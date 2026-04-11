<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\AttendanceLog;
use App\Models\AttendanceDaySwap;
use App\Models\AttendanceRule;
use App\Models\WorkLog;
use App\Models\WorkAssignment;
use App\Models\PayrollItem;
use App\Models\PayrollBatch;
use App\Models\LayerRateRule;
use App\Models\PerformanceRecord;
use App\Models\WorkLogType;
use App\Models\EditJob;
use App\Models\RecordingJobAssignee;
use App\Services\Payroll\PayrollCalculationService;
use App\Services\AuditLogService;
use App\Support\DurationInput;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkspaceController extends Controller
{
    public function __construct(
        protected PayrollCalculationService $payrollService
    ) {}

    public function show(Employee $employee, int $month, int $year)
    {
        $employee->load(['department', 'position', 'salaryProfile', 'bankAccount', 'profile']);

        $dayTypeLabels = [
            'workday' => 'วันทำงาน',
            'holiday' => 'วันหยุด',
            'sick_leave' => 'ลาป่วย',
            'personal_leave' => 'ลากิจ',
            'vacation_leave' => 'ลาพักร้อน',
            'ot_full_day' => 'OT เต็มวัน',
            'lwop' => 'LWOP',
            'not_started' => 'ยังไม่เริ่มงาน',
            'company_holiday' => 'วันหยุดบริษัท',
        ];

        $dayTypeColors = [
            'workday' => 'bg-green-100 text-green-800',
            'holiday' => 'bg-orange-100 text-orange-800',
            'sick_leave' => 'bg-blue-100 text-blue-800',
            'personal_leave' => 'bg-yellow-100 text-yellow-800',
            'vacation_leave' => 'bg-teal-100 text-teal-800',
            'ot_full_day' => 'bg-indigo-100 text-indigo-800',
            'lwop' => 'bg-red-100 text-red-800',
            'not_started' => 'bg-gray-200 text-gray-500',
            'company_holiday' => 'bg-purple-100 text-purple-800',
        ];

        // Generate attendance logs for monthly-attendance based modes
        if (in_array($employee->payroll_mode, ['monthly_staff', 'youtuber_salary'])) {
            $this->ensureAttendanceLogs($employee, $month, $year);
        }

        $attendanceLogs = AttendanceLog::where('employee_id', $employee->id)
            ->whereMonth('log_date', $month)
            ->whereYear('log_date', $year)
            ->orderBy('log_date')
            ->get();

        $workLogs = WorkLog::where('employee_id', $employee->id)
            ->where('month', $month)
            ->where('year', $year)
            ->orderBy('sort_order')
            ->get();

        $layerRates = LayerRateRule::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->orderBy('layer_from')
            ->get();

        $workingHoursRule = AttendanceRule::getActiveRule('working_hours');
        $attendanceMeta = [
            'target_check_in' => $workingHoursRule?->config['target_check_in'] ?? '09:30',
            'target_check_out' => $workingHoursRule?->config['target_check_out'] ?? '18:30',
            'target_minutes_per_day' => (int) ($workingHoursRule?->config['target_minutes_per_day'] ?? 540),
        ];

        // Calculate payroll
        $result = $this->payrollService->calculateForEmployee($employee, $month, $year);

        // Get existing payroll items (may include manual overrides)
        $batch = PayrollBatch::where('month', $month)->where('year', $year)->first();
        $payrollItems = $batch
            ? PayrollItem::where('employee_id', $employee->id)
                ->where('payroll_batch_id', $batch->id)
                ->get()
            : collect();

        $payslip = \App\Models\Payslip::where('employee_id', $employee->id)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        $proofs = \App\Models\PaymentProof::where('employee_id', $employee->id)
            ->whereHas('payslip', function($q) use ($month, $year) {
                $q->where('month', $month)->where('year', $year);
            })->orWhere(function($q) use ($employee, $month, $year) {
                $q->where('employee_id', $employee->id)
                  ->whereNull('payslip_id')
                  ->whereMonth('created_at', $month)
                  ->whereYear('created_at', $year);
            })->get();

        $claims = \App\Models\ExpenseClaim::where('employee_id', $employee->id)
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        $panel = $employee->position?->workspace_panel ?? 'recording_queue';

        $hasEditAssignments = EditJob::where('assigned_to', $employee->id)->exists();
        $hasRecordingAssignments = RecordingJobAssignee::where('employee_id', $employee->id)
            ->whereHas('recordingJob', fn($q) => $q->whereNotIn('status', ['cancelled']))
            ->exists();

        // Freelance workspace should follow actual assignments first.
        if (in_array($employee->payroll_mode, ['freelance_layer', 'freelance_fixed'], true)) {
            if ($hasEditAssignments) {
                $panel = 'edit_jobs';
            } elseif ($hasRecordingAssignments) {
                $panel = 'recording_queue';
            } else {
                $panel = 'none';
            }
        }

        // Edit jobs assigned to this employee from WORK Center
        $assignedEditJobs = $panel === 'edit_jobs'
            ? EditJob::with('mediaResource')
                ->where('assigned_to', $employee->id)
                ->orderByRaw("CASE status
                    WHEN 'assigned' THEN 1
                    WHEN 'editing' THEN 2
                    WHEN 'submitted' THEN 3
                    WHEN 'approved' THEN 4
                    WHEN 'done' THEN 5
                    ELSE 99
                END")
                ->orderBy('due_date')
                ->get()
            : collect();

        $recordingAssignments = $panel === 'recording_queue'
            ? RecordingJobAssignee::with('recordingJob')
                ->where('employee_id', $employee->id)
                ->whereHas('recordingJob', fn($q) => $q->whereNotIn('status', ['cancelled']))
                ->orderByDesc('id')
                ->get()
            : collect();

        return view('workspace.show', compact(
            'employee', 'month', 'year',
            'attendanceLogs', 'workLogs', 'layerRates',
            'result', 'payrollItems', 'payslip', 'proofs', 'claims',
            'dayTypeLabels', 'dayTypeColors', 'attendanceMeta', 'assignedEditJobs', 'recordingAssignments', 'panel'
        ));
    }

    public function storeClaim(Request $request, Employee $employee, int $month, int $year)
    {
        try {
            $validated = $request->validate([
                'description' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0.01',
                'type' => 'required|in:reimbursement,advance',
                'claim_date' => 'required|date',
            ]);

            // Validate Ceiling for Advance
            if ($validated['type'] === 'advance' && $employee->advance_ceiling_percent > 0) {
                $baseSalary = $employee->salaryProfile?->base_salary ?? 0;
                $limit = ($baseSalary * $employee->advance_ceiling_percent) / 100;
                
                $currentAdvances = \App\Models\ExpenseClaim::where('employee_id', $employee->id)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->where('type', 'advance')
                    ->sum('amount');

                if (($currentAdvances + $validated['amount']) > $limit) {
                    return back()->withErrors(['amount' => "ยอดเบิกเงินล่วงหน้าเกินเพดานที่กำหนดไว้ (" . number_format($limit, 2) . " บาท)"]);
                }
            }

            $claim = \App\Models\ExpenseClaim::create([
                'employee_id' => $employee->id,
                'description' => $validated['description'],
                'amount'      => $validated['amount'],
                'type'        => $validated['type'],
                'claim_date'  => $validated['claim_date'],
                'status'      => 'pending',
                'month'       => $month,
                'year'        => $year,
            ]);

            \App\Services\AuditLogService::logCreated($claim, 'Claim created for employee #' . $employee->id);

            return back()->with('success', 'บันทึกรายการเบิกสำเร็จ');
        } catch (\Throwable $e) {
            Log::error('storeClaim error', [
                'employee_id' => $employee->id,
                'month' => $month,
                'year' => $year,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการบันทึกรายการเบิก: ' . $e->getMessage()]);
        }
    }

    public function updateAdvanceCeiling(Request $request, Employee $employee)
    {
        try {
            return DB::transaction(function () use ($request, $employee) {
                $validated = $request->validate([
                    'advance_ceiling_percent' => 'nullable|numeric|min:0|max:100',
                ]);

                $oldValue = $employee->advance_ceiling_percent;

                $employee->update([
                    'advance_ceiling_percent' => $validated['advance_ceiling_percent'] ?: 0,
                ]);

                \App\Services\AuditLogService::log(
                    $employee,
                    'advance_ceiling_updated',
                    'advance_ceiling_percent',
                    $oldValue,
                    $employee->advance_ceiling_percent
                );

                return back()->with('success', 'อัปเดตเพดานการเบิกเงินสำเร็จ');
            });
        } catch (\Throwable $e) {
            Log::error('updateAdvanceCeiling error', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการอัปเดตเพดานการเบิกเงิน: ' . $e->getMessage()]);
        }
    }

    public function toggleWorkLog(\App\Models\WorkLog $workLog)
    {
        $workLog->update(['is_disabled' => !$workLog->is_disabled]);
        return back();
    }

    public function approveClaim(\App\Models\ExpenseClaim $claim)
    {
        try {
            return DB::transaction(function () use ($claim) {
                $oldStatus = $claim->status;
                
                $claim->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                ]);

                \App\Services\AuditLogService::log(
                    $claim,
                    'approved',
                    'status',
                    $oldStatus,
                    'approved'
                );

                return back()->with('success', 'อนุมัติรายการเบิกสำเร็จ');
            });
        } catch (\Throwable $e) {
            Log::error('approveClaim error', [
                'claim_id' => $claim->id,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการอนุมัติรายการเบิก: ' . $e->getMessage()]);
        }
    }

    public function deleteClaim(\App\Models\ExpenseClaim $claim)
    {
        try {
            return DB::transaction(function () use ($claim) {
                $claimData = $claim->getAttributes();
                
                $claim->delete();

                \App\Services\AuditLogService::logDeleted($claim, 'Claim deleted by ' . (auth()->user()?->name ?? 'system'));

                return back()->with('success', 'ลบรายการเบิกสำเร็จ');
            });
        } catch (\Throwable $e) {
            Log::error('deleteClaim error', [
                'claim_id' => $claim->id,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการลบรายการเบิก: ' . $e->getMessage()]);
        }
    }

    public function uploadProof(Request $request, Employee $employee, int $month, int $year)
    {
        try {
            $request->validate([
                'proof' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $payslip = \App\Models\Payslip::where('employee_id', $employee->id)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            $path = $request->file('proof')->store('proofs', 'public');
            
            if (!$path) {
                throw new \Exception('ความล้มเหลวในการจัดเก็บไฟล์');
            }

            $proof = \App\Models\PaymentProof::create([
                'employee_id' => $employee->id,
                'payslip_id' => $payslip?->id,
                'file_path' => $path,
                'original_filename' => $request->file('proof')->getClientOriginalName(),
            ]);

            \App\Services\AuditLogService::logCreated($proof, 'Proof uploaded for employee #' . $employee->id);

            return back()->with('success', 'อัปโหลดหลักฐานการโอนเงินสำเร็จ');
        } catch (\Throwable $e) {
            Log::error('uploadProof error', [
                'employee_id' => $employee->id,
                'month' => $month,
                'year' => $year,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการอัปโหลดหลักฐาน: ' . $e->getMessage()]);
        }
    }

    public function toggleModule(Request $request, Employee $employee)
    {
        try {
            return DB::transaction(function () use ($request, $employee) {
                $moduleName = $request->input('module_name');
                
                if (!$moduleName) {
                    throw new \Exception('ชื่อโมดูลไม่ระบุ');
                }

                $toggle = \App\Models\ModuleToggle::firstOrNew([
                    'employee_id' => $employee->id,
                    'module_name' => $moduleName,
                ]);
                $oldState = $toggle->is_enabled ?? false;
                $toggle->is_enabled = !$toggle->is_enabled;
                $toggle->save();

                \App\Services\AuditLogService::log(
                    $toggle,
                    'module_toggled',
                    'is_enabled',
                    $oldState,
                    $toggle->is_enabled,
                    'Module: ' . $moduleName
                );

                return back()->with('success', 'ปรับการตั้งค่าสำเร็จ');
            });
        } catch (\Throwable $e) {
            Log::error('toggleModule error', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการปรับการตั้งค่า: ' . $e->getMessage()]);
        }
    }

    public function recalculate(Employee $employee, int $month, int $year)
    {
        try {
            return DB::transaction(function () use ($employee, $month, $year) {
                $result = $this->payrollService->calculateForEmployee($employee, $month, $year);
                $this->payrollService->savePayrollItems($employee, $month, $year, $result);
                $this->payrollService->syncWorkLogAmounts($employee, $month, $year);

                \App\Services\AuditLogService::logAction(
                    'payroll_recalculated',
                    'employees',
                    $employee->id,
                    null,
                    [
                        'month'          => $month,
                        'year'           => $year,
                        'total_income'   => $result['summary']['total_income'] ?? 0,
                        'total_deduction' => $result['summary']['total_deduction'] ?? 0,
                        'net_pay'        => $result['summary']['net_pay'] ?? 0,
                    ]
                );

                return redirect()
                    ->route('workspace.show', ['employee' => $employee->id, 'month' => $month, 'year' => $year])
                    ->with('success', 'คำนวณเงินเดือนใหม่สำเร็จ');
            });
        } catch (\Throwable $e) {
            Log::error('recalculate error', [
                'employee_id' => $employee->id,
                'month' => $month,
                'year' => $year,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการคำนวณเงินเดือน: ' . $e->getMessage()]);
        }
    }

    public function saveAttendance(Request $request, Employee $employee, int $month, int $year)
    {
        try {
            return DB::transaction(function () use ($request, $employee, $month, $year) {
                $logs = $request->input('attendance', []);
                $changes = [];
                $workingHoursRule = AttendanceRule::getActiveRule('working_hours');
                $targetCheckIn = $workingHoursRule?->config['target_check_in'] ?? '09:30';
                $targetCheckOut = $workingHoursRule?->config['target_check_out'] ?? '18:30';
                $targetMinutesPerDay = (int) ($workingHoursRule?->config['target_minutes_per_day'] ?? 540);

                foreach ($logs as $logId => $data) {
                    $log = AttendanceLog::findOrFail($logId);
                    $oldData = $log->getAttributes();
                    $oldDayType = $log->day_type;

                    $dayType = $data['day_type'] ?? 'workday';
                    $isWorkday = in_array($dayType, ['workday', 'ot_full_day']);
                    $isLwop = $dayType === 'lwop' || isset($data['lwop_flag']);

                    $checkIn = $data['check_in'] ?? null;
                    $checkOut = $data['check_out'] ?? null;

                    $lateMinutes = 0;
                    $otMinutes = 0;
                    $earlyLeaveMinutes = 0;

                    if ($isWorkday && !$isLwop && $checkIn && $checkOut) {
                        $inAt = Carbon::parse("{$log->log_date->format('Y-m-d')} {$checkIn}:00");
                        $outAt = Carbon::parse("{$log->log_date->format('Y-m-d')} {$checkOut}:00");

                        if ($outAt->lessThanOrEqualTo($inAt)) {
                            $outAt->addDay();
                        }

                        $targetInAt = Carbon::parse("{$log->log_date->format('Y-m-d')} {$targetCheckIn}:00");
                        $targetOutAt = Carbon::parse("{$log->log_date->format('Y-m-d')} {$targetCheckOut}:00");

                        if ($inAt->greaterThan($targetInAt)) {
                            $lateMinutes = $targetInAt->diffInMinutes($inAt);
                        }

                        if ($outAt->lessThan($targetOutAt)) {
                            $earlyLeaveMinutes = $outAt->diffInMinutes($targetOutAt);
                        }

                        $workedMinutes = max(0, $inAt->diffInMinutes($outAt));
                        if (isset($data['ot_enabled'])) {
                            $otMinutes = max(0, $workedMinutes - $targetMinutesPerDay);
                        }
                    } else {
                        $checkIn = null;
                        $checkOut = null;
                    }

                    // Allow manual fallback when no check in/out is provided
                    if (!$checkIn || !$checkOut) {
                        $lateMinutes = (int) ($data['late_minutes'] ?? 0);
                        $otMinutes = isset($data['ot_enabled']) ? (int) ($data['ot_minutes'] ?? 0) : 0;
                    }

                    $isSwapToWorkday = $dayType === 'workday' && in_array($oldDayType, ['holiday', 'company_holiday']);
                    $clearSwapFlag = $log->is_swapped_day && $dayType !== 'workday';

                    $swapAttributes = [];
                    if ($isSwapToWorkday) {
                        $swapAttributes = [
                            'is_swapped_day' => true,
                            'swapped_from_day_type' => $log->swapped_from_day_type ?: $oldDayType,
                            'swapped_at' => now(),
                            'swapped_by' => auth()->id(),
                        ];
                    } elseif ($clearSwapFlag) {
                        $swapAttributes = [
                            'is_swapped_day' => false,
                            'swapped_from_day_type' => null,
                            'swapped_at' => null,
                            'swapped_by' => null,
                        ];
                    }

                    $log->update([
                        'day_type' => $dayType,
                        'check_in' => $checkIn ?: null,
                        'check_out' => $checkOut ?: null,
                        'late_minutes' => $lateMinutes,
                        'early_leave_minutes' => $earlyLeaveMinutes,
                        'ot_minutes' => $otMinutes,
                        'ot_enabled' => isset($data['ot_enabled']),
                        'lwop_flag' => $isLwop,
                        ...$swapAttributes,
                    ]);

                    if ($oldDayType !== $dayType) {
                        AttendanceDaySwap::create([
                            'employee_id' => $employee->id,
                            'attendance_log_id' => $log->id,
                            'log_date' => $log->log_date,
                            'from_day_type' => $oldDayType,
                            'to_day_type' => $dayType,
                            'swap_reason' => $isSwapToWorkday
                                ? 'manual_workday_swap'
                                : 'manual_day_type_change',
                            'swapped_by' => auth()->id(),
                        ]);
                    }

                    $changes[$logId] = [
                        'old' => $oldData,
                        'new' => $log->fresh()->getAttributes(),
                    ];
                }

                // Log to audit trail
                if (!empty($changes)) {
                    \App\Services\AuditLogService::logAction(
                        'attendance_updated',
                        'attendance_logs',
                        $employee->id,
                        ['count' => count($changes), 'month' => $month, 'year' => $year],
                        null
                    );
                }

                // Recalculate after save
                $result = $this->payrollService->calculateForEmployee($employee, $month, $year);
                $this->payrollService->savePayrollItems($employee, $month, $year, $result);

                return redirect()
                    ->route('workspace.show', ['employee' => $employee->id, 'month' => $month, 'year' => $year])
                    ->with('success', 'บันทึกข้อมูลการเข้างานสำเร็จ');
            });
        } catch (\Throwable $e) {
            Log::error('saveAttendance error', [
                'employee_id' => $employee->id,
                'month' => $month,
                'year' => $year,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูลการเข้างาน: ' . $e->getMessage()]);
        }
    }

    public function saveWorkLogs(Request $request, Employee $employee, int $month, int $year)
    {
        try {
            return DB::transaction(function () use ($request, $employee, $month, $year) {
                $logs = $request->input('worklogs', []);

                // Backup existing logs before deletion (for audit purposes)
                $oldLogs = WorkLog::where('employee_id', $employee->id)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->get()
                    ->toArray();

                // Delete existing and recreate
                WorkLog::where('employee_id', $employee->id)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->delete();

                $newLogs = [];
                foreach ($logs as $index => $data) {
                    $durationMinutes = !empty($data['duration_hms'])
                        ? DurationInput::minutesFromHms($data['duration_hms'])
                        : (isset($data['duration_minutes']) && $data['duration_minutes'] !== ''
                        ? max((float) $data['duration_minutes'], 0)
                        : null);

                    if ($durationMinutes !== null) {
                        $hours = 0;
                        $minutes = (int) floor($durationMinutes);
                        $seconds = (int) round(($durationMinutes - $minutes) * 60);

                        if ($seconds === 60) {
                            $minutes++;
                            $seconds = 0;
                        }
                    } else {
                        $hours = (int) ($data['hours'] ?? 0);
                        $minutes = (int) ($data['minutes'] ?? 0);
                        $seconds = (int) ($data['seconds'] ?? 0);
                    }

                    if (empty($data['layer'])
                        && $durationMinutes === null
                        && empty($data['duration_hms'])
                        && empty($data['hours'])
                        && empty($data['minutes'])
                        && empty($data['quantity'])
                        && empty($data['custom_rate'])
                        && empty($data['pricing_template_label'])) {
                        continue;
                    }

                    $log = WorkLog::create([
                        'employee_id' => $employee->id,
                        'month' => $month,
                        'year' => $year,
                        'work_type' => $data['work_type'] ?? null,
                        'layer' => $data['layer'] ?? null,
                        'hours' => $hours,
                        'minutes' => $minutes,
                        'seconds' => $seconds,
                        'quantity' => (int) ($data['quantity'] ?? 0),
                        'rate' => (float) ($data['rate'] ?? 0),
                        'amount' => (float) ($data['amount'] ?? 0),
                        'pricing_mode' => $data['pricing_mode'] ?? 'template',
                        'custom_rate' => isset($data['custom_rate']) && $data['custom_rate'] !== '' ? (float) $data['custom_rate'] : null,
                        'pricing_template_label' => $data['pricing_template_label'] ?? null,
                        'sort_order' => $index + 1,
                        'notes' => $data['notes'] ?? null,
                        'entry_type' => $data['entry_type'] ?? 'income',
                        'is_disabled' => isset($data['is_disabled']),
                    ]);
                    $newLogs[] = $log->getAttributes();
                }

                // Log to audit trail
                \App\Services\AuditLogService::logAction(
                    'worklogs_updated',
                    'work_logs',
                    $employee->id,
                    ['count' => count($oldLogs), 'month' => $month, 'year' => $year],
                    ['count' => count($newLogs)]
                );

                // Recalculate + sync WorkLog row amounts
                $result = $this->payrollService->calculateForEmployee($employee, $month, $year);
                $this->payrollService->savePayrollItems($employee, $month, $year, $result);
                $this->payrollService->syncWorkLogAmounts($employee, $month, $year);

                return redirect()
                    ->route('workspace.show', ['employee' => $employee->id, 'month' => $month, 'year' => $year])
                    ->with('success', 'บันทึก Work Log สำเร็จ');
            });
        } catch (\Throwable $e) {
            Log::error('saveWorkLogs error', [
                'employee_id' => $employee->id,
                'month' => $month,
                'year' => $year,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการบันทึก Work Log: ' . $e->getMessage()]);
        }
    }

    public function storePerformanceRecord(Request $request, Employee $employee, int $month, int $year)
    {
        try {
            $validated = $request->validate([
                'record_date' => 'nullable|date',
                'finish_date' => 'nullable|date',
                'duration_mmss' => ['nullable', 'regex:/^\d{1,3}:\d{2}$/'],
                'work_assignment_id' => 'nullable|exists:work_assignments,id',
                'work_title' => 'nullable|string|max:255',
                'work_type_code' => 'nullable|exists:work_log_types,code',
                'video_title' => 'nullable|string|max:255',
                'layer' => 'nullable|integer|min:0|max:1000',
                'duration_minutes' => 'nullable|numeric|min:0',
                'quantity' => 'nullable|integer|min:0',
                'rate_snapshot' => 'nullable|numeric|min:0',
                'status' => 'required|in:action_select,in_process,finished,rejected',
                'quality_score' => 'nullable|numeric|min:1|max:5',
                'reject_reason' => 'nullable|string|max:1000',
                'notes' => 'nullable|string|max:1000',
            ]);

            $selectedAssignment = null;
            if (!empty($validated['work_assignment_id'])) {
                $selectedAssignment = WorkAssignment::with('workType')
                    ->where('id', $validated['work_assignment_id'])
                    ->where('employee_id', $employee->id)
                    ->first();
            }

            $selectedWorkType = $selectedAssignment?->workType;
            if (!empty($validated['work_type_code'])) {
                $selectedWorkType = WorkLogType::where('code', $validated['work_type_code'])->where('is_active', true)->first();
            }

            $workTitle = trim((string) ($validated['work_title'] ?? ''));
            if ($workTitle === '' && $selectedWorkType) {
                $workTitle = $selectedWorkType->name;
            }

            if ($workTitle === '') {
                return back()->withErrors(['work_title' => 'กรุณาเลือก Template หรือกรอกชื่องาน']);
            }

            $status = $validated['status'];
            $actionSelect = match ($status) {
                'finished' => 'confirm_finished',
                'rejected' => 'reject',
                default => null,
            };

            if ($status === 'finished') {
                if (empty($validated['quality_score'])) {
                    return back()->withErrors(['quality_score' => 'กรุณาให้คะแนนคุณภาพงาน (1-5)']);
                }

                if (empty($validated['duration_minutes']) && empty($validated['duration_mmss'])) {
                    return back()->withErrors(['duration_minutes' => 'กรุณากรอกระยะเวลางานเมื่อยืนยันว่าเสร็จ']);
                }
            }

            if ($status === 'rejected') {
                if (empty($validated['reject_reason'])) {
                    return back()->withErrors(['reject_reason' => 'กรุณาระบุเหตุผลการ Reject']);
                }
            }

            $parsedDurationMinutes = null;
            if (!empty($validated['duration_mmss'])) {
                [$mm, $ss] = array_map('intval', explode(':', $validated['duration_mmss']));
                $parsedDurationMinutes = $mm + ($ss / 60);
            }

            $durationMinutes = $parsedDurationMinutes ?? (isset($validated['duration_minutes'])
                ? max((float) $validated['duration_minutes'], 0)
                : (float) ($selectedWorkType?->target_length_minutes ?? 0));

            $hours = 0;
            $minutes = (int) floor($durationMinutes);
            $seconds = (int) round(($durationMinutes - $minutes) * 60);

            if ($seconds === 60) {
                $minutes++;
                $seconds = 0;
            }

            $rateSnapshot = isset($validated['rate_snapshot'])
                ? (float) $validated['rate_snapshot']
                : (float) ($selectedWorkType?->default_rate_per_minute ?? 0);
            $amountSnapshot = round($durationMinutes * $rateSnapshot, 2);

            $notes = trim((string) ($validated['notes'] ?? ''));
            if ($selectedWorkType && $selectedWorkType->footage_size) {
                $notes = trim($notes . ($notes !== '' ? ' | ' : '') . 'Template Footage: ' . $selectedWorkType->footage_size);
            }

            PerformanceRecord::create([
                'employee_id' => $employee->id,
                'work_assignment_id' => $selectedAssignment?->id,
                'record_date' => $validated['finish_date'] ?? $validated['record_date'] ?? now()->toDateString(),
                'month' => $month,
                'year' => $year,
                'work_title' => $workTitle,
                'video_title' => $validated['video_title'] ?? null,
                'layer' => $validated['layer'] ?? null,
                'hours' => $hours,
                'minutes' => $minutes,
                'seconds' => $seconds,
                'quantity' => (int) ($validated['quantity'] ?? 0),
                'rate_snapshot' => $rateSnapshot,
                'amount_snapshot' => $amountSnapshot,
                'status' => $status,
                'action_select' => $actionSelect,
                'quality_score' => $validated['quality_score'] ?? null,
                'reject_reason' => $validated['reject_reason'] ?? null,
                'confirmed_finished_at' => $status === 'finished' ? now() : null,
                'score' => 0,
                'category' => $selectedWorkType?->code ?? 'work_history',
                'notes' => $notes !== '' ? $notes : null,
                'source' => 'manual',
                'created_by' => auth()->id(),
            ]);

            if ($selectedAssignment) {
                $selectedAssignment->update([
                    'status' => $status,
                    'completed_at' => $status === 'finished' ? now() : null,
                    'notes' => $selectedAssignment->notes,
                ]);
            }

            \App\Services\AuditLogService::logAction(
                'performance_record_created',
                'performance_records',
                $employee->id,
                null,
                ['work_title' => $workTitle, 'status' => $status, 'month' => $month, 'year' => $year]
            );

            return redirect()
                ->route('workspace.show', ['employee' => $employee->id, 'month' => $month, 'year' => $year])
                ->with('success', 'บันทึกประวัติการทำงานสำเร็จ');
        } catch (\Throwable $e) {
            Log::error('storePerformanceRecord error', [
                'employee_id' => $employee->id,
                'month' => $month,
                'year' => $year,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการบันทึกประวัติการทำงาน: ' . $e->getMessage()]);
        }
    }

    public function deletePerformanceRecord(PerformanceRecord $record)
    {
        try {
            $employeeId = $record->employee_id;
            $month = $record->month;
            $year = $record->year;

            \App\Services\AuditLogService::logDeleted($record, 'Performance record deleted');

            $record->delete();

            return redirect()
                ->route('workspace.show', ['employee' => $employeeId, 'month' => $month, 'year' => $year])
                ->with('success', 'ลบประวัติการทำงานสำเร็จ');
        } catch (\Throwable $e) {
            Log::error('deletePerformanceRecord error', [
                'record_id' => $record->id,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการลบประวัติการทำงาน: ' . $e->getMessage()]);
        }
    }

    public function updatePayrollItem(Request $request, Employee $employee, int $month, int $year)
    {
        try {
            return DB::transaction(function () use ($request, $employee, $month, $year) {
                $validated = $request->validate([
                    'item_id' => 'required|exists:payroll_items,id',
                    'amount' => 'required|numeric|min:0',
                ]);

                $item = PayrollItem::findOrFail($validated['item_id']);
                $oldAmount = $item->amount;
                $oldSourceFlag = $item->source_flag;

                $item->update([
                    'amount' => $validated['amount'],
                    'source_flag' => 'override',
                ]);

                \App\Services\AuditLogService::log(
                    $item,
                    'overridden',
                    'amount',
                    $oldAmount,
                    $validated['amount'],
                    'source_flag changed from ' . $oldSourceFlag . ' to override'
                );

                return redirect()
                    ->route('workspace.show', ['employee' => $employee->id, 'month' => $month, 'year' => $year])
                    ->with('success', 'อัพเดทรายการสำเร็จ');
            });
        } catch (\Throwable $e) {
            Log::error('updatePayrollItem error', [
                'employee_id' => $employee->id,
                'month' => $month,
                'year' => $year,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการอัพเดทรายการ: ' . $e->getMessage()]);
        }
    }

    protected function ensureAttendanceLogs(Employee $employee, int $month, int $year): void
    {
        $startDate = Carbon::create($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();

        // Load Existing Logs for the month
        $existingLogs = AttendanceLog::where('employee_id', $employee->id)
            ->whereMonth('log_date', $month)
            ->whereYear('log_date', $year)
            ->get()
            ->keyBy(function($log) {
                return \Carbon\Carbon::parse($log->log_date)->format('Y-m-d');
            });

        // Load Company Holidays
        $companyHolidays = \App\Models\CompanyHoliday::where('is_active', true)
            ->get()
            ->pluck('holiday_date')
            ->map(fn($d) => $d->format('Y-m-d'))
            ->toArray();

        $empStartDate = $employee->start_date ? Carbon::parse($employee->start_date) : null;

        $date = $startDate->copy();
        while ($date <= $endDate) {
            $dateStr = $date->format('Y-m-d');
            $existingLog = $existingLogs->get($dateStr);

            $dayType = 'workday';
            if ($empStartDate && $date < $empStartDate) {
                $dayType = 'not_started';
            } elseif (in_array($dateStr, $companyHolidays)) {
                $dayType = 'company_holiday';
            } elseif ($date->isWeekend()) {
                $dayType = 'holiday';
            }

            if (!$existingLog) {
                AttendanceLog::create([
                    'employee_id' => $employee->id,
                    'log_date' => $dateStr,
                    'day_type' => $dayType,
                ]);
            } else {
                // Auto-Sync: If it's currently a regular workday or holiday, but it SHOULD be a company_holiday or not_started
                if (
                    !$existingLog->is_swapped_day
                    && in_array($existingLog->day_type, ['workday', 'holiday'])
                    && $existingLog->day_type !== $dayType
                ) {
                    $existingLog->update(['day_type' => $dayType]);
                }
            }
            $date->addDay();
        }
    }

}
