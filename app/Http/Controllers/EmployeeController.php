<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Department;
use App\Models\Position;
use App\Models\EmployeeProfile;
use App\Models\EmployeeSalaryProfile;
use App\Models\EmployeeBankAccount;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $showInactive = $request->boolean('show_inactive', false);

        $query = Employee::with(['department', 'position', 'salaryProfile', 'payslips']);

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

        $employees = $query->orderBy('first_name')->get();
        $departments = Department::where('is_active', true)->get();

        return view('employees.index', compact('employees', 'departments', 'showInactive'));
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

        return view('employees.create', compact('departments', 'positions'));
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
            'payroll_mode' => 'required|in:monthly_staff,freelance_layer,freelance_fixed,youtuber_salary,youtuber_settlement,custom_hybrid',
            'start_date' => 'nullable|date',
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
                'effective_date' => $validated['start_date'] ?? now()->toDateString(),
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

        AuditLogService::logCreated($employee, 'Employee created');

        return redirect()->route('employees.index')->with('success', 'เพิ่มพนักงานสำเร็จ');
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
            'payroll_mode' => 'required|in:monthly_staff,freelance_layer,freelance_fixed,youtuber_salary,youtuber_settlement,custom_hybrid',
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
