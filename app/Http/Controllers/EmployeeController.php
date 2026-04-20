<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Department;
use App\Models\Position;
use App\Models\Role;
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

        return view('employees.index', compact(
            'employees', 'departments', 'positions', 'showInactive', 
            'sortBy', 'sortDir', 'groupBy'
        ));
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
            'payroll_mode' => 'required|in:monthly_staff,office_staff,freelance_layer,freelance_fixed,youtuber_salary,youtuber_settlement,custom_hybrid',
            'status' => 'nullable|string|in:active,inactive,probation,terminated',
            'start_date' => 'nullable|date',
            'effective_date' => 'nullable|date',
            'base_salary' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'account_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'id_card' => 'nullable|string|max:20',
        ]);

        $employee = Employee::create([
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

        if ($employee->user) {
            $employee->user->roles()->sync([$validated['role_id']]);
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
                'freelance_layer', 'freelance_fixed' => 'FL',
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
        $employee->load(['profile', 'salaryProfile', 'bankAccount']);
        $departments = Department::where('is_active', true)->get();
        $positions = Position::where('is_active', true)->get();

        return view('employees.edit', compact('employee', 'departments', 'positions'));
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
            'payroll_mode' => 'required|in:monthly_staff,office_staff,freelance_layer,freelance_fixed,youtuber_salary,youtuber_settlement,custom_hybrid',
            'start_date' => 'nullable|date',
            'base_salary' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'account_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'id_card' => 'nullable|string|max:20',
        ]);

        $oldData = $employee->getAttributes();

        $employee->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'nickname' => $validated['nickname'] ?? null,
            'employee_code' => $validated['employee_code'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'position_id' => $validated['position_id'] ?? null,
            'payroll_mode' => $validated['payroll_mode'],
            'start_date' => $validated['start_date'] ?? null,
        ]);

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
