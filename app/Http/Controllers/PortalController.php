<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\CompanyProfile;
use App\Models\DaySwapRequest;
use App\Models\DocumentAttachment;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\ExtraIncomeEntry;
use App\Models\LeaveCarryover;
use App\Models\LeaveEncashment;
use App\Models\LeaveRequest;
use App\Models\OtRequest;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class PortalController extends Controller
{
    /**
     * Document type catalogue. Keys = URL slug; metadata drives lookup, view labels, and signing.
     */
    public const TYPES = [
        'leave' => [
            'model'    => LeaveRequest::class,
            'label'    => 'ใบลา',
            'icon'     => '🏖️',
            'color'    => 'blue',
            'date_col' => 'leave_date',
            'allow_attachments' => true,
        ],
        'ot' => [
            'model'    => OtRequest::class,
            'label'    => 'ใบขอ OT',
            'icon'     => '⏰',
            'color'    => 'orange',
            'date_col' => 'log_date',
            'allow_attachments' => false,
        ],
        'swap' => [
            'model'    => DaySwapRequest::class,
            'label'    => 'ใบสลับวัน',
            'icon'     => '🔄',
            'color'    => 'purple',
            'date_col' => 'work_date',
            'allow_attachments' => false,
        ],
        'expense' => [
            'model'    => ExpenseClaim::class,
            'label'    => 'ใบเบิกเงิน',
            'icon'     => '💰',
            'color'    => 'emerald',
            'date_col' => 'claim_date',
            'allow_attachments' => true,
        ],
        'carryover' => [
            'model'    => LeaveCarryover::class,
            'label'    => 'ใบยกยอดวันลา',
            'icon'     => '📥',
            'color'    => 'indigo',
            'date_col' => 'created_at',
            'allow_attachments' => true,
        ],
        'encash' => [
            'model'    => LeaveEncashment::class,
            'label'    => 'ใบแลกวันลาเป็นเงิน',
            'icon'     => '💵',
            'color'    => 'teal',
            'date_col' => 'created_at',
            'allow_attachments' => true,
        ],
    ];

    // ─── Index: unified dashboard ───────────────────────────────────────────

    public function index(Request $request)
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');
        $myEmployeeId = $user->employee?->id;

        $statusFilter = $request->string('status')->toString() ?: 'all';
        $typeFilter = $request->string('type')->toString() ?: 'all';
        $employeeFilter = $request->integer('employee_id') ?: null;
        $year = $request->integer('year') ?: (int) now()->year;
        $month = $request->integer('month') ?: null;

        $documents = collect();
        foreach (self::TYPES as $slug => $meta) {
            if ($typeFilter !== 'all' && $typeFilter !== $slug) continue;

            /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
            $model = $meta['model'];
            $q = $model::query()->with(['employee.position', 'attachments']);

            if (!$isAdmin) {
                if (!$myEmployeeId) continue;
                $q->where('employee_id', $myEmployeeId);
            } elseif ($employeeFilter) {
                $q->where('employee_id', $employeeFilter);
            }

            if ($statusFilter !== 'all') {
                $q->where('status', $statusFilter);
            }

            $dateCol = $meta['date_col'];
            if ($year)  $q->whereYear($dateCol, $year);
            if ($month) $q->whereMonth($dateCol, $month);

            $q->orderByDesc($dateCol)->orderByDesc('id');

            foreach ($q->get() as $doc) {
                $documents->push($this->serialize($doc, $slug, $meta));
            }
        }

        $documents = $documents->sortByDesc('date_sortable')->values();

        // Stats for header strip
        $stats = [
            'total'    => $documents->count(),
            'pending'  => $documents->where('status', 'pending')->count(),
            'approved' => $documents->where('status', 'approved')->count(),
            'rejected' => $documents->where('status', 'rejected')->count(),
        ];

        $employees = $isAdmin
            ? Employee::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_code'])
            : collect();

        return view('portal.index', [
            'documents' => $documents,
            'stats'     => $stats,
            'types'     => self::TYPES,
            'filters'   => compact('statusFilter', 'typeFilter', 'employeeFilter', 'year', 'month'),
            'employees' => $employees,
            'isAdmin'   => $isAdmin,
            'leaveTypes' => \App\Models\Employee::LEAVE_TYPE_LABELS,
        ]);
    }

    // ─── Show: detail page for a single document ────────────────────────────

    public function show(string $type, int $id)
    {
        [$meta, $doc] = $this->loadDocument($type, $id);
        $this->authorizeAccess($doc);
        $doc->load(['employee.position', 'employee.department', 'attachments.uploader']);

        return view('portal.show', [
            'type' => $type,
            'meta' => $meta,
            'doc'  => $doc,
            'isAdmin' => Auth::user()->hasRole('admin'),
        ]);
    }

    // ─── Print: PDF for a single document (2 signatures) ────────────────────

    public function print(string $type, int $id)
    {
        [$meta, $doc] = $this->loadDocument($type, $id);
        $this->authorizeAccess($doc);
        $doc->load(['employee.position', 'employee.department']);

        $company = CompanyProfile::active();

        $pdf = app('dompdf.wrapper');
        $pdf->loadView("portal.pdf.$type", compact('doc', 'company'));
        $pdf->setPaper('a4', 'portrait');
        $filename = $doc->document_number . '.pdf';
        return $pdf->stream($filename);
    }

    // ─── Approve / Reject ───────────────────────────────────────────────────

    public function approve(string $type, int $id, Request $request)
    {
        $this->requireAdmin();
        [$meta, $doc] = $this->loadDocument($type, $id);
        $note = $request->string('review_note')->toString() ?: null;
        $this->updateStatus($doc, 'approved', $note);
        return back()->with('success', "อนุมัติ {$doc->document_number} แล้ว");
    }

    public function reject(string $type, int $id, Request $request)
    {
        $this->requireAdmin();
        [$meta, $doc] = $this->loadDocument($type, $id);
        $note = $request->string('review_note')->toString() ?: null;
        $this->updateStatus($doc, 'rejected', $note);
        return back()->with('success', "ปฏิเสธ {$doc->document_number} แล้ว");
    }

    // ─── Attachments ────────────────────────────────────────────────────────

    public function uploadAttachment(string $type, int $id, Request $request)
    {
        [$meta, $doc] = $this->loadDocument($type, $id);
        $this->authorizeAccess($doc);

        if (!($meta['allow_attachments'] ?? false)) {
            return back()->withErrors(['file' => 'เอกสารประเภทนี้ไม่รองรับไฟล์แนบ']);
        }

        $request->validate([
            'file' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png,webp,doc,docx',
            'note' => 'nullable|string|max:255',
        ]);

        $doc->addAttachment($request->file('file'), Auth::id(), $request->input('note'));

        return back()->with('success', 'อัปโหลดไฟล์แนบสำเร็จ');
    }

    public function deleteAttachment(string $type, int $id, DocumentAttachment $attachment)
    {
        [$meta, $doc] = $this->loadDocument($type, $id);
        $this->authorizeAccess($doc);

        // Owner of the doc OR admin can remove their own/anyone's attachment respectively
        $user = Auth::user();
        if (!$user->hasRole('admin') && $attachment->uploaded_by !== $user->id) {
            abort(403, 'ลบได้เฉพาะไฟล์ที่คุณอัปโหลด');
        }

        $doc->deleteAttachment($attachment);
        return back()->with('success', 'ลบไฟล์แนบสำเร็จ');
    }

    // ─── Bulk export (replaces salary-advance bulk export) ──────────────────

    public function bulkExport(Request $request)
    {
        $this->requireAdmin();

        $validated = $request->validate([
            'documents'   => 'required|array|min:1',
            'documents.*' => 'required|string', // "type:id" pairs
            'mode'        => 'required|in:single,separate',
        ]);

        $items = collect($validated['documents'])
            ->map(function ($pair) {
                [$type, $id] = explode(':', $pair, 2);
                if (!isset(self::TYPES[$type])) return null;
                $model = self::TYPES[$type]['model'];
                $doc = $model::with(['employee.position', 'employee.department'])->find($id);
                return $doc ? compact('type', 'doc') : null;
            })
            ->filter()
            ->values();

        if ($items->isEmpty()) {
            return back()->withErrors(['error' => 'ไม่พบเอกสารที่เลือก']);
        }

        $company = CompanyProfile::active();

        if ($validated['mode'] === 'single') {
            // Render each doc to HTML, separated by page breaks, then a single dompdf pass
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>';
            foreach ($items as $i => $item) {
                $rendered = view("portal.pdf.{$item['type']}", ['doc' => $item['doc'], 'company' => $company])->render();
                // Strip the outer html/body since we're concatenating into one document
                $body = $this->extractBody($rendered);
                $html .= '<div' . ($i > 0 ? ' style="page-break-before: always;"' : '') . '>' . $body . '</div>';
            }
            $html .= '</body></html>';

            $pdf = app('dompdf.wrapper');
            $pdf->loadHTML($html);
            $pdf->setPaper('a4', 'portrait');
            return $pdf->download('portal_combined_' . now()->format('Ymd_His') . '.pdf');
        }

        return $this->renderZip($items, $company);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    protected function loadDocument(string $type, int $id): array
    {
        if (!isset(self::TYPES[$type])) {
            abort(404, 'ไม่พบประเภทเอกสาร');
        }
        $meta = self::TYPES[$type];
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
        $model = $meta['model'];
        $doc = $model::findOrFail($id);
        return [$meta, $doc];
    }

    protected function authorizeAccess($doc): void
    {
        $user = Auth::user();
        if ($user->hasRole('admin')) return;

        $myEmployeeId = $user->employee?->id;
        if (!$myEmployeeId || $doc->employee_id !== $myEmployeeId) {
            abort(403, 'ไม่มีสิทธิ์เข้าถึงเอกสารนี้');
        }
    }

    protected function requireAdmin(): void
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403, 'เฉพาะ admin เท่านั้น');
    }

    protected function updateStatus($doc, string $status, ?string $note): void
    {
        DB::transaction(function () use ($doc, $status, $note) {
            $old = $doc->status;
            $payload = [
                'status' => $status,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ];
            if ($note !== null) {
                $payload['review_note'] = $note;
            }
            // ExpenseClaim doesn't have reviewed_by/reviewed_at columns — strip them
            if ($doc instanceof ExpenseClaim) {
                $payload = ['status' => $status];
                if ($status === 'approved') $payload['approved_at'] = now();
            }
            // Leave carryover/encashment use approved_by/approved_at + rejection_reason
            if ($doc instanceof LeaveCarryover || $doc instanceof LeaveEncashment) {
                $payload = [
                    'status' => $status,
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                ];
                if ($status === 'rejected' && $note !== null) {
                    $payload['rejection_reason'] = $note;
                }
            }
            $doc->update($payload);

            // Side-effect: when LeaveRequest is approved, write to AttendanceLog so balance reflects it
            if ($doc instanceof LeaveRequest && $status === 'approved') {
                $dateStr = Carbon::parse($doc->leave_date)->toDateString();
                $log = AttendanceLog::where('employee_id', $doc->employee_id)
                    ->whereDate('log_date', $dateStr)->first();
                if ($log) {
                    $log->update(['day_type' => $doc->leave_type]);
                } else {
                    AttendanceLog::create([
                        'employee_id' => $doc->employee_id,
                        'log_date'    => $dateStr,
                        'day_type'    => $doc->leave_type,
                    ]);
                }
            }
            // Side-effect: when LeaveRequest is rejected after prior approval, revert AttendanceLog day_type
            if ($doc instanceof LeaveRequest && $status === 'rejected' && $old === 'approved') {
                $dateStr = Carbon::parse($doc->leave_date)->toDateString();
                AttendanceLog::where('employee_id', $doc->employee_id)
                    ->whereDate('log_date', $dateStr)
                    ->where('day_type', $doc->leave_type)
                    ->update(['day_type' => 'workday']);
            }

            // Side-effect: when DaySwapRequest is approved, write workday/holiday flips into AttendanceLog
            if ($doc instanceof DaySwapRequest && $status === 'approved') {
                foreach ([
                    ['date' => $doc->work_date, 'type' => 'workday'],
                    ['date' => $doc->off_date,  'type' => 'holiday'],
                ] as $entry) {
                    $dateStr = Carbon::parse($entry['date'])->toDateString();
                    $log = AttendanceLog::where('employee_id', $doc->employee_id)
                        ->whereDate('log_date', $dateStr)->first();
                    if ($log) {
                        $log->update([
                            'day_type'              => $entry['type'],
                            'is_swapped_day'        => true,
                            'swapped_from_day_type' => $log->day_type,
                        ]);
                    } else {
                        AttendanceLog::create([
                            'employee_id'    => $doc->employee_id,
                            'log_date'       => $dateStr,
                            'day_type'       => $entry['type'],
                            'is_swapped_day' => true,
                        ]);
                    }
                }
            }

            // Side-effect: when LeaveEncashment becomes approved, create the ExtraIncomeEntry that pays the employee
            if ($doc instanceof LeaveEncashment && $status === 'approved' && empty($doc->extra_income_entry_id)) {
                $extra = ExtraIncomeEntry::create([
                    'employee_id' => $doc->employee_id,
                    'month' => $doc->payout_month,
                    'year' => $doc->payout_year,
                    'label' => 'แลกวันลาเป็นเงิน (' . ($doc->leave_type === 'vacation_leave' ? 'ลาพักร้อน' : $doc->leave_type) . ')',
                    'category' => 'leave_encashment',
                    'amount' => $doc->amount,
                    'include_in_payslip' => true,
                    'notes' => "แลกวันลา {$doc->days} วัน [LE#{$doc->id}]",
                    'created_by' => Auth::id(),
                ]);
                $doc->update(['extra_income_entry_id' => $extra->id]);
            }
            // Side-effect: when LeaveEncashment is rejected after a previous approval, remove its ExtraIncomeEntry
            if ($doc instanceof LeaveEncashment && $status === 'rejected' && $doc->extra_income_entry_id) {
                ExtraIncomeEntry::where('id', $doc->extra_income_entry_id)->delete();
                $doc->update(['extra_income_entry_id' => null]);
            }

            $docLabel = $doc->document_number ?? class_basename($doc) . '#' . $doc->id;
            AuditLogService::log($doc, "status_{$status}", 'status', $old, $status,
                "เอกสาร {$docLabel} เปลี่ยนสถานะเป็น {$status}");
        });
    }

    protected function serialize($doc, string $type, array $meta): array
    {
        $dateCol = $meta['date_col'];
        $date = $doc->{$dateCol} ? Carbon::parse($doc->{$dateCol}) : null;
        $docNumber = $doc->document_number ?? strtoupper($type) . '-' . str_pad((string) $doc->id, 5, '0', STR_PAD_LEFT);
        return [
            'type'         => $type,
            'meta'         => $meta,
            'id'           => $doc->id,
            'doc_number'   => $docNumber,
            'employee'     => $doc->employee,
            'date'         => $date,
            'date_sortable'=> $date ? $date->timestamp : 0,
            'status'       => $doc->status,
            'summary'      => $this->summary($doc, $type),
            'attachments_count' => $doc->attachments?->count() ?? 0,
            'created_at'   => $doc->created_at,
        ];
    }

    protected function summary($doc, string $type): string
    {
        return match ($type) {
            'leave'     => trim($this->leaveTypeLabel($doc->leave_type) . ($doc->reason ? ' — ' . $doc->reason : '')),
            'ot'        => trim(round($doc->requested_minutes / 60, 2) . ' ชม.' . ($doc->reason ? ' — ' . $doc->reason : '')),
            'swap'      => 'มาทำงาน ' . optional($doc->work_date)->format('d/m/Y') . ' (หยุดทดแทน ' . optional($doc->off_date)->format('d/m/Y') . ')',
            'expense'   => trim(($doc->type === 'advance' ? 'เบิกล่วงหน้า' : 'เบิกค่าใช้จ่าย') . ' ' . number_format((float) $doc->amount, 2) . ' บาท' . ($doc->description ? ' — ' . $doc->description : '')),
            'carryover' => 'ยกยอด ' . $this->leaveTypeLabel($doc->leave_type) . ' ' . rtrim(rtrim(number_format((float) $doc->days, 1), '0'), '.') . ' วัน · ปี ' . ($doc->source_year ?? '?') . ' → ' . $doc->year,
            'encash'    => 'แลก ' . $this->leaveTypeLabel($doc->leave_type) . ' ' . rtrim(rtrim(number_format((float) $doc->days, 1), '0'), '.') . ' วัน · ' . number_format((float) $doc->amount, 2) . ' บาท',
        };
    }

    protected function leaveTypeLabel(string $type): string
    {
        return \App\Models\Employee::LEAVE_TYPE_LABELS[$type] ?? $type;
    }

    protected function extractBody(string $html): string
    {
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $m)) {
            $body = $m[1];
        } else {
            $body = $html;
        }
        // Pull through the <style> blocks too so each section keeps its formatting
        if (preg_match_all('/<style[^>]*>.*?<\/style>/is', $html, $styles)) {
            $body = implode("\n", $styles[0]) . $body;
        }
        return $body;
    }

    protected function renderZip($items, ?CompanyProfile $company)
    {
        $tmpDir = storage_path('app/tmp_portal_' . uniqid());
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $zipPath = $tmpDir . '/export.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('ไม่สามารถสร้างไฟล์ ZIP ได้');
        }

        foreach ($items as $item) {
            $type = $item['type'];
            $doc = $item['doc'];
            $pdf = app('dompdf.wrapper');
            $pdf->loadView("portal.pdf.$type", compact('doc', 'company'));
            $pdf->setPaper('a4', 'portrait');
            $zip->addFromString($doc->document_number . '.pdf', $pdf->output());
        }
        $zip->close();

        $download = 'portal_documents_' . now()->format('Ymd_His') . '.zip';
        return response()->download($zipPath, $download)->deleteFileAfterSend(true);
    }
}
