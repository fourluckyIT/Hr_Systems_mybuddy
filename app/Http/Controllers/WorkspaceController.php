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
use App\Models\EditingJob;
use App\Models\Payslip;
use App\Models\PaymentProof;
use App\Models\ExpenseClaim;
use App\Models\ModuleToggle;
use App\Models\CompanyHoliday;
use App\Services\Payroll\PayrollCalculationService;
use App\Services\AuditLogService;
use App\Support\DurationInput;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class WorkspaceController extends Controller
{
    public function __construct(
        protected PayrollCalculationService $payrollService
    ) {}

    protected function isWorkspaceEditingEnabled(Employee $employee): bool
    {
        $toggle = $employee->moduleToggles()
            ->where('module_name', 'workspace_editing')
            ->first();

        // Default to enabled so existing users keep current behavior.
        return $toggle ? (bool) $toggle->is_enabled : true;
    }

    protected function ensureWorkspaceEditingEnabled(Employee $employee): void
    {
        if (!$this->isWorkspaceEditingEnabled($employee)) {
            throw new \RuntimeException('สิทธิ์แก้ไข Workspace ถูกปิดจาก Work Center');
        }
    }

    protected function ensureCanAccessEmployeeWorkspace(Employee $employee): void
    {
        $user = Auth::user();
        $isEmployeeOnly = $user
            && $user->hasRole('owner')
            && !$user->hasRole('admin');

        if ($isEmployeeOnly && (int) ($user->employee?->id) !== (int) $employee->id) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงข้อมูลของพนักงานคนอื่น');
        }
    }

    public function myWorkspace(?int $month = null, ?int $year = null)
    {
        $user = Auth::user();
        $employee = $user?->employee;

        if (!$employee) {
            return redirect()->route('employees.index')
                ->withErrors(['error' => 'ไม่พบข้อมูลพนักงานที่ผูกกับบัญชีผู้ใช้นี้']);
        }

        return redirect()->route('workspace.show', [
            'employee' => $employee->id,
            'month' => $month ?: now()->month,
            'year' => $year ?: now()->year,
        ]);
    }

    public function show(Employee $employee, int $month, int $year)
    {
        $this->ensureCanAccessEmployeeWorkspace($employee);
        $data = $this->getWorkspaceViewData($employee, $month, $year);
        
        return view('workspace.show', array_merge(['employee' => $employee, 'month' => $month, 'year' => $year], $data));
    }

    public function storeClaim(Request $request, Employee $employee, int $month, int $year)
    {
        try {
            $this->ensureCanAccessEmployeeWorkspace($employee);
            $this->ensureWorkspaceEditingEnabled($employee);

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

                $currentAdvances = ExpenseClaim::where('employee_id', $employee->id)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->where('type', 'advance')
                    ->sum('amount');

                if (($currentAdvances + $validated['amount']) > $limit) {
                    return back()->withErrors(['amount' => "ยอดเบิกเงินล่วงหน้าเกินเพดานที่กำหนดไว้ (" . number_format($limit, 2) . " บาท)"]);
                }
            }

            $claim = ExpenseClaim::create([
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
            $this->ensureWorkspaceEditingEnabled($employee);

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

    public function toggleWorkLog(WorkLog $workLog)
    {
        $this->ensureWorkspaceEditingEnabled($workLog->employee);

        $workLog->update(['is_disabled' => !$workLog->is_disabled]);
        return back();
    }

    public function approveClaim(ExpenseClaim $claim)
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

    public function deleteClaim(ExpenseClaim $claim)
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

            $payslip = Payslip::where('employee_id', $employee->id)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            $path = $request->file('proof')->store('proofs', 'public');

            if (!$path) {
                throw new \Exception('ความล้มเหลวในการจัดเก็บไฟล์');
            }

            $proof = PaymentProof::create([
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

                $toggle = ModuleToggle::firstOrNew([
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

    public function saveFreelanceLayerRates(Request $request, Employee $employee, int $month, int $year)
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);
        if ($employee->payroll_mode !== 'freelance_layer') {
            return back()->withErrors(['error' => 'ใช้ได้เฉพาะโหมด Freelance']);
        }

        $validated = $request->validate([
            'rates' => 'array',
            'rates.*.id' => 'nullable|integer',
            'rates.*.layer_from' => 'required|integer|min:1',
            'rates.*.layer_to' => 'required|integer|gte:rates.*.layer_from',
            'rates.*.rate_per_minute' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated, $employee, $month, $year) {
            $keepIds = [];
            foreach ($validated['rates'] ?? [] as $row) {
                $rule = LayerRateRule::updateOrCreate(
                    ['id' => $row['id'] ?? null, 'employee_id' => $employee->id],
                    [
                        'employee_id' => $employee->id,
                        'layer_from' => $row['layer_from'],
                        'layer_to' => $row['layer_to'],
                        'rate_per_minute' => $row['rate_per_minute'],
                        'effective_date' => now()->toDateString(),
                        'is_active' => true,
                    ]
                );
                $keepIds[] = $rule->id;
            }
            LayerRateRule::where('employee_id', $employee->id)
                ->whereNotIn('id', $keepIds ?: [0])
                ->delete();

            $result = $this->payrollService->calculateForEmployee($employee, $month, $year);
            $this->payrollService->savePayrollItems($employee, $month, $year, $result);
            $this->payrollService->syncWorkLogAmounts($employee, $month, $year);

            return back()->with('success', 'บันทึก Price/min + คำนวณใหม่สำเร็จ');
        });
    }

    public function updateWorkLogRate(Request $request, WorkLog $workLog)
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $validated = $request->validate([
            'rate' => 'nullable|numeric|min:0',
            'amount' => 'nullable|numeric|min:0',
            'pricing_mode' => 'nullable|in:layer,custom',
            'layer' => 'nullable|integer|min:1',
        ]);

        $updates = [];
        if (array_key_exists('pricing_mode', $validated)) {
            $updates['pricing_mode'] = $validated['pricing_mode'] ?? 'layer';
        }

        if (array_key_exists('layer', $validated) && $validated['layer'] !== null) {
            $updates['layer'] = $validated['layer'];
        }

        if (array_key_exists('rate', $validated) && $validated['rate'] !== null) {
            $updates['rate'] = $validated['rate'];
            if (($updates['pricing_mode'] ?? $workLog->pricing_mode) === 'custom') {
                $updates['custom_rate'] = $validated['rate'];
            } else {
                $updates['custom_rate'] = null; // Reset custom rate if switched to layer
            }
        }
        
        if (array_key_exists('amount', $validated) && $validated['amount'] !== null) {
            $updates['amount'] = $validated['amount'];
        }
        
        if ($updates) {
            $workLog->update($updates);

            // Re-sync amounts automatically if mode or layer changed
            if (isset($updates['layer']) || isset($updates['pricing_mode'])) {
                app(\App\Services\Payroll\PayrollCalculationService::class)->syncWorkLogAmounts($workLog->employee, $workLog->month, $workLog->year);
            }
        }

        return back()->with('success', 'อัปเดตเรท/ยอดของ row สำเร็จ');
    }

    public function recalculate(Employee $employee, int $month, int $year)
    {
        try {
            return DB::transaction(function () use ($employee, $month, $year) {
                $this->syncAttendanceDerivedMetrics($employee, $month, $year);
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
            $this->ensureWorkspaceEditingEnabled($employee);

            return DB::transaction(function () use ($request, $employee, $month, $year) {
                $logs = $request->input('attendance', []);
                $changes = [];
                $workingHoursRule = AttendanceRule::getActiveRule('working_hours');
                $targetCheckIn = $workingHoursRule?->config['target_check_in'] ?? '09:30';
                $targetCheckOut = $workingHoursRule?->config['target_check_out'] ?? '18:30';
                $targetMinutesPerDay = (int) ($workingHoursRule?->config['target_minutes_per_day'] ?? 540);
                $lunchBreakMinutes = (int) ($workingHoursRule?->config['lunch_break_minutes'] ?? 60);

                foreach ($logs as $logId => $data) {
                    $log = AttendanceLog::findOrFail($logId);
                    $oldData = $log->getAttributes();
                    $oldDayType = $log->day_type;

                    $dayType = $data['day_type'] ?? 'workday';
                    $this->validateSwapPolicy($log, $dayType);

                    $isWorkday = in_array($dayType, ['workday', 'ot_full_day'], true);
                    $isHolidayOvertimeDay = in_array($dayType, ['holiday', 'company_holiday'], true);
                    $isTimeEntryDay = $isWorkday || $isHolidayOvertimeDay;
                    $isLwop = $dayType === 'lwop' || isset($data['lwop_flag']);

                    $isLwop = $dayType === 'lwop' || isset($data['lwop_flag']);
                    $checkIn = $data['check_in'] ?? null;
                    $checkOut = $data['check_out'] ?? null;

                    if ($isTimeEntryDay && !$isLwop && $checkIn && $checkOut) {
                        $derived = $this->calculateAttendanceDerivedValues($log, [
                            'target_check_in' => $targetCheckIn,
                            'target_check_out' => $targetCheckOut,
                            'target_minutes_per_day' => $targetMinutesPerDay,
                            'lunch_break_minutes' => $lunchBreakMinutes,
                        ], $data);
                        
                        $lateMinutes = $derived['late_minutes'];
                        $earlyLeaveMinutes = $derived['early_leave_minutes'];
                        $otMinutes = $derived['ot_minutes'];
                    } else {
                        $checkIn = null;
                        $checkOut = null;
                        $lateMinutes = (int) ($data['late_minutes'] ?? 0);
                        $otMinutes = !empty($data['ot_enabled']) ? (int) ($data['ot_minutes'] ?? 0) : 0;
                        $earlyLeaveMinutes = 0;
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

                    $otEnabledNew = isset($data['ot_enabled']);
                    $currentOtStatus = $log->ot_status ?? 'none';
                    if (in_array($currentOtStatus, ['requested', 'approved'], true)) {
                        $newOtStatus = $currentOtStatus;
                    } elseif ($otEnabledNew) {
                        $newOtStatus = 'admin_set';
                    } else {
                        $newOtStatus = 'none';
                    }

                    $log->update([
                        'day_type' => $dayType,
                        'check_in' => $checkIn ?: null,
                        'check_out' => $checkOut ?: null,
                        'late_minutes' => $lateMinutes,
                        'early_leave_minutes' => $earlyLeaveMinutes,
                        'ot_minutes' => $otMinutes,
                        'ot_enabled' => $otEnabledNew,
                        'ot_status' => $newOtStatus,
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

                if ($request->wantsJson()) {
                    return response()->json([
                        'ok' => true,
                        'summary' => $result['summary'],
                        'items' => $result['items']
                    ]);
                }

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

    public function saveAttendanceRow(Request $request, Employee $employee, int $month, int $year)
    {
        try {
            $this->ensureWorkspaceEditingEnabled($employee);

            return DB::transaction(function () use ($request, $employee, $month, $year) {
                $logId = $request->input('log_id');
                $data = $request->input('data', []);

                $log = AttendanceLog::where('id', $logId)
                    ->where('employee_id', $employee->id)
                    ->firstOrFail();

                $workingHoursRule = AttendanceRule::getActiveRule('working_hours');
                $meta = [
                    'target_check_in' => $workingHoursRule?->config['target_check_in'] ?? '09:30',
                    'target_check_out' => $workingHoursRule?->config['target_check_out'] ?? '18:30',
                    'target_minutes_per_day' => (int) ($workingHoursRule?->config['target_minutes_per_day'] ?? 540),
                    'lunch_break_minutes' => (int) ($workingHoursRule?->config['lunch_break_minutes'] ?? 60),
                ];

                $oldData = $log->getAttributes();
                $oldDayType = $log->day_type;
                $dayType = $data['day_type'] ?? $log->day_type;
                $this->validateSwapPolicy($log, $dayType);

                $checkIn = $data['check_in'] ?? null;
                $checkOut = $data['check_out'] ?? null;
                $isLwop = ($dayType === 'lwop' || !empty($data['lwop_flag']));

                if ($checkIn && $checkOut && !$isLwop) {
                    $derived = $this->calculateAttendanceDerivedValues($log, $meta, $data);
                    $lateMinutes = $derived['late_minutes'];
                    $earlyLeaveMinutes = $derived['early_leave_minutes'];
                    $otMinutes = $derived['ot_minutes'];
                } else {
                    $checkIn = null;
                    $checkOut = null;
                    $lateMinutes = (int) ($data['late_minutes'] ?? 0);
                    $otMinutes = !empty($data['ot_enabled']) ? (int) ($data['ot_minutes'] ?? 0) : 0;
                    $earlyLeaveMinutes = 0;
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

                $otEnabledNew = !empty($data['ot_enabled']);
                $currentOtStatus = $log->ot_status ?? 'none';

                // Preserve employee-driven states; admin checkbox → admin_set
                if (in_array($currentOtStatus, ['requested', 'approved'], true)) {
                    $newOtStatus = $currentOtStatus;
                } elseif ($otEnabledNew) {
                    $newOtStatus = 'admin_set';
                } else {
                    $newOtStatus = 'none';
                }

                $log->update([
                    'day_type' => $dayType,
                    'check_in' => $checkIn ?: null,
                    'check_out' => $checkOut ?: null,
                    'late_minutes' => $lateMinutes,
                    'early_leave_minutes' => $earlyLeaveMinutes,
                    'ot_minutes' => $otMinutes,
                    'ot_enabled' => $otEnabledNew,
                    'ot_status' => $newOtStatus,
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

                \App\Services\AuditLogService::logAction(
                    'attendance_row_updated',
                    'attendance_logs',
                    $log->id,
                    ['employee_id' => $employee->id, 'month' => $month, 'year' => $year],
                    null
                );

                // Recalculate payroll
                $result = $this->payrollService->calculateForEmployee($employee, $month, $year);
                $this->payrollService->savePayrollItems($employee, $month, $year, $result);

                $log->refresh();

                return response()->json([
                    'ok' => true,
                    'row' => [
                        'log_id' => $log->id,
                        'late_minutes' => $log->late_minutes,
                        'ot_minutes' => $log->ot_minutes,
                        'day_type' => $log->day_type,
                    ],
                    'summary' => $result['summary'],
                    'items' => $result['items'],
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('saveAttendanceRow error', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function saveWorkLogs(Request $request, Employee $employee, int $month, int $year)
    {
        try {
            $this->ensureWorkspaceEditingEnabled($employee);

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
                        : (int) ($data['duration_minutes'] ?? 0);

                    $log = WorkLog::create([
                        'employee_id' => $employee->id,
                        'month' => $month,
                        'year' => $year,
                        'log_date' => $data['log_date'] ?? null,
                        // 'work_log_type_id' => $data['work_log_type_id'] ?? null,
                        'editing_job_id' => $data['editing_job_id'] ?? null,
                        'description' => $data['description'] ?? '',
                        'quantity' => $data['quantity'] ?? 1,
                        'duration_minutes' => $durationMinutes,
                        'rate' => $data['rate'] ?? 0,
                        'amount' => $data['amount'] ?? 0,
                        'pricing_mode' => $data['pricing_mode'] ?? 'fixed',
                        'sort_order' => $index,
                        'status' => 'confirmed',
                    ]);
                    $newLogs[] = $log;
                }

                \App\Services\AuditLogService::logAction(
                    'work_logs_updated',
                    'work_logs',
                    $employee->id,
                    ['count' => count($newLogs), 'month' => $month, 'year' => $year],
                    null
                );

                // Recalculate after save
                $result = $this->payrollService->calculateForEmployee($employee, $month, $year);
                $this->payrollService->savePayrollItems($employee, $month, $year, $result);

                return redirect()
                    ->route('workspace.show', ['employee' => $employee->id, 'month' => $month, 'year' => $year])
                    ->with('success', 'บันทึกข้อมูลงานสำเร็จ');
            });
        } catch (\Throwable $e) {
            Log::error('saveWorkLogs error', [
                'employee_id' => $employee->id,
                'month' => $month,
                'year' => $year,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูลงาน: ' . $e->getMessage()]);
        }
    }

    protected function ensureAttendanceLogs(Employee $employee, int $month, int $year)
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $startDateString = $startDate->toDateString();
        $endDateString = $endDate->toDateString();

        $existingDates = AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('log_date', [$startDateString, $endDateString])
            ->pluck('log_date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $holidays = CompanyHoliday::where('is_active', true)
            ->whereBetween('holiday_date', [$startDateString, $endDateString])
            ->pluck('holiday_date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $workingHoursRule = AttendanceRule::getActiveRule('working_hours');
        $newLogs = [];
        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            if (!in_array($dateStr, $existingDates)) {
                $standardHolidays = $workingHoursRule?->config['standard_holidays'] ?? [0, 6];
                $isWeekend = in_array($date->dayOfWeek, $standardHolidays);
                $isHoliday = in_array($dateStr, $holidays);

                $dayType = 'workday';
                if ($isHoliday) $dayType = 'company_holiday';
                elseif ($isWeekend) $dayType = 'holiday';

                $newLogs[] = [
                    'employee_id' => $employee->id,
                    'log_date' => $dateStr,
                    'day_type' => $dayType,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($newLogs)) {
            AttendanceLog::query()->insertOrIgnore($newLogs);
        }
    }

    protected function getAttendanceRuleMeta(): array
    {
        $workingHoursRule = AttendanceRule::getActiveRule('working_hours');

        return [
            'target_check_in' => $workingHoursRule?->config['target_check_in'] ?? '09:30',
            'target_check_out' => $workingHoursRule?->config['target_check_out'] ?? '18:30',
            'target_minutes_per_day' => (int) ($workingHoursRule?->config['target_minutes_per_day'] ?? 540),
            'lunch_break_minutes' => (int) ($workingHoursRule?->config['lunch_break_minutes'] ?? 60),
        ];
    }

    protected function calculateAttendanceDerivedValues(AttendanceLog $log, array $meta, array $inputs = []): array
    {
        $dayType = (string) ($inputs['day_type'] ?? $log->day_type);
        $otEnabled = isset($inputs['ot_enabled']) ? (bool)$inputs['ot_enabled'] : (bool)$log->ot_enabled;
        
        $isWorkday = in_array($dayType, ['workday', 'ot_full_day'], true);
        $isHolidayOvertimeDay = in_array($dayType, ['holiday', 'company_holiday'], true);
        $isTimeEntryDay = $isWorkday || $isHolidayOvertimeDay;
        
        // Check for LWOP in inputs OR model
        $isLwop = $dayType === 'lwop' || ($inputs['lwop_flag'] ?? $log->lwop_flag);

        $checkIn = $inputs['check_in'] ?? $log->check_in;
        $checkOut = $inputs['check_out'] ?? $log->check_out;

        $lateMinutes = 0;
        $earlyLeaveMinutes = 0;
        $otMinutes = 0;

        if (!$isTimeEntryDay || $isLwop || !$checkIn || !$checkOut) {
            return [
                'late_minutes' => $lateMinutes,
                'early_leave_minutes' => $earlyLeaveMinutes,
                'ot_minutes' => $otMinutes,
            ];
        }

        $inAt = Carbon::parse("{$log->log_date->format('Y-m-d')} {$checkIn}:00");
        $outAt = Carbon::parse("{$log->log_date->format('Y-m-d')} {$checkOut}:00");

        if ($outAt->lessThanOrEqualTo($inAt)) {
            $outAt->addDay();
        }

        $targetInAt = Carbon::parse("{$log->log_date->format('Y-m-d')} {$meta['target_check_in']}:00");
        $targetOutAt = Carbon::parse("{$log->log_date->format('Y-m-d')} {$meta['target_check_out']}:00");

        if ($isWorkday) {
            // Late: Based on Target Start
            if ($inAt->greaterThan($targetInAt)) {
                $lateMinutes = $targetInAt->diffInMinutes($inAt);
            }

            // Early Leave: Based on Target End
            if ($outAt->lessThan($targetOutAt)) {
                $earlyLeaveMinutes = $outAt->diffInMinutes($targetOutAt);
            }
        }

        // OT Calculation (clock-based, unified workday + holiday)
        // OT = minutes past standard checkout time
        if ($otEnabled) {
            $otMinutes = $outAt->greaterThan($targetOutAt)
                ? (int) $targetOutAt->diffInMinutes($outAt)
                : 0;
        }

        return [
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'ot_minutes' => $otMinutes,
        ];
    }

    protected function syncAttendanceDerivedMetrics(Employee $employee, int $month, int $year): void
    {
        $meta = $this->getAttendanceRuleMeta();

        AttendanceLog::where('employee_id', $employee->id)
            ->whereMonth('log_date', $month)
            ->whereYear('log_date', $year)
            ->get()
            ->each(function (AttendanceLog $log) use ($meta) {
                $derived = $this->calculateAttendanceDerivedValues($log, $meta);

                $updates = [];
                foreach ($derived as $field => $value) {
                    if ((int) $log->{$field} !== (int) $value) {
                        $updates[$field] = $value;
                    }
                }

                if (!empty($updates)) {
                    $log->update($updates);
                }
            });
    }

    protected function validateSwapPolicy(AttendanceLog $log, string $newDayType): void
    {
        $oldDayType = (string) $log->day_type;
        $isSwapToWorkday = $newDayType === 'workday' && in_array($oldDayType, ['holiday', 'company_holiday'], true);

        if (!$isSwapToWorkday) {
            return;
        }

        if ($oldDayType === 'company_holiday' && !$this->allowCompanyHolidaySwap()) {
            throw new \RuntimeException('ไม่สามารถสลับวันหยุดตามประเพณีเป็นวันทำงานได้: เปิดสิทธิ์เฉพาะกิจการที่กฎหมายยกเว้นในหน้า Rules ก่อน');
        }

        if ($this->wouldExceedSixConsecutiveWorkdays($log, $newDayType)) {
            throw new \RuntimeException('ไม่สามารถสลับวันได้: จะทำให้ทำงานติดต่อกันเกิน 6 วันโดยไม่มีวันหยุด');
        }
    }

    protected function allowCompanyHolidaySwap(): bool
    {
        $workingHoursRule = AttendanceRule::getActiveRule('working_hours');
        return (bool) ($workingHoursRule?->config['allow_company_holiday_swap'] ?? false);
    }

    protected function wouldExceedSixConsecutiveWorkdays(AttendanceLog $targetLog, string $overrideDayType): bool
    {
        $isWorkday = static fn(?string $dayType): bool => in_array((string) $dayType, ['workday', 'ot_full_day'], true);

        $targetDate = Carbon::parse($targetLog->log_date)->startOfDay();
        $start = $targetDate->copy()->subDays(14);
        $end = $targetDate->copy()->addDays(14);

        $logs = AttendanceLog::where('employee_id', $targetLog->employee_id)
            ->whereBetween('log_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn(AttendanceLog $log) => Carbon::parse($log->log_date)->toDateString());

        $streak = 1;

        for ($i = 1; $i <= 14; $i++) {
            $date = $targetDate->copy()->subDays($i)->toDateString();
            $dayType = $logs[$date]->day_type ?? null;
            if ($isWorkday($dayType)) {
                $streak++;
            } else {
                break;
            }
        }

        for ($i = 1; $i <= 14; $i++) {
            $date = $targetDate->copy()->addDays($i)->toDateString();
            $dayType = $logs[$date]->day_type ?? null;
            if ($isWorkday($dayType)) {
                $streak++;
            } else {
                break;
            }
        }

        return $isWorkday($overrideDayType) && $streak > 6;
    }

    /**
     * AJAX endpoint to get only the attendance grid partial.
     */
    public function getGridRefresh(Employee $employee, int $month, int $year)
    {
        $this->ensureCanAccessEmployeeWorkspace($employee);
        $data = $this->getWorkspaceViewData($employee, $month, $year);

        $viewFile = match($employee->payroll_mode) {
            'monthly_staff', 'office_staff' => 'workspace.partials.attendance-grid',
            'youtuber_salary' => 'workspace.partials.youtuber-recording-sessions',
            'freelance_layer' => 'workspace.partials.freelance-layer-grid',
            'youtuber_settlement' => 'workspace.partials.youtuber-settlement-grid',
            default => null
        };

        if (!$viewFile) return response()->json(['error' => 'No grid for this mode'], 400);

        $html = view($viewFile, array_merge(['employee' => $employee, 'month' => $month, 'year' => $year], $data))->render();

        return response()->json([
            'html' => $html,
            'summary' => $data['result']['summary'] ?? [],
            'items' => $data['result']['items'] ?? [],
            'vacationBalance' => $data['vacationBalance'] ?? null
        ]);
    }

    protected function getWorkspaceViewData(Employee $employee, int $month, int $year)
    {
        $employee->load(['department', 'position', 'salaryProfile', 'bankAccount', 'profile']);

        $dayTypeLabels = [
            'workday' => 'วันทำงาน', 'holiday' => 'วันหยุด', 'sick_leave' => 'ลาป่วย',
            'personal_leave' => 'ลากิจ', 'vacation_leave' => 'ลาพักร้อน', 
            'ot_full_day' => 'OT เต็มวัน', 'lwop' => 'LWOP',
            'not_started' => 'ยังไม่เริ่มงาน', 'company_holiday' => 'วันหยุดบริษัท',
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

        if (in_array($employee->payroll_mode, ['monthly_staff', 'office_staff'])) {
            $this->ensureAttendanceLogs($employee, $month, $year);
            $this->syncAttendanceDerivedMetrics($employee, $month, $year);
        }

        $attendanceLogs = AttendanceLog::where('employee_id', $employee->id)
            ->whereMonth('log_date', $month)->whereYear('log_date', $year)
            ->orderBy('log_date')->get();

        $isCurrentMonth = ($month === (int) now()->month && $year === (int) now()->year);
        $isAdmin = auth()->user()?->hasRole('admin') ?? false;
        $attendanceReadOnly = !$isAdmin && $isCurrentMonth;

        $workLogs = WorkLog::where('employee_id', $employee->id)
            ->where('month', $month)->where('year', $year)
            ->orderBy('sort_order')->get();

        $layerRates = LayerRateRule::where('employee_id', $employee->id)->where('is_active', true)->get();

        $workingHoursRule = AttendanceRule::getActiveRule('working_hours');
        $attendanceMeta = [
            'target_check_in' => $workingHoursRule?->config['target_check_in'] ?? '09:30',
            'target_check_out' => $workingHoursRule?->config['target_check_out'] ?? '18:30',
            'target_minutes_per_day' => (int) ($workingHoursRule?->config['target_minutes_per_day'] ?? 540),
            'lunch_break_minutes' => (int) ($workingHoursRule?->config['lunch_break_minutes'] ?? 60),
        ];

        $result = $this->payrollService->calculateForEmployee($employee, $month, $year);
        $batch = PayrollBatch::where('month', $month)->where('year', $year)->first();
        $payrollItems = $batch ? PayrollItem::where('employee_id', $employee->id)->where('payroll_batch_id', $batch->id)->get() : collect();
        $payslip = Payslip::where('employee_id', $employee->id)->where('month', $month)->where('year', $year)->first();
        $proofs = PaymentProof::where('employee_id', $employee->id)->whereHas('payslip', function($q) use ($month, $year) {
            $q->where('month', $month)->where('year', $year);
        })->orWhere(function($q) use ($employee, $month, $year) {
            $q->where('employee_id', $employee->id)->whereNull('payslip_id')->whereMonth('created_at', $month)->whereYear('created_at', $year);
        })->get();

        $claims = ExpenseClaim::where('employee_id', $employee->id)->where('month', $month)->where('year', $year)->get();
        $isYoutuber = in_array($employee->payroll_mode, ['youtuber_salary', 'youtuber_settlement']);
        $assignedEditJobs = collect();
        $performanceSummary = [
            'total_duration_hms' => '00:00:00',
            'final_count' => 0
        ];
        $panel = 'none';

        if (!$isYoutuber) {
            $hasEditAssignments = EditingJob::where('assigned_to', $employee->id)->active()->exists();
            if ($employee->payroll_mode === 'freelance_layer') {
                $panel = $hasEditAssignments ? 'edit_jobs' : 'none';
            } else {
                $panel = 'edit_jobs'; // Default for monthly/office editors
            }
            
            $assignedEditJobs = $hasEditAssignments ? EditingJob::with('game')->where('assigned_to', $employee->id)->active()
                ->orderByRaw("CASE status WHEN 'assigned' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'review_ready' THEN 3 WHEN 'final' THEN 4 ELSE 99 END")
                ->orderBy('deadline_date')->get() : collect();

            $monthlyFinalJobs = EditingJob::where('assigned_to', $employee->id)->where('status', 'final')
                ->whereMonth('finalized_at', $month)->whereYear('finalized_at', $year)->get();

            $totalMonthlySeconds = $monthlyFinalJobs->sum(fn($j) => ($j->video_duration_minutes * 60) + $j->video_duration_seconds);
            $performanceSummary = [
                'total_duration_hms' => DurationInput::formatSecondsAsHms($totalMonthlySeconds),
                'final_count' => $monthlyFinalJobs->count()
            ];
        } else {
            // It's a Youtuber - They don't see editing pipeline anymore
            $panel = 'none';
            $assignedEditJobs = collect();
            
            // Calculate YTD Income (Finalized Payslips only)
            $ytdIncome = \App\Models\Payslip::where('employee_id', $employee->id)
                ->where('year', $year)
                ->where('status', 'finalized')
                ->sum('net_pay');

            $performanceSummary = [
                'ytd_income' => $ytdIncome,
                'revenue_label' => 'รายได้สะสมปีนี้ (YTD)',
                'is_youtuber' => true
            ];
        }

        $recordingAssignments = collect();

        // Recording sessions for YouTubers — show sessions they participated in this month
        $recordingSessions = collect();
        if ($employee->payroll_mode === 'youtuber_salary') {
            $recordingSessions = \App\Models\RecordingSession::with(['game', 'youtubers'])
                ->whereHas('youtubers', fn($q) => $q->where('employees.id', $employee->id))
                ->whereMonth('session_date', $month)
                ->whereYear('session_date', $year)
                ->orderByDesc('session_date')
                ->get();
        }

        $workspaceEditEnabled = $this->isWorkspaceEditingEnabled($employee);
        $vacationBalance = $employee->getVacationBalance($year);

        return [
            'attendanceLogs' => $attendanceLogs, 'workLogs' => $workLogs, 'layerRates' => $layerRates,
            'result' => $result, 'payrollItems' => $payrollItems, 'payslip' => $payslip,
            'proofs' => $proofs, 'claims' => $claims, 'dayTypeLabels' => $dayTypeLabels,
            'dayTypeColors' => $dayTypeColors, 'attendanceMeta' => $attendanceMeta,
            'attendanceReadOnly' => $attendanceReadOnly, 'isAdmin' => $isAdmin,
            'assignedEditJobs' => $assignedEditJobs, 'recordingAssignments' => $recordingAssignments,
            'recordingSessions' => $recordingSessions,
            'panel' => $panel, 'workspaceEditEnabled' => $workspaceEditEnabled, 'performanceSummary' => $performanceSummary,
            'vacationBalance' => $vacationBalance
        ];
    }
}
