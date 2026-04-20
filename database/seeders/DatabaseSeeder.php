<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Department;
use App\Models\Position;
use App\Models\PayrollItemType;
use App\Models\SocialSecurityConfig;
use App\Models\AttendanceRule;
use App\Models\User;
use App\Models\Employee;
use App\Models\EmployeeProfile;
use App\Models\EmployeeSalaryProfile;
use App\Models\EmployeeBankAccount;
use App\Models\LayerRateRule;
use App\Models\CompanyHoliday;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Company Holidays
        CompanyHoliday::create(['holiday_date' => '2026-04-13', 'name' => 'วันสงกรานต์']);
        CompanyHoliday::create(['holiday_date' => '2026-04-14', 'name' => 'วันสงกรานต์']);
        CompanyHoliday::create(['holiday_date' => '2026-04-15', 'name' => 'วันสงกรานต์']);
        // 1. Roles
        $adminRole = Role::create(['name' => 'admin', 'display_name' => 'Administrator']);
        $ownerRole = Role::create(['name' => 'owner', 'display_name' => 'Employee / Owner']);

        // 2. Departments & Positions
        $editDept = Department::create(['name' => 'ตัดต่อ', 'code' => 'EDIT']);
        $contentDept = Department::create(['name' => 'คอนเทนต์', 'code' => 'CONTENT']);
        $mgmtDept = Department::create(['name' => 'บริหาร', 'code' => 'MGMT']);

        $editorPos = Position::create(['name' => 'ตัดต่อ', 'code' => 'EDITOR', 'department_id' => $editDept->id]);
        $seniorEditorPos = Position::create(['name' => 'ตัดต่อซีเนียร์', 'code' => 'SR_EDITOR', 'department_id' => $editDept->id]);
        $youtuberPos = Position::create(['name' => 'YouTuber', 'code' => 'YOUTUBER', 'department_id' => $contentDept->id]);
        $talentPos = Position::create(['name' => 'Talent', 'code' => 'TALENT', 'department_id' => $contentDept->id]);
        $managerPos = Position::create(['name' => 'ผู้จัดการ', 'code' => 'MANAGER', 'department_id' => $mgmtDept->id]);

        // 3. Payroll Item Types
        $types = [
            // Income
            ['code' => 'base_salary', 'label_th' => 'ฐานเงินเดือน', 'label_en' => 'Base Salary', 'category' => 'income', 'is_system' => true, 'sort_order' => 1],
            ['code' => 'overtime', 'label_th' => 'ค่าล่วงเวลา', 'label_en' => 'Overtime', 'category' => 'income', 'is_system' => true, 'sort_order' => 2],
            ['code' => 'diligence', 'label_th' => 'เบี้ยขยัน', 'label_en' => 'Diligence Allowance', 'category' => 'income', 'is_system' => true, 'sort_order' => 3],
            ['code' => 'performance', 'label_th' => 'ค่าประสิทธิภาพ', 'label_en' => 'Performance', 'category' => 'income', 'is_system' => true, 'sort_order' => 4],
            ['code' => 'freelance_income', 'label_th' => 'ค่าจ้าง', 'label_en' => 'Freelance Income', 'category' => 'income', 'is_system' => true, 'sort_order' => 5],
            ['code' => 'other_income_1', 'label_th' => 'อื่นๆ 1', 'label_en' => 'Other 1', 'category' => 'income', 'is_system' => false, 'sort_order' => 6],
            ['code' => 'other_income_2', 'label_th' => 'อื่นๆ 2', 'label_en' => 'Other 2', 'category' => 'income', 'is_system' => false, 'sort_order' => 7],
            // Deductions
            ['code' => 'cash_advance', 'label_th' => 'เงินหักล่วงหน้า', 'label_en' => 'Cash Advance', 'category' => 'deduction', 'is_system' => true, 'sort_order' => 1],
            ['code' => 'lwop', 'label_th' => 'ขาดงาน', 'label_en' => 'Leave Without Pay', 'category' => 'deduction', 'is_system' => true, 'sort_order' => 2],
            ['code' => 'late_deduction', 'label_th' => 'มาสาย', 'label_en' => 'Late', 'category' => 'deduction', 'is_system' => true, 'sort_order' => 3],
            ['code' => 'sso_employee', 'label_th' => 'ประกันสังคม', 'label_en' => 'Social Security', 'category' => 'deduction', 'is_system' => true, 'sort_order' => 4],
            ['code' => 'other_deduction_1', 'label_th' => 'หัก 1', 'label_en' => 'Deduction 1', 'category' => 'deduction', 'is_system' => false, 'sort_order' => 5],
            ['code' => 'other_deduction_2', 'label_th' => 'หัก 2', 'label_en' => 'Deduction 2', 'category' => 'deduction', 'is_system' => false, 'sort_order' => 6],
            ['code' => 'other_deduction_3', 'label_th' => 'หัก 3', 'label_en' => 'Deduction 3', 'category' => 'deduction', 'is_system' => false, 'sort_order' => 7],
        ];
        foreach ($types as $type) {
            PayrollItemType::create($type);
        }

        $this->call([
            JobStageSeeder::class,
            PerformanceTierSeeder::class,
            GameSeeder::class,
        ]);

        // 4. Social Security Config
        SocialSecurityConfig::create([
            'effective_date' => '2024-01-01',
            'employee_rate' => 5.00,
            'employer_rate' => 5.00,
            'salary_ceiling' => 15000.00,
            'max_contribution' => 750.00,
            'is_active' => true,
        ]);

        SocialSecurityConfig::create([
            'effective_date' => '2026-04-01',
            'employee_rate' => 5.00,
            'employer_rate' => 5.00,
            'salary_ceiling' => 17500.00,
            'max_contribution' => 875.00,
            'is_active' => true,
        ]);

        // 5. Attendance Rules
        AttendanceRule::create([
            'rule_type' => 'diligence',
            'config' => ['amount' => 500, 'require_zero_late' => true, 'require_zero_lwop' => true],
            'effective_date' => '2024-01-01',
            'is_active' => true,
        ]);
        AttendanceRule::create([
            'rule_type' => 'ot_rate',
                'config' => [
                    'max_ot_hours' => 40,
                    'weekly_ot_limit_hours' => 36,
                    'rate_multiplier' => 1.5,
                    'rate_multiplier_workday' => 1.5,
                    'rate_multiplier_holiday' => 3.0,
                    'enable_holiday_legal_split' => true,
                    'holiday_regular_multiplier_monthly' => 1.0,
                    'requires_employee_consent' => true,
                ],
            'effective_date' => '2024-01-01',
            'is_active' => true,
        ]);
        AttendanceRule::create([
            'rule_type' => 'late_deduction',
            'config' => ['type' => 'none', 'rate_per_minute' => 0, 'grace_period_minutes' => 0],
            'effective_date' => '2024-01-01',
            'is_active' => true,
        ]);
        AttendanceRule::create([
            'rule_type' => 'working_hours',
            'config' => [
                'target_check_in' => '09:30',
                'target_check_out' => '18:30',
                'target_minutes_per_day' => 540,
                'lunch_break_minutes' => 60,
                'working_days_per_month' => 22,
            ],
            'effective_date' => '2024-01-01',
            'is_active' => true,
        ]);

        // 6. Admin User
        $adminUser = User::create([
            'name' => 'Admin',
            'email' => 'admin@xhr.local',
            'password' => bcrypt('password'),
        ]);
        $adminUser->roles()->attach($adminRole);

        // 7. Owner User + linked employee (for role simulation)
        $ownerUser = User::create([
            'name' => 'Owner Demo',
            'email' => 'owner@xhr.local',
            'password' => bcrypt('password'),
        ]);
        $ownerUser->roles()->attach($ownerRole);

        $ownerEmployee = Employee::create([
            'user_id' => $ownerUser->id,
            'employee_code' => 'OWN-001',
            'first_name' => 'Owner',
            'last_name' => 'Demo',
            'nickname' => 'Owner',
            'department_id' => $contentDept->id,
            'position_id' => $youtuberPos->id,
            'payroll_mode' => 'youtuber_salary',
            'status' => 'active',
            'is_active' => true,
            'start_date' => '2024-01-01',
        ]);

        EmployeeProfile::create([
            'employee_id' => $ownerEmployee->id,
        ]);

        EmployeeSalaryProfile::create([
            'employee_id' => $ownerEmployee->id,
            'base_salary' => 20000,
            'effective_date' => '2024-01-01',
            'is_current' => true,
        ]);

        EmployeeBankAccount::create([
            'employee_id' => $ownerEmployee->id,
            'bank_name' => 'SCB',
            'account_number' => '1234567890',
            'account_name' => 'Owner Demo',
            'is_primary' => true,
        ]);

        // 8. Monthly Staff test account (salary employee login)
        $staffUser = User::create([
            'name' => 'สมชาย ทดสอบ',
            'email' => 'staff@xhr.local',
            'password' => bcrypt('password'),
        ]);
        $staffUser->roles()->attach($ownerRole);

        $staffEmployee = Employee::create([
            'user_id' => $staffUser->id,
            'employee_code' => 'STAFF-001',
            'first_name' => 'สมชาย',
            'last_name' => 'ทดสอบ',
            'nickname' => 'ชาย',
            'department_id' => $editDept->id,
            'position_id' => $editorPos->id,
            'payroll_mode' => 'monthly_staff',
            'status' => 'active',
            'is_active' => true,
            'start_date' => '2025-01-01',
            'probation_end_date' => '2025-04-01',
        ]);

        EmployeeProfile::create([
            'employee_id' => $staffEmployee->id,
        ]);

        EmployeeSalaryProfile::create([
            'employee_id' => $staffEmployee->id,
            'base_salary' => 18000,
            'effective_date' => '2025-01-01',
            'is_current' => true,
        ]);

        EmployeeBankAccount::create([
            'employee_id' => $staffEmployee->id,
            'bank_name' => 'กสิกรไทย',
            'account_number' => '0987654321',
            'account_name' => 'สมชาย ทดสอบ',
            'is_primary' => true,
        ]);
    }
}
