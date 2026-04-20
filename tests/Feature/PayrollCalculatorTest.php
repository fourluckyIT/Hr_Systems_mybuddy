<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSalaryProfile;
use App\Models\AttendanceLog;
use App\Models\AttendanceRule;
use App\Models\WorkLog;
use App\Models\LayerRateRule;
use App\Models\SocialSecurityConfig;
use App\Models\ModuleToggle;
use App\Services\Payroll\MonthlyStaffCalculator;
use App\Services\Payroll\FreelanceLayerCalculator;
use App\Services\Payroll\FreelanceFixedCalculator;
use App\Services\Payroll\YoutuberSettlementCalculator;
use App\Services\SocialSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollCalculatorTest extends TestCase
{
    use RefreshDatabase;

    // ===== Monthly Staff =====

    public function test_monthly_staff_basic_salary(): void
    {
        $employee = $this->createEmployee('monthly_staff', 30000);
        $this->createWorkingHoursRule();

        $calc = app(MonthlyStaffCalculator::class);
        $result = $calc->calculate($employee, 1, 2026);

        $items = collect($result['items']);
        $baseSalary = $items->firstWhere('item_type_code', 'base_salary');

        $this->assertEquals(30000, $baseSalary['amount']);
        $this->assertEquals('income', $baseSalary['category']);
        $this->assertEquals('master', $baseSalary['source_flag']);
    }

    public function test_monthly_staff_diligence_with_zero_late(): void
    {
        $employee = $this->createEmployee('monthly_staff', 25000);
        $this->createWorkingHoursRule();
        $this->createDiligenceRule(500);

        // Create attendance with zero late
        for ($d = 1; $d <= 22; $d++) {
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'log_date' => "2026-01-" . str_pad($d, 2, '0', STR_PAD_LEFT),
                'day_type' => 'workday',
                'working_minutes' => 540,
                'late_minutes' => 0,
                'ot_minutes' => 0,
                'ot_enabled' => false,
                'lwop_flag' => false,
                'is_disabled' => false,
            ]);
        }

        $calc = app(MonthlyStaffCalculator::class);
        $result = $calc->calculate($employee, 1, 2026);

        $items = collect($result['items']);
        $diligence = $items->firstWhere('item_type_code', 'diligence');

        $this->assertEquals(500, $diligence['amount']);
    }

    public function test_monthly_staff_no_diligence_when_late(): void
    {
        $employee = $this->createEmployee('monthly_staff', 25000);
        $this->createWorkingHoursRule();
        $this->createDiligenceRule(500);

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'log_date' => '2026-01-02',
            'day_type' => 'workday',
            'working_minutes' => 530,
            'late_minutes' => 10,
            'ot_minutes' => 0,
            'ot_enabled' => false,
            'lwop_flag' => false,
            'is_disabled' => false,
        ]);

        $calc = app(MonthlyStaffCalculator::class);
        $result = $calc->calculate($employee, 1, 2026);

        $items = collect($result['items']);
        $diligence = $items->firstWhere('item_type_code', 'diligence');

        $this->assertEquals(0, $diligence['amount']);
    }

    public function test_monthly_staff_lwop_deduction(): void
    {
        $employee = $this->createEmployee('monthly_staff', 22000);
        $this->createWorkingHoursRule(22);

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'log_date' => '2026-01-05',
            'day_type' => 'lwop',
            'working_minutes' => 0,
            'late_minutes' => 0,
            'ot_minutes' => 0,
            'ot_enabled' => false,
            'lwop_flag' => true,
            'is_disabled' => false,
        ]);

        $calc = app(MonthlyStaffCalculator::class);
        $result = $calc->calculate($employee, 1, 2026);

        $items = collect($result['items']);
        $lwop = $items->firstWhere('item_type_code', 'lwop');

        // 1 day LWOP = baseSalary / 22 = 22000 / 22 = 1000
        $this->assertEquals(1000, $lwop['amount']);
        $this->assertEquals('deduction', $lwop['category']);
    }

    public function test_monthly_staff_ot_pay(): void
    {
        $employee = $this->createEmployee('monthly_staff', 19800);
        $this->createWorkingHoursRule(22, 540);
        $this->createOtRule(1.5);

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'log_date' => '2026-01-06',
            'day_type' => 'workday',
            'working_minutes' => 600,
            'late_minutes' => 0,
            'ot_minutes' => 60,
            'ot_enabled' => true,
            'lwop_flag' => false,
            'is_disabled' => false,
        ]);

        $calc = app(MonthlyStaffCalculator::class);
        $result = $calc->calculate($employee, 1, 2026);

        $items = collect($result['items']);
        $ot = $items->firstWhere('item_type_code', 'overtime');

        // rate per minute = 19800 / (22*540) = 19800 / 11880 ~= 1.6667
        // OT = 60 * 1.6667 * 1.5 = 150.00
        $this->assertEquals(150.00, $ot['amount']);
    }

    public function test_monthly_staff_holiday_regular_and_ot_split(): void
    {
        $employee = $this->createEmployee('monthly_staff', 19800);
        $this->createWorkingHoursRule(22, 540);
        $this->createOtRule(1.5, 3.0, 36, 40);

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'log_date' => '2026-01-10',
            'day_type' => 'holiday',
            'working_minutes' => 0,
            'late_minutes' => 0,
            'ot_minutes' => 600,
            'ot_enabled' => true,
            'lwop_flag' => false,
            'is_disabled' => false,
        ]);

        $calc = app(MonthlyStaffCalculator::class);
        $result = $calc->calculate($employee, 1, 2026);

        $items = collect($result['items']);
        $holidayWorkPay = $items->firstWhere('item_type_code', 'holiday_work_pay');
        $ot = $items->firstWhere('item_type_code', 'overtime');

        // rate per minute = 19800 / (22*540) = 1.6667
        // holiday regular = 540 * 1.6667 * 1.0 = 900.00
        // holiday OT excess = (600 - 540) * 1.6667 * 3.0 = 300.00
        $this->assertEquals(900.00, $holidayWorkPay['amount']);
        $this->assertEquals(300.00, $ot['amount']);
    }

    public function test_monthly_staff_ot_is_capped_by_weekly_limit(): void
    {
        $employee = $this->createEmployee('monthly_staff', 19800);
        $this->createWorkingHoursRule(22, 540);
        $this->createOtRule(1.5, 3.0, 2, 40);

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'log_date' => '2026-01-05',
            'day_type' => 'workday',
            'working_minutes' => 540,
            'late_minutes' => 0,
            'ot_minutes' => 90,
            'ot_enabled' => true,
            'lwop_flag' => false,
            'is_disabled' => false,
        ]);

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'log_date' => '2026-01-06',
            'day_type' => 'workday',
            'working_minutes' => 540,
            'late_minutes' => 0,
            'ot_minutes' => 90,
            'ot_enabled' => true,
            'lwop_flag' => false,
            'is_disabled' => false,
        ]);

        $calc = app(MonthlyStaffCalculator::class);
        $result = $calc->calculate($employee, 1, 2026);

        $items = collect($result['items']);
        $ot = $items->firstWhere('item_type_code', 'overtime');

        // Weekly cap 2h = 120 minutes, workday multiplier 1.5
        // OT = 120 * 1.6667 * 1.5 = 300.00
        $this->assertEquals(300.00, $ot['amount']);
    }

    public function test_monthly_staff_late_grace_is_monthly_quota(): void
    {
        $employee = $this->createEmployee('monthly_staff', 19800);
        $this->createWorkingHoursRule(22, 540);

        AttendanceRule::create([
            'rule_type' => 'late_deduction',
            'is_active' => true,
            'effective_date' => '2025-01-01',
            'config' => [
                'type' => 'per_minute',
                'grace_period_minutes' => 10,
            ],
        ]);

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'log_date' => '2026-01-06',
            'day_type' => 'workday',
            'working_minutes' => 540,
            'late_minutes' => 8,
            'ot_minutes' => 0,
            'ot_enabled' => false,
            'lwop_flag' => false,
            'is_disabled' => false,
        ]);

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'log_date' => '2026-01-07',
            'day_type' => 'workday',
            'working_minutes' => 540,
            'late_minutes' => 7,
            'ot_minutes' => 0,
            'ot_enabled' => false,
            'lwop_flag' => false,
            'is_disabled' => false,
        ]);

        $calc = app(MonthlyStaffCalculator::class);
        $result = $calc->calculate($employee, 1, 2026);

        $late = collect($result['items'])->firstWhere('item_type_code', 'late_deduction');

        // total late = 15 minutes, monthly grace = 10 minutes => billable = 5
        // rate per minute = 19800 / (22*540) = 1.6667 => deduction = 8.33
        $this->assertEquals(8.33, $late['amount']);
    }

    public function test_monthly_staff_sso_deduction(): void
    {
        $employee = $this->createEmployee('monthly_staff', 20000);
        $this->createWorkingHoursRule();
        $this->enableSso($employee);
        $this->createSsoConfig(5, 5, 15000, 750);

        $calc = app(MonthlyStaffCalculator::class);
        $result = $calc->calculate($employee, 1, 2026);

        $items = collect($result['items']);
        $sso = $items->firstWhere('item_type_code', 'sso_employee');

        // salary 20000 capped at 15000 => 15000 * 5% = 750
        $this->assertEquals(750, $sso['amount']);
    }

    public function test_monthly_staff_net_pay_calculation(): void
    {
        $employee = $this->createEmployee('monthly_staff', 30000);
        $this->createWorkingHoursRule();

        $calc = app(MonthlyStaffCalculator::class);
        $result = $calc->calculate($employee, 1, 2026);

        $summary = $result['summary'];
        $this->assertEquals(
            round($summary['total_income'] - $summary['total_deduction'], 2),
            $summary['net_pay']
        );
    }

    // ===== Freelance Layer =====

    public function test_freelance_layer_basic_calculation(): void
    {
        $employee = $this->createEmployee('freelance_layer', 0);

        LayerRateRule::create([
            'employee_id' => $employee->id,
            'layer_from' => 1,
            'layer_to' => 3,
            'rate_per_minute' => 2.50,
            'effective_date' => '2025-01-01',
            'is_active' => true,
        ]);

        WorkLog::create([
            'employee_id' => $employee->id,
            'month' => 1, 'year' => 2026,
            'work_type' => 'editing',
            'layer' => 2,
            'hours' => 1, 'minutes' => 30, 'seconds' => 0,
            'quantity' => 1,
            'rate' => 2.50,
            'amount' => 0,
            'sort_order' => 1,
            'is_disabled' => false,
        ]);

        $calc = new FreelanceLayerCalculator();
        $result = $calc->calculate($employee, 1, 2026);

        // 1 hour 30 min = 90 minutes * 2.50 = 225.00
        $this->assertEquals(225.00, $result['summary']['total_income']);
        $this->assertEquals(1, $result['summary']['work_log_count']);
    }

    public function test_freelance_layer_with_seconds(): void
    {
        $employee = $this->createEmployee('freelance_layer', 0);

        LayerRateRule::create([
            'employee_id' => $employee->id,
            'layer_from' => 1,
            'layer_to' => 5,
            'rate_per_minute' => 3.00,
            'effective_date' => '2025-01-01',
            'is_active' => true,
        ]);

        WorkLog::create([
            'employee_id' => $employee->id,
            'month' => 2, 'year' => 2026,
            'work_type' => 'editing',
            'layer' => 1,
            'hours' => 0, 'minutes' => 10, 'seconds' => 30,
            'quantity' => 1,
            'rate' => 3.00,
            'amount' => 0,
            'sort_order' => 1,
            'is_disabled' => false,
        ]);

        $calc = new FreelanceLayerCalculator();
        $result = $calc->calculate($employee, 2, 2026);

        // 0h 10m 30s => 10 + 30/60 = 10.5 minutes * 3.00 = 31.50
        $this->assertEquals(31.50, $result['summary']['total_income']);
    }

    // ===== Freelance Fixed =====

    public function test_freelance_fixed_basic_calculation(): void
    {
        $employee = $this->createEmployee('freelance_fixed', 0);

        WorkLog::create([
            'employee_id' => $employee->id,
            'month' => 1, 'year' => 2026,
            'work_type' => 'translation',
            'hours' => 0, 'minutes' => 0, 'seconds' => 0,
            'quantity' => 5,
            'rate' => 800,
            'amount' => 0,
            'sort_order' => 1,
            'is_disabled' => false,
        ]);

        $calc = new FreelanceFixedCalculator();
        $result = $calc->calculate($employee, 1, 2026);

        // 5 * 800 = 4000
        $this->assertEquals(4000, $result['summary']['total_income']);
        $this->assertEquals(4000, $result['summary']['net_pay']);
    }

    public function test_freelance_fixed_multiple_work_logs(): void
    {
        $employee = $this->createEmployee('freelance_fixed', 0);

        WorkLog::create([
            'employee_id' => $employee->id,
            'month' => 3, 'year' => 2026,
            'work_type' => 'design', 'quantity' => 2, 'rate' => 1500,
            'hours' => 0, 'minutes' => 0, 'seconds' => 0,
            'amount' => 0, 'sort_order' => 1, 'is_disabled' => false,
        ]);
        WorkLog::create([
            'employee_id' => $employee->id,
            'month' => 3, 'year' => 2026,
            'work_type' => 'review', 'quantity' => 3, 'rate' => 500,
            'hours' => 0, 'minutes' => 0, 'seconds' => 0,
            'amount' => 0, 'sort_order' => 2, 'is_disabled' => false,
        ]);

        $calc = new FreelanceFixedCalculator();
        $result = $calc->calculate($employee, 3, 2026);

        // 2*1500 + 3*500 = 3000 + 1500 = 4500
        $this->assertEquals(4500, $result['summary']['total_income']);
        $this->assertEquals(2, $result['summary']['work_log_count']);
    }

    // ===== Youtuber Settlement =====

    public function test_youtuber_settlement_income_and_expense(): void
    {
        $employee = $this->createEmployee('youtuber_settlement', 0);

        WorkLog::create([
            'employee_id' => $employee->id,
            'month' => 1, 'year' => 2026,
            'work_type' => 'YouTube AdSense', 'entry_type' => 'income',
            'amount' => 50000, 'hours' => 0, 'minutes' => 0, 'seconds' => 0,
            'quantity' => 1, 'rate' => 0, 'sort_order' => 1,
        ]);
        WorkLog::create([
            'employee_id' => $employee->id,
            'month' => 1, 'year' => 2026,
            'work_type' => 'Equipment cost', 'entry_type' => 'deduction',
            'amount' => 12000, 'hours' => 0, 'minutes' => 0, 'seconds' => 0,
            'quantity' => 1, 'rate' => 0, 'sort_order' => 2,
        ]);

        $calc = new YoutuberSettlementCalculator();
        $result = $calc->calculate($employee, 1, 2026);

        $this->assertEquals(50000, $result['summary']['total_income']);
        $this->assertEquals(12000, $result['summary']['total_deduction']);
        $this->assertEquals(38000, $result['summary']['net_pay']);
    }

    public function test_youtuber_settlement_entry_type_classification(): void
    {
        $employee = $this->createEmployee('youtuber_settlement', 0);

        WorkLog::create([
            'employee_id' => $employee->id,
            'month' => 2, 'year' => 2026,
            'work_type' => 'Sponsorship', 'entry_type' => 'income',
            'amount' => 20000, 'hours' => 0, 'minutes' => 0, 'seconds' => 0,
            'quantity' => 1, 'rate' => 0, 'sort_order' => 1,
        ]);

        $calc = new YoutuberSettlementCalculator();
        $result = $calc->calculate($employee, 2, 2026);

        $incomeItems = collect($result['items'])->where('category', 'income');
        $deductionItems = collect($result['items'])->where('category', 'deduction');

        $this->assertEquals(1, $incomeItems->count());
        $this->assertEquals(0, $deductionItems->count());
        $this->assertEquals(20000, $result['summary']['net_pay']);
    }

    // ===== SSO =====

    public function test_sso_calculation_within_ceiling(): void
    {
        $this->createSsoConfig(5, 5, 15000, 750);
        $sso = new SocialSecurityService();
        $result = $sso->calculate(10000, '2026-01-01');

        // 10000 * 5% = 500
        $this->assertEquals(500, $result['employee']);
        $this->assertEquals(500, $result['employer']);
    }

    public function test_sso_calculation_at_ceiling(): void
    {
        $this->createSsoConfig(5, 5, 15000, 750);
        $sso = new SocialSecurityService();
        $result = $sso->calculate(20000, '2026-01-01');

        // 20000 capped at 15000, 15000 * 5% = 750
        $this->assertEquals(750, $result['employee']);
        $this->assertEquals(750, $result['employer']);
    }

    public function test_sso_no_config_returns_zero(): void
    {
        $sso = new SocialSecurityService();
        $result = $sso->calculate(30000, '2026-01-01');

        $this->assertEquals(0, $result['employee']);
        $this->assertEquals(0, $result['employer']);
    }

    public function test_sso_max_contribution_cap(): void
    {
        $this->createSsoConfig(10, 10, 50000, 750);
        $sso = new SocialSecurityService();
        $result = $sso->calculate(50000, '2026-01-01');

        // 50000 * 10% = 5000, but max is 750
        $this->assertEquals(750, $result['employee']);
        $this->assertEquals(750, $result['employer']);
    }

    // ===== Helpers =====

    private function createEmployee(string $payrollMode, float $baseSalary): Employee
    {
        $employee = Employee::create([
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'employee_code' => 'EMP-' . uniqid(),
            'payroll_mode' => $payrollMode,
            'is_active' => true,
            'start_date' => '2025-01-01',
        ]);

        if ($baseSalary > 0) {
            EmployeeSalaryProfile::create([
                'employee_id' => $employee->id,
                'base_salary' => $baseSalary,
                'effective_date' => '2025-01-01',
            ]);
            $employee->load('salaryProfile');
        }

        return $employee;
    }

    private function createWorkingHoursRule(int $workingDays = 22, int $targetMinutes = 540): void
    {
        AttendanceRule::create([
            'rule_type' => 'working_hours',
            'is_active' => true,
            'effective_date' => '2025-01-01',
            'config' => [
                'target_minutes_per_day' => $targetMinutes,
                'working_days_per_month' => $workingDays,
                'target_check_in' => '09:30',
                'target_check_out' => '18:30',
            ],
        ]);
    }

    private function createDiligenceRule(float $amount): void
    {
        AttendanceRule::create([
            'rule_type' => 'diligence',
            'is_active' => true,
            'effective_date' => '2025-01-01',
            'config' => [
                'use_tiers' => false,
                'amount' => $amount,
                'require_zero_late' => true,
                'require_zero_lwop' => true,
            ],
        ]);
    }

    private function createOtRule(float $workdayMultiplier, float $holidayMultiplier = 3.0, float $weeklyOtLimitHours = 36, float $maxOtHours = 40): void
    {
        AttendanceRule::create([
            'rule_type' => 'ot_rate',
            'is_active' => true,
            'effective_date' => '2025-01-01',
            'config' => [
                'rate_multiplier' => $workdayMultiplier,
                'rate_multiplier_workday' => $workdayMultiplier,
                'rate_multiplier_holiday' => $holidayMultiplier,
                'enable_holiday_legal_split' => true,
                'holiday_regular_multiplier_monthly' => 1.0,
                'weekly_ot_limit_hours' => $weeklyOtLimitHours,
                'max_ot_hours' => $maxOtHours,
            ],
        ]);
    }

    private function createSsoConfig(float $empRate, float $erRate, float $ceiling, float $maxContrib): void
    {
        SocialSecurityConfig::create([
            'employee_rate' => $empRate,
            'employer_rate' => $erRate,
            'salary_ceiling' => $ceiling,
            'max_contribution' => $maxContrib,
            'effective_date' => '2025-01-01',
            'is_active' => true,
        ]);
    }

    private function enableSso(Employee $employee): void
    {
        ModuleToggle::create([
            'employee_id' => $employee->id,
            'module_name' => 'sso_deduction',
            'is_enabled' => true,
        ]);
    }
}
