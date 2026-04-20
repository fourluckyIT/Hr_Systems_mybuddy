<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\AttendanceRule;
use App\Models\CompanyExpense;
use App\Models\CompanyRevenue;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeProfile;
use App\Models\EmployeeSalaryProfile;
use App\Models\LayerRateRule;
use App\Models\ModuleToggle;
use App\Models\PayrollBatch;
use App\Models\PayrollItem;
use App\Models\Payslip;
use App\Models\DaySwapRequest;
use App\Models\PayslipItem;
use App\Models\Role;
use App\Models\SocialSecurityConfig;
use App\Models\SubscriptionCost;
use App\Models\User;
use App\Models\WorkLog;
use App\Services\Payroll\PayrollCalculationService;
use App\Services\SocialSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FullSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Employee $monthlyEmployee;
    protected Employee $freelanceLayerEmployee;
    protected Employee $freelanceFixedEmployee;
    protected Employee $youtuberSalaryEmployee;
    protected Employee $youtuberSettlementEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $adminRole = Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        $hrRole = Role::create(['name' => 'hr', 'display_name' => 'HR']);
        $managerRole = Role::create(['name' => 'manager', 'display_name' => 'Manager']);
        $employeeRole = Role::create(['name' => 'employee', 'display_name' => 'Employee']);
        $viewerRole = Role::create(['name' => 'viewer', 'display_name' => 'Viewer']);

        // Create user
        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => bcrypt('password'),
        ]);

        // Attach admin role
        $this->user->roles()->attach($adminRole);

        // Create SSO config
        SocialSecurityConfig::create([
            'effective_date' => '2024-01-01',
            'employee_rate' => 5.00,
            'employer_rate' => 5.00,
            'salary_ceiling' => 15000.00,
            'max_contribution' => 750.00,
            'is_active' => true,
        ]);

        // Create attendance rules
        AttendanceRule::create([
            'rule_type' => 'working_hours',
            'config' => ['target_minutes_per_day' => 540, 'lunch_break_minutes' => 60, 'working_days_per_month' => 22],
            'effective_date' => '2024-01-01',
            'is_active' => true,
        ]);
        AttendanceRule::create([
            'rule_type' => 'diligence',
            'config' => ['amount' => 500, 'require_zero_late' => true, 'require_zero_lwop' => true],
            'effective_date' => '2024-01-01',
            'is_active' => true,
        ]);
        AttendanceRule::create([
            'rule_type' => 'late_deduction',
            'config' => ['type' => 'per_minute', 'rate_per_minute' => 5, 'grace_period_minutes' => 0],
            'effective_date' => '2024-01-01',
            'is_active' => true,
        ]);
        AttendanceRule::create([
            'rule_type' => 'ot_rate',
            'config' => ['max_ot_hours' => 40, 'rate_multiplier' => 1.5],
            'effective_date' => '2024-01-01',
            'is_active' => true,
        ]);

        // Create employees for each payroll mode
        $this->monthlyEmployee = $this->createEmployee('Monthly', 'Staff', 'monthly_staff', 30000);
        $this->freelanceLayerEmployee = $this->createEmployee('Freelance', 'Layer', 'freelance_layer', 0);
        $this->freelanceFixedEmployee = $this->createEmployee('Freelance', 'Fixed', 'freelance_fixed', 0);
        $this->youtuberSalaryEmployee = $this->createEmployee('Youtuber', 'Salary', 'youtuber_salary', 25000);
        $this->youtuberSettlementEmployee = $this->createEmployee('Youtuber', 'Settlement', 'youtuber_settlement', 0);

        // Create layer rate rules for freelance_layer employee
        LayerRateRule::create([
            'employee_id' => $this->freelanceLayerEmployee->id,
            'layer_from' => 1,
            'layer_to' => 3,
            'rate_per_minute' => 2.50,
            'effective_date' => '2024-01-01',
            'is_active' => true,
        ]);
        LayerRateRule::create([
            'employee_id' => $this->freelanceLayerEmployee->id,
            'layer_from' => 4,
            'layer_to' => 6,
            'rate_per_minute' => 3.00,
            'effective_date' => '2024-01-01',
            'is_active' => true,
        ]);
    }

    private function createEmployee(string $firstName, string $lastName, string $payrollMode, float $baseSalary): Employee
    {
        $employee = Employee::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'payroll_mode' => $payrollMode,
            'status' => 'active',
            'is_active' => true,
            'start_date' => '2024-01-01',
        ]);

        EmployeeProfile::create([
            'employee_id' => $employee->id,
        ]);

        if ($baseSalary > 0) {
            EmployeeSalaryProfile::create([
                'employee_id' => $employee->id,
                'base_salary' => $baseSalary,
                'effective_date' => '2024-01-01',
                'is_current' => true,
            ]);
        }

        EmployeeBankAccount::create([
            'employee_id' => $employee->id,
            'bank_name' => 'Test Bank',
            'account_number' => '1234567890',
            'account_name' => "$firstName $lastName",
            'is_primary' => true,
        ]);

        return $employee;
    }

    // ========================================
    // SECTION A: Authentication Tests
    // ========================================

    public function test_login_page_loads(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_user_can_login(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@test.local',
            'password' => 'password',
        ]);
        $response->assertRedirect('/employees');
    }

    public function test_wrong_password_rejected(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@test.local',
            'password' => 'wrong',
        ]);
        $response->assertSessionHasErrors();
    }

    public function test_unauthenticated_redirect_to_login(): void
    {
        $response = $this->get('/employees');
        $response->assertRedirect('/login');
    }

    public function test_user_can_logout(): void
    {
        $response = $this->actingAs($this->user)->post('/logout');
        $response->assertRedirect('/login');
    }

    // ========================================
    // SECTION B: Employee Management Tests
    // ========================================

    public function test_employee_board_loads(): void
    {
        $response = $this->actingAs($this->user)->get('/employees');
        $response->assertStatus(200);
        $response->assertSee('Monthly');
    }

    public function test_employee_create_form_loads(): void
    {
        $response = $this->actingAs($this->user)->get('/employees/create');
        $response->assertStatus(200);
    }

    public function test_can_create_new_employee(): void
    {
        $response = $this->actingAs($this->user)->post('/employees', [
            'first_name' => 'ทดสอบ',
            'last_name' => 'ใหม่',
            'payroll_mode' => 'monthly_staff',
            'status' => 'active',
            'start_date' => '2026-04-01',
            'base_salary' => 25000,
            'effective_date' => '2026-04-01',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('employees', ['first_name' => 'ทดสอบ', 'last_name' => 'ใหม่']);
    }

    public function test_employee_edit_form_loads(): void
    {
        $response = $this->actingAs($this->user)->get("/employees/{$this->monthlyEmployee->id}/edit");
        $response->assertStatus(200);
    }

    public function test_can_update_employee(): void
    {
        $response = $this->actingAs($this->user)->put("/employees/{$this->monthlyEmployee->id}", [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'payroll_mode' => 'monthly_staff',
            'status' => 'active',
        ]);
        $response->assertRedirect();
        $this->monthlyEmployee->refresh();
        $this->assertEquals('Updated', $this->monthlyEmployee->first_name);
    }

    public function test_can_toggle_employee_status(): void
    {
        $response = $this->actingAs($this->user)->patch("/employees/{$this->monthlyEmployee->id}/toggle-status");
        $response->assertRedirect();
        $this->monthlyEmployee->refresh();
        $this->assertFalse($this->monthlyEmployee->is_active);
    }

    // ========================================
    // SECTION C: Workspace Tests
    // ========================================

    public function test_workspace_loads_for_monthly_staff(): void
    {
        $response = $this->actingAs($this->user)->get("/workspace/{$this->monthlyEmployee->id}/4/2026");
        $response->assertStatus(200);
        $response->assertSee('ขอ Swap วันหยุด');
    }

    public function test_workspace_loads_when_first_day_attendance_log_already_exists(): void
    {
        AttendanceLog::create([
            'employee_id' => $this->monthlyEmployee->id,
            'log_date' => '2026-04-01',
            'day_type' => 'workday',
        ]);

        $response = $this->actingAs($this->user)->get("/workspace/{$this->monthlyEmployee->id}/4/2026");

        $response->assertStatus(200);
        $this->assertSame(
            1,
            AttendanceLog::where('employee_id', $this->monthlyEmployee->id)
                ->whereDate('log_date', '2026-04-01')
                ->count()
        );
        $this->assertSame(
            30,
            AttendanceLog::where('employee_id', $this->monthlyEmployee->id)
                ->whereMonth('log_date', 4)
                ->whereYear('log_date', 2026)
                ->count()
        );
    }

    public function test_workspace_loads_for_freelance_layer(): void
    {
        $response = $this->actingAs($this->user)->get("/workspace/{$this->freelanceLayerEmployee->id}/4/2026");
        $response->assertStatus(200);
    }

    public function test_workspace_loads_for_freelance_fixed(): void
    {
        $response = $this->actingAs($this->user)->get("/workspace/{$this->freelanceFixedEmployee->id}/4/2026");
        $response->assertStatus(200);
    }

    public function test_workspace_loads_for_youtuber_salary(): void
    {
        $response = $this->actingAs($this->user)->get("/workspace/{$this->youtuberSalaryEmployee->id}/4/2026");
        $response->assertStatus(200);
    }

    public function test_workspace_loads_for_youtuber_settlement(): void
    {
        $response = $this->actingAs($this->user)->get("/workspace/{$this->youtuberSettlementEmployee->id}/4/2026");
        $response->assertStatus(200);
    }

    // ========================================
    // SECTION D: Payroll Calculation Tests
    // ========================================

    public function test_monthly_staff_calculation_basic(): void
    {
        // Create attendance for the month (22 perfect days)
        for ($day = 1; $day <= 22; $day++) {
            $date = sprintf('2026-04-%02d', $day);
            if (date('N', strtotime($date)) > 5) continue; // skip weekends
            AttendanceLog::create([
                'employee_id' => $this->monthlyEmployee->id,
                'log_date' => $date,
                'day_type' => 'workday',
                'check_in' => '09:00',
                'check_out' => '18:00',
                'late_minutes' => 0,
                'ot_minutes' => 0,
                'lwop_flag' => false,
            ]);
        }

        // Enable SSO module
        ModuleToggle::create([
            'employee_id' => $this->monthlyEmployee->id,
            'module_name' => 'sso_deduction',
            'is_enabled' => true,
        ]);

        $service = app(PayrollCalculationService::class);
        $result = $service->calculateForEmployee($this->monthlyEmployee, 4, 2026);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('summary', $result);

        // Check base salary is present
        $baseSalaryItem = collect($result['items'])->firstWhere('item_type_code', 'base_salary');
        $this->assertNotNull($baseSalaryItem);
        $this->assertEquals(30000, (float) $baseSalaryItem['amount']);

        // Check SSO deduction
        $ssoItem = collect($result['items'])->firstWhere('item_type_code', 'sso_employee');
        $this->assertNotNull($ssoItem);
        $this->assertEquals(750, (float) $ssoItem['amount']); // 30000 > 15000 ceiling, so 15000*5% = 750

        // Check diligence (no lates, no LWOP)
        $diligenceItem = collect($result['items'])->firstWhere('item_type_code', 'diligence');
        $this->assertNotNull($diligenceItem);
        $this->assertEquals(500, (float) $diligenceItem['amount']);

        // Net pay check
        $this->assertGreaterThan(0, $result['summary']['net_pay']);
    }

    public function test_monthly_staff_with_late_deduction(): void
    {
        // Create attendance with some lates
        AttendanceLog::create([
            'employee_id' => $this->monthlyEmployee->id,
            'log_date' => '2026-04-01',
            'day_type' => 'workday',
            'check_in' => '09:15',
            'check_out' => '18:00',
            'late_minutes' => 15,
            'ot_minutes' => 0,
            'lwop_flag' => false,
        ]);

        $service = app(PayrollCalculationService::class);
        $result = $service->calculateForEmployee($this->monthlyEmployee, 4, 2026);

        // Should have late deduction
        $lateItem = collect($result['items'])->firstWhere('item_type_code', 'late_deduction');
        $this->assertNotNull($lateItem);
        $this->assertGreaterThan(0, (float) $lateItem['amount']);

        // Diligence should be 0 (was late)
        $diligenceItem = collect($result['items'])->firstWhere('item_type_code', 'diligence');
        $this->assertNotNull($diligenceItem);
        $this->assertEquals(0, (float) $diligenceItem['amount']);
    }

    public function test_monthly_staff_with_lwop(): void
    {
        AttendanceLog::create([
            'employee_id' => $this->monthlyEmployee->id,
            'log_date' => '2026-04-01',
            'day_type' => 'workday',
            'lwop_flag' => true,
            'late_minutes' => 0,
            'ot_minutes' => 0,
        ]);

        $service = app(PayrollCalculationService::class);
        $result = $service->calculateForEmployee($this->monthlyEmployee, 4, 2026);

        $lwopItem = collect($result['items'])->firstWhere('item_type_code', 'lwop');
        $this->assertNotNull($lwopItem);
        // LWOP = base_salary / working_days * lwop_days = 30000/22*1 ≈ 1363.64
        $this->assertGreaterThan(1000, (float) $lwopItem['amount']);
    }

    public function test_freelance_layer_calculation(): void
    {
        // Create work logs with layer data
        WorkLog::create([
            'employee_id' => $this->freelanceLayerEmployee->id,
            'month' => 4,
            'year' => 2026,
            'work_type' => 'Video Edit',
            'layer' => 2,
            'hours' => 1,
            'minutes' => 30,
            'seconds' => 0,
            'quantity' => 0,
            'rate' => 0,
            'amount' => 0,
            'is_disabled' => false,
        ]);
        WorkLog::create([
            'employee_id' => $this->freelanceLayerEmployee->id,
            'month' => 4,
            'year' => 2026,
            'work_type' => 'Audio Mix',
            'layer' => 5,
            'hours' => 0,
            'minutes' => 45,
            'seconds' => 30,
            'quantity' => 0,
            'rate' => 0,
            'amount' => 0,
            'is_disabled' => false,
        ]);

        $service = app(PayrollCalculationService::class);
        $result = $service->calculateForEmployee($this->freelanceLayerEmployee, 4, 2026);

        $this->assertArrayHasKey('summary', $result);
        // Layer 2 → rate 2.50/min, 90 min = 225.00
        // Layer 5 → rate 3.00/min, 45.5 min = 136.50
        // Total should be ~361.50
        $this->assertGreaterThan(300, $result['summary']['total_income']);
    }

    public function test_freelance_fixed_calculation(): void
    {
        WorkLog::create([
            'employee_id' => $this->freelanceFixedEmployee->id,
            'month' => 4,
            'year' => 2026,
            'work_type' => 'Thumbnail',
            'quantity' => 10,
            'rate' => 500,
            'amount' => 0,
            'is_disabled' => false,
        ]);
        WorkLog::create([
            'employee_id' => $this->freelanceFixedEmployee->id,
            'month' => 4,
            'year' => 2026,
            'work_type' => 'Banner',
            'quantity' => 5,
            'rate' => 300,
            'amount' => 0,
            'is_disabled' => false,
        ]);

        $service = app(PayrollCalculationService::class);
        $result = $service->calculateForEmployee($this->freelanceFixedEmployee, 4, 2026);

        // 10*500 + 5*300 = 5000 + 1500 = 6500
        $this->assertEquals(6500, $result['summary']['total_income']);
    }

    public function test_youtuber_salary_calculation(): void
    {
        // Create attendance
        AttendanceLog::create([
            'employee_id' => $this->youtuberSalaryEmployee->id,
            'log_date' => '2026-04-01',
            'day_type' => 'workday',
            'check_in' => '09:00',
            'check_out' => '18:00',
            'late_minutes' => 0,
            'ot_minutes' => 0,
            'lwop_flag' => false,
        ]);

        $service = app(PayrollCalculationService::class);
        $result = $service->calculateForEmployee($this->youtuberSalaryEmployee, 4, 2026);

        $baseSalary = collect($result['items'])->firstWhere('item_type_code', 'base_salary');
        $this->assertNotNull($baseSalary);
        $this->assertEquals(25000, (float) $baseSalary['amount']);
    }

    public function test_youtuber_settlement_calculation(): void
    {
        // Income work log
        WorkLog::create([
            'employee_id' => $this->youtuberSettlementEmployee->id,
            'month' => 4,
            'year' => 2026,
            'work_type' => 'YouTube Revenue',
            'amount' => 50000,
            'entry_type' => 'income',
            'is_disabled' => false,
        ]);
        // Deduction work log
        WorkLog::create([
            'employee_id' => $this->youtuberSettlementEmployee->id,
            'month' => 4,
            'year' => 2026,
            'work_type' => 'Equipment Cost',
            'amount' => 10000,
            'entry_type' => 'deduction',
            'is_disabled' => false,
        ]);

        $service = app(PayrollCalculationService::class);
        $result = $service->calculateForEmployee($this->youtuberSettlementEmployee, 4, 2026);

        $this->assertEquals(50000, $result['summary']['total_income']);
        $this->assertEquals(10000, $result['summary']['total_deduction']);
        $this->assertEquals(40000, $result['summary']['net_pay']);
    }

    // ========================================
    // SECTION E: SSO Calculation Tests
    // ========================================

    public function test_sso_calculation_below_ceiling(): void
    {
        $service = app(SocialSecurityService::class);
        $result = $service->calculate(10000);
        // 10000 * 5% = 500
        $this->assertEquals(500, $result['employee']);
        $this->assertEquals(500, $result['employer']);
    }

    public function test_sso_calculation_above_ceiling(): void
    {
        $service = app(SocialSecurityService::class);
        $result = $service->calculate(50000);
        // Capped at 15000 ceiling → 15000*5% = 750, max contribution = 750
        $this->assertEquals(750, $result['employee']);
        $this->assertEquals(750, $result['employer']);
    }

    public function test_sso_calculation_at_ceiling(): void
    {
        $service = app(SocialSecurityService::class);
        $result = $service->calculate(15000);
        $this->assertEquals(750, $result['employee']);
        $this->assertEquals(750, $result['employer']);
    }

    // ========================================
    // SECTION F: Payroll Save & Payslip Tests
    // ========================================

    public function test_save_payroll_items(): void
    {
        $service = app(PayrollCalculationService::class);
        $result = $service->calculateForEmployee($this->monthlyEmployee, 4, 2026);
        $batch = $service->savePayrollItems($this->monthlyEmployee, 4, 2026, $result);

        $this->assertNotNull($batch);
        $this->assertEquals(4, $batch->month);
        $this->assertEquals(2026, $batch->year);

        // Verify payroll items saved
        $items = PayrollItem::where('employee_id', $this->monthlyEmployee->id)
            ->where('payroll_batch_id', $batch->id)
            ->get();
        $this->assertGreaterThan(0, $items->count());
    }

    public function test_finalize_payslip_creates_snapshot(): void
    {
        $service = app(PayrollCalculationService::class);
        $result = $service->calculateForEmployee($this->monthlyEmployee, 4, 2026);
        $service->savePayrollItems($this->monthlyEmployee, 4, 2026, $result);
        $payslip = $service->finalizePayslip($this->monthlyEmployee, 4, 2026);

        $this->assertNotNull($payslip);
        $this->assertEquals('finalized', $payslip->status);
        $this->assertNotNull($payslip->finalized_at);

        // Verify payslip items (snapshot) exist
        $snapshotItems = PayslipItem::where('payslip_id', $payslip->id)->get();
        $this->assertGreaterThan(0, $snapshotItems->count());

        // Verify totals
        $this->assertGreaterThan(0, (float) $payslip->total_income);
        $this->assertEquals(
            round((float) $payslip->total_income - (float) $payslip->total_deduction, 2),
            round((float) $payslip->net_pay, 2)
        );
    }

    // ========================================
    // SECTION G: Page Load Tests (all routes)
    // ========================================

    public function test_payslip_preview_loads(): void
    {
        // Create payroll data first
        $service = app(PayrollCalculationService::class);
        $result = $service->calculateForEmployee($this->monthlyEmployee, 4, 2026);
        $service->savePayrollItems($this->monthlyEmployee, 4, 2026, $result);

        $response = $this->actingAs($this->user)
            ->get("/payslip/{$this->monthlyEmployee->id}/4/2026/preview");
        $response->assertStatus(200);
    }

    public function test_payslip_finalize_via_route(): void
    {
        $service = app(PayrollCalculationService::class);
        $result = $service->calculateForEmployee($this->monthlyEmployee, 4, 2026);
        $service->savePayrollItems($this->monthlyEmployee, 4, 2026, $result);

        $response = $this->actingAs($this->user)
            ->post("/payslip/{$this->monthlyEmployee->id}/4/2026/finalize");
        $response->assertRedirect();

        $payslip = Payslip::where('employee_id', $this->monthlyEmployee->id)
            ->where('month', 4)->where('year', 2026)->first();
        $this->assertEquals('finalized', $payslip->status);
    }

    public function test_payslip_pdf_download(): void
    {
        $service = app(PayrollCalculationService::class);
        $result = $service->calculateForEmployee($this->monthlyEmployee, 4, 2026);
        $service->savePayrollItems($this->monthlyEmployee, 4, 2026, $result);
        $service->finalizePayslip($this->monthlyEmployee, 4, 2026);

        $response = $this->actingAs($this->user)
            ->get("/payslip/{$this->monthlyEmployee->id}/4/2026/pdf");
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_annual_summary_loads(): void
    {
        $response = $this->actingAs($this->user)->get('/annual?year=2026');
        $response->assertStatus(200);
    }

    public function test_company_finance_loads(): void
    {
        $response = $this->actingAs($this->user)->get('/company/finance?year=2026');
        $response->assertStatus(200);
    }

    public function test_audit_logs_page_loads(): void
    {
        $response = $this->actingAs($this->user)->get('/audit-logs');
        $response->assertStatus(200);
    }

    public function test_calendar_loads(): void
    {
        $response = $this->actingAs($this->user)->get('/calendar/4/2026');
        $response->assertStatus(200);
    }

    public function test_settings_rules_loads(): void
    {
        $response = $this->actingAs($this->user)->get('/settings/rules');
        $response->assertStatus(200);
    }

    public function test_settings_company_loads(): void
    {
        $response = $this->actingAs($this->user)->get('/settings/company');
        $response->assertStatus(200);
    }

    public function test_settings_master_data_loads(): void
    {
        $response = $this->actingAs($this->user)->get('/settings/master-data');
        $response->assertStatus(200);
    }

    public function test_settings_works_loads(): void
    {
        $response = $this->actingAs($this->user)->get('/settings/works');
        $response->assertStatus(200);
    }

    public function test_work_command_loads(): void
    {
        $response = $this->actingAs($this->user)->get('/work');
        $response->assertStatus(200);
    }

    // ========================================
    // SECTION H: Workspace Operations
    // ========================================

    public function test_workspace_recalculate(): void
    {
        $response = $this->actingAs($this->user)
            ->post("/workspace/{$this->monthlyEmployee->id}/4/2026/recalculate");
        $response->assertRedirect();
    }

    public function test_workspace_save_attendance(): void
    {
        // Ensure attendance logs exist
        AttendanceLog::create([
            'employee_id' => $this->monthlyEmployee->id,
            'log_date' => '2026-04-01',
            'day_type' => 'workday',
            'late_minutes' => 0,
            'ot_minutes' => 0,
        ]);

        $log = AttendanceLog::where('employee_id', $this->monthlyEmployee->id)->first();

        $response = $this->actingAs($this->user)
            ->post("/workspace/{$this->monthlyEmployee->id}/4/2026/attendance", [
                'logs' => [
                    [
                        'id' => $log->id,
                        'day_type' => 'workday',
                        'check_in' => '09:00',
                        'check_out' => '18:00',
                        'late_minutes' => 0,
                        'ot_minutes' => 0,
                        'ot_enabled' => false,
                        'lwop_flag' => false,
                    ]
                ],
            ]);
        $response->assertRedirect();
    }

    public function test_workspace_allows_time_entry_and_holiday_ot_calculation(): void
    {
        $log = AttendanceLog::create([
            'employee_id' => $this->monthlyEmployee->id,
            'log_date' => '2026-04-05',
            'day_type' => 'holiday',
            'late_minutes' => 0,
            'ot_minutes' => 0,
            'ot_enabled' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/workspace/{$this->monthlyEmployee->id}/4/2026/attendance-row", [
                'log_id' => $log->id,
                'data' => [
                    'day_type' => 'holiday',
                    'check_in' => '09:00',
                    'check_out' => '18:00',
                    'ot_enabled' => 1,
                ],
            ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('row.ot_minutes', 480);
    }

    public function test_workspace_recalculate_normalizes_stale_ot_minutes_from_old_formula(): void
    {
        AttendanceRule::where('rule_type', 'working_hours')
            ->where('is_active', true)
            ->first()
            ?->update([
                'config' => [
                    'target_check_in' => '09:30',
                    'target_check_out' => '18:30',
                    'target_minutes_per_day' => 540,
                    'lunch_break_minutes' => 60,
                    'working_days_per_month' => 22,
                ],
            ]);

        $log = AttendanceLog::create([
            'employee_id' => $this->monthlyEmployee->id,
            'log_date' => '2026-04-16',
            'day_type' => 'workday',
            'check_in' => '09:20',
            'check_out' => '19:30',
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'ot_minutes' => 70,
            'ot_enabled' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->post("/workspace/{$this->monthlyEmployee->id}/4/2026/recalculate");

        $response->assertRedirect();
        $log->refresh();
        $this->assertSame(10, $log->ot_minutes);
    }

    public function test_workspace_blocks_company_holiday_swap_when_not_exempt(): void
    {
        $log = AttendanceLog::create([
            'employee_id' => $this->monthlyEmployee->id,
            'log_date' => '2026-04-08',
            'day_type' => 'company_holiday',
            'late_minutes' => 0,
            'ot_minutes' => 0,
            'ot_enabled' => false,
        ]);

        $blocked = $this->actingAs($this->user)
            ->postJson("/workspace/{$this->monthlyEmployee->id}/4/2026/attendance-row", [
                'log_id' => $log->id,
                'data' => [
                    'day_type' => 'workday',
                    'check_in' => '09:00',
                    'check_out' => '18:00',
                    'ot_enabled' => 1,
                ],
            ]);

        $blocked->assertStatus(422);
        $blocked->assertJsonPath('ok', false);

        $workingHoursRule = AttendanceRule::where('rule_type', 'working_hours')->where('is_active', true)->first();
        $config = $workingHoursRule->config;
        $config['allow_company_holiday_swap'] = true;
        $workingHoursRule->update(['config' => $config]);

        $allowed = $this->actingAs($this->user)
            ->postJson("/workspace/{$this->monthlyEmployee->id}/4/2026/attendance-row", [
                'log_id' => $log->id,
                'data' => [
                    'day_type' => 'workday',
                    'check_in' => '09:00',
                    'check_out' => '18:00',
                    'ot_enabled' => 1,
                ],
            ]);

        $allowed->assertOk();
        $allowed->assertJsonPath('ok', true);
    }

    public function test_leave_swap_approval_blocks_company_holiday_when_not_exempt(): void
    {
        $swap = DaySwapRequest::create([
            'employee_id' => $this->monthlyEmployee->id,
            'work_date' => '2026-04-13',
            'off_date' => '2026-04-14',
            'reason' => 'test legal policy',
            'status' => 'pending',
            'requested_by' => $this->user->id,
        ]);

        AttendanceLog::create([
            'employee_id' => $this->monthlyEmployee->id,
            'log_date' => '2026-04-13',
            'day_type' => 'company_holiday',
            'late_minutes' => 0,
            'ot_minutes' => 0,
            'ot_enabled' => false,
        ]);

        AttendanceLog::create([
            'employee_id' => $this->monthlyEmployee->id,
            'log_date' => '2026-04-14',
            'day_type' => 'workday',
            'late_minutes' => 0,
            'ot_minutes' => 0,
            'ot_enabled' => false,
        ]);

        $blocked = $this->actingAs($this->user)
            ->patch("/leave/swap/{$swap->id}/review", [
                'action' => 'approved',
            ]);

        $blocked->assertSessionHasErrors('work_date');

        $workingHoursRule = AttendanceRule::where('rule_type', 'working_hours')->where('is_active', true)->first();
        $config = $workingHoursRule->config;
        $config['allow_company_holiday_swap'] = true;
        $workingHoursRule->update(['config' => $config]);

        $allowed = $this->actingAs($this->user)
            ->patch("/leave/swap/{$swap->id}/review", [
                'action' => 'approved',
            ]);

        $allowed->assertRedirect();
        $swap->refresh();
        $this->assertEquals('approved', $swap->status);
    }

    public function test_workspace_toggle_module(): void
    {
        $response = $this->actingAs($this->user)
            ->post("/workspace/{$this->monthlyEmployee->id}/module/toggle", [
                'module_name' => 'sso_deduction',
                'is_enabled' => true,
            ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('module_toggles', [
            'employee_id' => $this->monthlyEmployee->id,
            'module_name' => 'sso_deduction',
            'is_enabled' => true,
        ]);
    }

    public function test_workspace_store_claim(): void
    {
        $response = $this->actingAs($this->user)
            ->post("/workspace/{$this->monthlyEmployee->id}/4/2026/claims", [
                'description' => 'ค่าเดินทาง',
                'amount' => 1500,
                'type' => 'reimbursement',
                'claim_date' => '2026-04-10',
            ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('expense_claims', [
            'employee_id' => $this->monthlyEmployee->id,
            'description' => 'ค่าเดินทาง',
        ]);
    }

    // ========================================
    // SECTION I: Company Finance Operations
    // ========================================

    public function test_store_revenue(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/company/revenue', [
                'source' => 'YouTube',
                'description' => 'Ad revenue',
                'amount' => 100000,
                'month' => 4,
                'year' => 2026,
            ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('company_revenues', ['source' => 'YouTube']);
    }

    public function test_store_expense(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/company/expense', [
                'category' => 'Office',
                'description' => 'ค่าเช่าสำนักงาน',
                'amount' => 30000,
                'month' => 4,
                'year' => 2026,
            ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('company_expenses', ['category' => 'Office']);
    }

    public function test_store_subscription(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/company/subscription', [
                'name' => 'Adobe CC',
                'amount' => 1500,
                'is_recurring' => true,
                'month' => 4,
                'year' => 2026,
            ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('subscription_costs', ['name' => 'Adobe CC']);
    }

    // ========================================
    // SECTION J: Audit Log Tests
    // ========================================

    public function test_audit_log_created_on_employee_create(): void
    {
        $this->actingAs($this->user)->post('/employees', [
            'first_name' => 'Audit',
            'last_name' => 'Test',
            'payroll_mode' => 'monthly_staff',
            'status' => 'active',
            'start_date' => '2026-04-01',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'auditable_type' => Employee::class,
        ]);
    }

    public function test_audit_log_on_module_toggle(): void
    {
        $this->actingAs($this->user)
            ->post("/workspace/{$this->monthlyEmployee->id}/module/toggle", [
                'module_name' => 'sso_deduction',
                'is_enabled' => true,
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => ModuleToggle::class,
        ]);
    }

    // ========================================
    // SECTION K: Payslip Unfinalize Tests
    // ========================================

    public function test_can_unfinalize_payslip(): void
    {
        $service = app(PayrollCalculationService::class);
        $result = $service->calculateForEmployee($this->monthlyEmployee, 4, 2026);
        $service->savePayrollItems($this->monthlyEmployee, 4, 2026, $result);
        $service->finalizePayslip($this->monthlyEmployee, 4, 2026);

        $response = $this->actingAs($this->user)
            ->post("/payslip/{$this->monthlyEmployee->id}/4/2026/unfinalize");
        $response->assertRedirect();

        $payslip = Payslip::where('employee_id', $this->monthlyEmployee->id)
            ->where('month', 4)->where('year', 2026)->first();
        $this->assertEquals('draft', $payslip->status);
    }
}
