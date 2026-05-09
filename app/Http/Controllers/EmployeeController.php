<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Department;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Models\EmployeeProfile;
use App\Models\EmployeeSalaryProfile;
use App\Models\EmployeeBankAccount;
use App\Models\AttendanceRule;
use App\Models\ModuleToggle;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $showInactive = $request->boolean('show_inactive', false);
        $sortBy = $request->get('sort_by', 'first_name');
        $sortDir = $request->get('sort_dir', 'asc');
        $groupBy = $request->get('group_by', 'none');

        $query = Employee::with(['department', 'position', 'salaryProfile', 'payslips'])
            ->select('employees.*');

        // Joins for sorting if needed
        if ($sortBy === 'department') {
            $query->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
                  ->orderBy('departments.name', $sortDir);
        } elseif ($sortBy === 'salary') {
            $query->leftJoin('employee_salary_profiles', function($join) {
                $join->on('employees.id', '=', 'employee_salary_profiles.employee_id')
                     ->where('employee_salary_profiles.is_current', true);
            })->orderBy('employee_salary_profiles.base_salary', $sortDir);
        } elseif ($sortBy === 'employee_code') {
            $query->orderBy('employee_code', $sortDir);
        } elseif ($sortBy === 'payroll_mode') {
            $query->orderBy('payroll_mode', $sortDir);
        } else {
            // Default sort: first_name then last_name
            $query->orderBy('first_name', $sortDir)->orderBy('last_name', $sortDir);
        }

        if (!$showInactive) {
            $query->where('is_active', true);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('nickname', 'like', "%{$search}%")
                  ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }

        if ($mode = $request->get('payroll_mode')) {
            $query->where('payroll_mode', $mode);
        }

        if ($dept = $request->get('department_id')) {
            $query->where('department_id', $dept);
        }

        $employees = $query->get();
        $departments = Department::where('is_active', true)->get();
        $positions = Position::where('is_active', true)->with('department')->get();
        $roles = Role::orderBy('name')->get();

        return view('employees.index', compact(
            'employees', 'departments', 'positions', 'roles', 'showInactive',
            'sortBy', 'sortDir', 'groupBy'
        ));
    }

    /**
     * Pass probation — set probation_end_date to a specified date (default = yesterday) and ensure status=active.
     * Default = yesterday so the employee disappears from the watchlist immediately.
     */
    public function passProbation(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'passed_date' => 'nullable|date|before_or_equal:today',
            'reason' => 'nullable|string|max:1000',
        ]);

        if (!$employee->probation_end_date) {
            return back()->withErrors(['probation' => 'พนักงานคนนี้ไม่มีกำหนดวันสิ้นสุดทดลองงาน']);
        }

        $oldEnd = $employee->probation_end_date?->toDateString();
        $passedDate = $validated['passed_date'] ?? now()->subDay()->toDateString();

        $employee->update([
            'probation_end_date' => $passedDate,
            'status' => 'active',
            'is_active' => true,
        ]);

        AuditLogService::log(
            $employee,
            'probation_passed',
            'probation_end_date',
            $oldEnd,
            $passedDate,
            'พนักงานผ่านทดลองงาน' . (!empty($validated['reason']) ? ': ' . $validated['reason'] : '')
        );

        return back()->with('success', "✅ {$employee->full_name} ผ่านทดลองงาน (วันที่: {$passedDate})");
    }

    /**
     * Fail probation — terminate the employee.
     * อ้างอิง พรบ.คุ้มครองแรงงาน ม.17/1 + ม.118 — เลิกจ้างต้องบอกล่วงหน้า 1 งวดจ่ายค่าจ้าง
     * (หรือจ่าย pay in lieu) และต้องดำเนินการก่อนครบ 120 วันเพื่อไม่ต้องจ่ายค่าชดเชย
     */
    public function failProbation(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'end_date' => 'required|date',
            'reason' => 'nullable|string|max:1000',
        ]);

        $employee->update([
            'status' => 'terminated',
            'is_active' => false,
            'end_date' => $validated['end_date'],
        ]);

        AuditLogService::log(
            $employee,
            'probation_failed',
            'status',
            'probation',
            'terminated',
            'ไม่ผ่านทดลองงาน: ' . ($validated['reason'] ?? '-')
        );

        return back()->with('success', "❌ {$employee->full_name} ถูกบันทึกว่าไม่ผ่านทดลองงาน (วันสิ้นสุด: {$validated['end_date']})");
    }

    /**
     * Extend probation — push probation_end_date forward.
     * Capped at PROBATION_LEGAL_LIMIT_DAYS from start_date.
     */
    public function extendProbation(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'new_end_date' => 'required|date|after_or_equal:today',
            'reason' => 'nullable|string|max:1000',
        ]);

        if ($employee->start_date) {
            $maxDate = $employee->start_date->copy()->addDays(Employee::PROBATION_LEGAL_LIMIT_DAYS);
            if (\Carbon\Carbon::parse($validated['new_end_date'])->gt($maxDate)) {
                return back()->withErrors([
                    'new_end_date' => 'ไม่สามารถขยายเกิน 119 วันจากวันเริ่มงาน (เพื่อหลีกเลี่ยงข้อ 118 พรบ.คุ้มครองแรงงาน) — สูงสุด: ' . $maxDate->toDateString(),
                ]);
            }
        }

        $oldEnd = $employee->probation_end_date?->toDateString();
        $employee->update(['probation_end_date' => $validated['new_end_date']]);

        AuditLogService::log(
            $employee,
            'probation_extended',
            'probation_end_date',
            $oldEnd,
            $validated['new_end_date'],
            'ขยายทดลองงาน: ' . ($validated['reason'] ?? '-')
        );

        return back()->with('success', "ขยายระยะทดลองงานของ {$employee->full_name} ถึง {$validated['new_end_date']}");
    }

    public function toggleStatus(Employee $employee)
    {
        $oldStatus = $employee->is_active;
        $employee->is_active = !$employee->is_active;
        $employee->save();

        AuditLogService::log($employee, 'status_toggled', 'is_active', $oldStatus, $employee->is_active, 'Employee status toggled');

        $statusLabel = $employee->is_active ? 'เปิดใช้งาน' : 'ระงับการใช้งาน';
        return back()->with('success', "ปรับปรุงสถานะ {$employee->full_name} เป็น {$statusLabel} สำเร็จ");
    }

    public function create()
    {
        $departments = Department::where('is_active', true)->get();
        $positions = Position::where('is_active', true)->get();
        $roles = Role::orderBy('name')->get();

        return view('employees.create', compact('departments', 'positions', 'roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'nickname' => 'nullable|string|max:100',
            'employee_code' => 'nullable|string|max:50|unique:employees',
            'department_id' => 'nullable|exists:departments,id',
            'position_id' => 'nullable|exists:positions,id',
            'role_id' => 'nullable|exists:roles,id',
            'payroll_mode' => 'required|in:monthly_staff,office_staff,freelance_layer,youtuber_salary,youtuber_settlement,custom_hybrid',
            'status' => 'nullable|string|in:active,inactive,probation,terminated',
            'start_date' => 'nullable|date',
            'probation_end_date' => 'nullable|date',
            'effective_date' => 'nullable|date',
            'base_salary' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'account_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'id_card' => 'nullable|string|max:20',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6|max:255',
        ]);

        $user = User::create([
            'name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'nickname' => $validated['nickname'] ?? null,
            'employee_code' => $validated['employee_code'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'position_id' => $validated['position_id'] ?? null,
            'payroll_mode' => $validated['payroll_mode'],
            'status' => $validated['status'] ?? 'active',
            'is_active' => ($validated['status'] ?? 'active') === 'active',
            'start_date' => $validated['start_date'] ?? null,
            'probation_end_date' => $validated['probation_end_date']
                ?? (!empty($validated['start_date'])
                    ? \Carbon\Carbon::parse($validated['start_date'])->addDays(Employee::PROBATION_DEFAULT_DAYS)->toDateString()
                    : null),
        ]);

        // Profile
        EmployeeProfile::create([
            'employee_id' => $employee->id,
            'phone' => $validated['phone'] ?? null,
            'id_card' => $validated['id_card'] ?? null,
        ]);

        // Salary
        if (!empty($validated['base_salary'])) {
            EmployeeSalaryProfile::create([
                'employee_id' => $employee->id,
                'base_salary' => $validated['base_salary'],
                'effective_date' => $validated['effective_date'] ?? $validated['start_date'] ?? now()->toDateString(),
                'is_current' => true,
            ]);
        }

        // Bank
        if (!empty($validated['bank_name']) && !empty($validated['account_number'])) {
            EmployeeBankAccount::create([
                'employee_id' => $employee->id,
                'bank_name' => $validated['bank_name'],
                'account_number' => $validated['account_number'],
                'account_name' => $validated['account_name'] ?? $employee->full_name,
            ]);
        }

        $roleId = $validated['role_id'] ?? optional(Role::where('name', 'owner')->first())->id;
        if ($roleId) {
            $user->roles()->sync([$roleId]);
        }

        if (in_array($employee->payroll_mode, ['monthly_staff', 'office_staff', 'youtuber_salary'], true)) {
            $moduleDefaults = AttendanceRule::getActiveRule('module_defaults')?->config ?? [];

            $toggleDefaults = [
                'sso_deduction' => (bool) ($moduleDefaults['default_sso_deduction'] ?? true),
                'deduct_late' => (bool) ($moduleDefaults['default_deduct_late'] ?? true),
                'deduct_early' => (bool) ($moduleDefaults['default_deduct_early'] ?? true),
            ];

            foreach ($toggleDefaults as $moduleName => $isEnabled) {
                ModuleToggle::updateOrCreate(
                    ['employee_id' => $employee->id, 'module_name' => $moduleName],
                    ['is_enabled' => $isEnabled]
                );
            }
        }

        AuditLogService::logCreated($employee, 'Employee created');

        return redirect()->route('employees.index')->with('success', 'เพิ่มพนักงานสำเร็จ');
    }

    public function generateCode(Request $request)
    {
        $departmentId = $request->get('department_id');
        $payrollMode = $request->get('payroll_mode');

        $prefix = '';

        if ($departmentId) {
            $dept = Department::find($departmentId);
            $prefix = $dept?->code ?? '';
        }

        if (!$prefix && $payrollMode) {
            $prefix = match ($payrollMode) {
                'freelance_layer' => 'FL',
                'youtuber_salary', 'youtuber_settlement' => 'YT',
                'monthly_staff' => 'STAFF',
                'office_staff' => 'OFFICE',
                default => 'EMP',
            };
        }

        if (!$prefix) {
            $prefix = 'EMP';
        }

        // Find next sequence for this prefix (Database agnostic approach)
        $codes = Employee::where('employee_code', 'like', $prefix . '-%')
            ->pluck('employee_code')
            ->toArray();

        $nextNum = 1;
        if (!empty($codes)) {
            $maxNum = 0;
            foreach ($codes as $c) {
                $parts = explode('-', $c);
                $num = (int) end($parts);
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
            $nextNum = $maxNum + 1;
        }

        $code = $prefix . '-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        return response()->json(['code' => $code, 'prefix' => $prefix]);
    }

    public function edit(Employee $employee)
    {
        $employee->load(['profile', 'salaryProfile', 'bankAccount', 'user.roles']);
        $departments = Department::where('is_active', true)->get();
        $positions = Position::where('is_active', true)->get();
        $roles = Role::orderBy('name')->get();
        $currentRoleId = $employee->user?->roles->first()?->id;
        $leavePolicies = \App\Models\LeavePolicy::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();
        $effectivePolicy = $employee->effectivePolicy();

        return view('employees.edit', compact('employee', 'departments', 'positions', 'roles', 'currentRoleId', 'leavePolicies', 'effectivePolicy'));
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'nickname' => 'nullable|string|max:100',
            'employee_code' => 'nullable|string|max:50|unique:employees,employee_code,' . $employee->id,
            'department_id' => 'nullable|exists:departments,id',
            'position_id' => 'nullable|exists:positions,id',
            'payroll_mode' => 'required|in:monthly_staff,office_staff,freelance_layer,youtuber_salary,youtuber_settlement,custom_hybrid',
            'start_date' => 'nullable|date',
            'probation_end_date' => 'nullable|date',
            'base_salary' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'account_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'id_card' => 'nullable|string|max:20',
            'tier_source' => 'nullable|in:avg,monthly_total,manual',
            'tier_override_id' => 'nullable|exists:performance_tiers,id',
            'tier_override_note' => 'nullable|string|max:255',
            'fixed_rate_per_clip' => 'nullable|numeric|min:0',
            'vacation_entitlement' => 'nullable|integer|min:0|max:365',
            'sick_leave_entitlement' => 'nullable|integer|min:0|max:365',
            'personal_leave_entitlement' => 'nullable|integer|min:0|max:365',
            'leave_policy_id' => 'nullable|exists:leave_policies,id',
            'email' => [
                $employee->user ? 'nullable' : 'required',
                'email', 'max:255',
                \Illuminate\Validation\Rule::unique('users', 'email')->ignore($employee->user?->id),
            ],
            'password' => [$employee->user ? 'nullable' : 'required', 'string', 'min:6', 'max:255'],
            'role_id' => 'nullable|exists:roles,id',
        ]);

        $oldData = $employee->getAttributes();

        if ($employee->user) {
            $userUpdates = [];
            if (!empty($validated['email']) && $validated['email'] !== $employee->user->email) {
                $userUpdates['email'] = $validated['email'];
            }
            if (!empty($validated['password'])) {
                $userUpdates['password'] = $validated['password'];
            }
            $userUpdates['name'] = trim($validated['first_name'] . ' ' . $validated['last_name']);
            $employee->user->update($userUpdates);
        } else {
            $newUser = User::create([
                'name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);
            $employee->user_id = $newUser->id;
            $employee->save();
            $employee->setRelation('user', $newUser);
        }

        $roleId = $validated['role_id'] ?? null;
        if (!$roleId && $employee->user && $employee->user->roles()->count() === 0) {
            $roleId = optional(Role::where('name', 'owner')->first())->id;
        }
        if ($roleId && $employee->user) {
            $employee->user->roles()->sync([$roleId]);
        }

        $updates = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'nickname' => $validated['nickname'] ?? null,
            'employee_code' => $validated['employee_code'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'position_id' => $validated['position_id'] ?? null,
            'payroll_mode' => $validated['payroll_mode'],
            'start_date' => $validated['start_date'] ?? null,
            'probation_end_date' => array_key_exists('probation_end_date', $validated) ? $validated['probation_end_date'] : $employee->probation_end_date,
            'tier_source' => $validated['tier_source'] ?? 'avg',
            'tier_override_id' => $validated['tier_override_id'] ?? null,
            'tier_override_note' => $validated['tier_override_note'] ?? null,
            // override entitlements (NULL = ใช้จาก policy)
            'vacation_entitlement' => $validated['vacation_entitlement'] ?? null,
            'sick_leave_entitlement' => $validated['sick_leave_entitlement'] ?? null,
            'personal_leave_entitlement' => $validated['personal_leave_entitlement'] ?? null,
            'leave_policy_id' => $validated['leave_policy_id'] ?? null,
        ];

        if ($request->user()?->hasRole('admin') && array_key_exists('fixed_rate_per_clip', $validated)) {
            $updates['fixed_rate_per_clip'] = $validated['fixed_rate_per_clip'];
        }

        $employee->update($updates);

        EmployeeProfile::updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'phone' => $validated['phone'] ?? null,
                'id_card' => $validated['id_card'] ?? null,
            ]
        );

        if (array_key_exists('base_salary', $validated) && $validated['base_salary'] !== null && $validated['base_salary'] !== '') {
            $currentSalary = $employee->salaryProfile;
            if ($currentSalary) {
                $currentSalary->update([
                    'base_salary' => $validated['base_salary'],
                    'effective_date' => $validated['start_date'] ?? $currentSalary->effective_date,
                ]);
            } else {
                EmployeeSalaryProfile::create([
                    'employee_id' => $employee->id,
                    'base_salary' => $validated['base_salary'],
                    'effective_date' => $validated['start_date'] ?? now()->toDateString(),
                    'is_current' => true,
                ]);
            }
        }

        if (!empty($validated['bank_name']) && !empty($validated['account_number'])) {
            EmployeeBankAccount::updateOrCreate(
                ['employee_id' => $employee->id, 'is_primary' => true],
                [
                    'bank_name' => $validated['bank_name'],
                    'account_number' => $validated['account_number'],
                    'account_name' => $validated['account_name'] ?? $employee->full_name,
                ]
            );
        }

        AuditLogService::log($employee, 'updated', 'employee', $oldData, $employee->getAttributes(), 'Employee updated');

        return redirect()->route('employees.index')->with('success', 'อัปเดตข้อมูลพนักงานสำเร็จ');
    }
}
