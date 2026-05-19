<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\CompanyProfile;
use App\Models\DaySwapRequest;
use App\Models\DocumentAttachment;
use App\Models\DocumentTemplate;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\ExtraIncomeEntry;
use App\Models\LeaveCarryover;
use App\Models\LeaveEncashment;
use App\Models\LeaveRequest;
use App\Models\OtRequest;
use App\Services\AuditLogService;
use App\Services\DocumentFieldCatalog;
use App\Services\DocxTemplateRenderer;
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
        $hidePast = (bool) $request->boolean('hide_past');

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

        // Stats for header strip (computed BEFORE bucketing, so totals are honest)
        $stats = [
            'total'    => $documents->count(),
            'pending'  => $documents->where('status', 'pending')->count(),
            'approved' => $documents->where('status', 'approved')->count(),
            'rejected' => $documents->where('status', 'rejected')->count(),
        ];

        // Split into 3 buckets: pending → upcoming → past
        $today = now()->startOfDay()->timestamp;
        $pending  = $documents->where('status', 'pending')->sortBy('date_sortable')->values();
        $upcoming = $documents->filter(fn($d) => $d['status'] !== 'pending' && ($d['date_sortable'] ?? 0) >= $today)
                              ->sortBy('date_sortable')->values();
        $past     = $documents->filter(fn($d) => $d['status'] !== 'pending' && ($d['date_sortable'] ?? 0) < $today)
                              ->sortByDesc('date_sortable')->values();

        $pastCount = $past->count();

        $buckets = [
            'pending'  => [
                'label'      => 'รออนุมัติ',
                'sublabel'   => 'ต้องการการตัดสินใจของผู้อนุมัติ',
                'docs'       => $pending,
                'headerCls'  => 'bg-amber-50 border-amber-200 text-amber-900',
                'badgeCls'   => 'bg-amber-100 text-amber-800',
                'icon'       => '⏳',
            ],
            'upcoming' => [
                'label'      => 'กำลังจะถึง',
                'sublabel'   => 'อนุมัติแล้ว/ปฏิเสธแล้ว และยังไม่ถึงวันที่กำหนด',
                'docs'       => $upcoming,
                'headerCls'  => 'bg-sky-50 border-sky-200 text-sky-900',
                'badgeCls'   => 'bg-sky-100 text-sky-800',
                'icon'       => '📅',
            ],
            'past'     => [
                'label'      => 'ผ่านวันไปแล้ว',
                'sublabel'   => 'ประวัติ — อ้างอิงย้อนหลัง',
                'docs'       => $hidePast ? collect() : $past,
                'headerCls'  => 'bg-gray-50 border-gray-200 text-gray-700',
                'badgeCls'   => 'bg-gray-200 text-gray-700',
                'icon'       => '🗄',
                'collapsible'=> true,
            ],
        ];

        $employees = $isAdmin
            ? Employee::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'employee_code'])
            : collect();

        // ── Tab routing ──
        $activeTab = ($isAdmin && $request->get('tab') === 'leave') ? 'leave' : 'docs';

        $leaveData = [];
        if ($activeTab === 'leave') {
            $leaveData = $this->loadLeaveTabData($request);
        }

        return view('portal.index', array_merge([
            'buckets'   => $buckets,
            'documents' => $documents,
            'stats'     => $stats,
            'types'     => self::TYPES,
            'filters'   => compact('statusFilter', 'typeFilter', 'employeeFilter', 'year', 'month', 'hidePast'),
            'pastCount' => $pastCount,
            'employees' => $employees,
            'isAdmin'   => $isAdmin,
            'leaveTypes' => \App\Models\Employee::LEAVE_TYPE_LABELS,
            'activeTab' => $activeTab,
        ], $leaveData));
    }

    /**
     * Load leave management data for the "สิทธิวันลา" tab.
     */
    private function loadLeaveTabData(Request $request): array
    {
        $leaveYear = (int) $request->get('leave_year', now()->year);
        $today = now();

        $emps = Employee::with([
            'department', 'position', 'leavePolicy',
            'leaveCarryovers' => fn($q) => $q->where('year', $leaveYear),
            'leaveEncashments' => fn($q) => $q->where('year', $leaveYear),
        ])
            ->where('is_active', true)
            ->whereIn('payroll_mode', ['monthly_staff', 'office_staff', 'youtuber_salary'])
            ->orderBy('first_name')->orderBy('last_name')
            ->get();

        $leavePolicies = \App\Models\LeavePolicy::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();
        $leaveDepartments = \App\Models\Department::where('is_active', true)->orderBy('name')->get();

        $leaveRowsArr = $emps->map(function (Employee $emp) use ($leaveYear, $today) {
            $balances = $emp->getAllLeaveBalances($leaveYear);
            $policy = $emp->effectivePolicy();
            $isProbation = $emp->probation_end_date && $emp->probation_end_date->isFuture();
            $hasNoPolicy = !$policy;
            $vac = $balances['vacation_leave'] ?? null;
            $expiresMonths = $vac['carryover_expires_months'] ?? null;
            $carryIn = (float) ($vac['carryover'] ?? 0);
            $expiringSoon = false;
            $expiresAt = null;
            if ($expiresMonths !== null && $carryIn > 0) {
                $expiresAt = \Carbon\Carbon::create($leaveYear, 1, 1)->addMonths($expiresMonths);
                $expiringSoon = $expiresAt->diffInDays($today, false) >= -60 && $expiresAt->isAfter($today);
            }
            $lowVacation = ($vac['remaining'] ?? 0) < 5 && ($vac['limit'] ?? 0) > 0;

            return [
                'id' => $emp->id, 'code' => $emp->employee_code, 'name' => $emp->display_name,
                'dept_id' => $emp->department_id, 'dept_name' => $emp->department?->name ?? '—',
                'policy_id' => $policy?->id, 'policy_name' => $policy?->name,
                'policy_is_default' => (bool) $policy?->is_default,
                'is_probation' => $isProbation, 'has_no_policy' => $hasNoPolicy,
                'expiring_soon' => $expiringSoon, 'expires_at' => $expiresAt?->toDateString(),
                'low_vacation' => $lowVacation, 'balances' => $balances,
            ];
        })->values()->toArray();

        $leaveUnusedVacationCount = collect($leaveRowsArr)
            ->filter(fn($r) => ($r['balances']['vacation_leave']['remaining'] ?? 0) > 0 && ($r['balances']['vacation_leave']['allow_carryover'] ?? false))
            ->count();

        $leaveStats = [
            'total_employees'      => $emps->count(),
            'total_policies'       => $leavePolicies->count(),
            'total_carryover_days' => LeaveCarryover::whereIn('employee_id', $emps->pluck('id'))->where('year', $leaveYear)->sum('days'),
            'total_encash_days'    => LeaveEncashment::whereIn('employee_id', $emps->pluck('id'))->where('year', $leaveYear)->sum('days'),
            'total_encash_amount'  => LeaveEncashment::whereIn('employee_id', $emps->pluck('id'))->where('year', $leaveYear)->sum('amount'),
            'no_policy_count'      => collect($leaveRowsArr)->where('has_no_policy', true)->count(),
            'probation_count'      => collect($leaveRowsArr)->where('is_probation', true)->count(),
            'expiring_count'       => collect($leaveRowsArr)->where('expiring_soon', true)->count(),
            'low_vacation_count'   => collect($leaveRowsArr)->where('low_vacation', true)->count(),
        ];

        return compact('leaveRowsArr', 'leavePolicies', 'leaveDepartments', 'leaveYear', 'leaveStats', 'leaveUnusedVacationCount');
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

        // Leave stats for the printed PDF (ลามาแล้ว / ลาครั้งนี้ / table breakdown)
        $stats = null;
        if ($type === 'leave') {
            $year = Carbon::parse($doc->leave_date)->year;
            $leaveType = $doc->leave_type;

            $usedBefore = AttendanceLog::where('employee_id', $doc->employee_id)
                ->whereYear('log_date', $year)
                ->where('day_type', $leaveType)
                ->whereDate('log_date', '<', Carbon::parse($doc->leave_date)->toDateString())
                ->count();

            $current = 1;

            $stats = [
                'used_before' => $usedBefore,
                'current'     => $current,
                'total'       => $usedBefore + $current,
            ];

            // Breakdown table for combined sick/personal/maternity sheet
            if (in_array($leaveType, ['sick_leave', 'personal_leave', 'maternity_leave'], true)) {
                $stats['table'] = [
                    'sick_leave'      => AttendanceLog::where('employee_id', $doc->employee_id)->whereYear('log_date', $year)->where('day_type', 'sick_leave')->count(),
                    'personal_leave'  => AttendanceLog::where('employee_id', $doc->employee_id)->whereYear('log_date', $year)->where('day_type', 'personal_leave')->count(),
                    'maternity_leave' => AttendanceLog::where('employee_id', $doc->employee_id)->whereYear('log_date', $year)->where('day_type', 'maternity_leave')->count(),
                ];

                // Last leave of the same type (for "ครั้งสุดท้ายตั้งแต่วันที่ ... ถึงวันที่ ...")
                $lastLog = AttendanceLog::where('employee_id', $doc->employee_id)
                    ->where('day_type', $leaveType)
                    ->whereDate('log_date', '<', Carbon::parse($doc->leave_date)->toDateString())
                    ->orderByDesc('log_date')
                    ->first();
                $stats['last_leave_date'] = $lastLog ? Carbon::parse($lastLog->log_date) : null;
            }

            // Vacation balance — fills "สิทธิลาสะสม / ลาประจำปีนี้ / รวม" on the vacation form
            if ($leaveType === 'vacation_leave' && method_exists($doc->employee, 'getLeaveBalance')) {
                $bal = $doc->employee->getLeaveBalance('vacation_leave', $year);
                $stats['balance'] = [
                    'carryover' => (float) ($bal['carryover'] ?? 0),
                    'annual'    => (float) ($bal['limit'] ?? 0),
                    'total'     => (float) (($bal['carryover'] ?? 0) + ($bal['limit'] ?? 0)),
                ];
            }
        }

        // Prefer custom template if one is configured for this doc/variant
        $variant = DocumentFieldCatalog::variantFor($type, $doc);
        $template = DocumentTemplate::resolveFor($type, $variant);

        if ($template && $template->kind === 'docx') {
            // DOCX path: fill placeholders → LibreOffice → PDF
            try {
                $pdfPath = DocxTemplateRenderer::render($template, $doc);
                $filename = ($doc->document_number ?? 'document') . '.pdf';
                return response()->file($pdfPath, [
                    'Content-Type'        => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $filename . '"',
                ])->deleteFileAfterSend(true);
            } catch (\Throwable $e) {
                return response($e->getMessage(), 500)->header('Content-Type', 'text/plain; charset=utf-8');
            }
        }

        $template?->load('fields');
        $pdf = app('dompdf.wrapper');
        if ($template && $template->kind === 'image') {
            $pdf->loadView('portal.pdf._template_overlay', compact('doc', 'template'));
        } else {
            $pdf->loadView("portal.pdf.$type", compact('doc', 'company', 'stats'));
        }
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
