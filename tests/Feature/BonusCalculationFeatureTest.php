<?php

namespace Tests\Feature;

use App\Models\BonusAuditLog;
use App\Models\BonusCalculation;
use App\Models\BonusCycle;
use App\Models\Employee;
use App\Models\PerformanceTier;
use App\Models\User;
use App\Services\BonusCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BonusCalculationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected BonusCalculationService $service;
    protected Employee $employee;
    protected BonusCycle $juneCycle;
    protected BonusCycle $decCycle;
    protected PerformanceTier $tierS;
    protected PerformanceTier $tierA;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BonusCalculationService::class);

        // Create user for auth
        $this->user = User::create([
            'name' => 'Test Admin',
            'email' => 'testadmin@example.com',
            'password' => bcrypt('password'),
        ]);

        // Seed performance tiers
        $this->tierS = PerformanceTier::create([
            'tier_code' => 'S', 'tier_name' => 'Excellent',
            'multiplier' => 0.200, 'description' => 'Exceeds expectations',
            'display_order' => 2, 'is_active' => true,
        ]);
        $this->tierA = PerformanceTier::create([
            'tier_code' => 'A', 'tier_name' => 'Good',
            'multiplier' => 0.100, 'description' => 'Meets expectations',
            'display_order' => 3, 'is_active' => true,
        ]);
        PerformanceTier::create([
            'tier_code' => 'SS', 'tier_name' => 'Exceptional',
            'multiplier' => 0.300, 'description' => 'Outstanding performance',
            'display_order' => 1, 'is_active' => true,
        ]);
        PerformanceTier::create([
            'tier_code' => 'B', 'tier_name' => 'Below Average',
            'multiplier' => -0.100, 'description' => 'Needs improvement',
            'display_order' => 4, 'is_active' => true,
        ]);
        PerformanceTier::create([
            'tier_code' => 'C', 'tier_name' => 'Poor',
            'multiplier' => -0.200, 'description' => 'Underperforming',
            'display_order' => 5, 'is_active' => true,
        ]);

        // Create cycles
        $this->juneCycle = BonusCycle::create([
            'cycle_code' => '2026-JUN', 'cycle_year' => 2026,
            'cycle_period' => 'june', 'payment_date' => '2026-06-30',
            'max_allocation' => 0.40, 'status' => 'draft',
        ]);
        $this->decCycle = BonusCycle::create([
            'cycle_code' => '2026-DEC', 'cycle_year' => 2026,
            'cycle_period' => 'december', 'payment_date' => '2026-12-31',
            'max_allocation' => 0.60, 'status' => 'draft',
        ]);

        // Create employee — probation ended 2025-12-30 (6 months to June 30, 12 months to Dec 31)
        $this->employee = Employee::create([
            'first_name' => 'Test', 'last_name' => 'Employee',
            'payroll_mode' => 'monthly_staff', 'status' => 'active',
            'is_active' => true, 'start_date' => '2025-09-01',
            'probation_end_date' => '2025-12-30',
        ]);
    }

    // ─── Service Integration Tests ───────────────────────────────────────

    public function test_calculate_and_store_creates_record(): void
    {
        $this->actingAs($this->user);

        $calc = $this->service->calculateAndStore(
            $this->employee->id, $this->juneCycle->id,
            30000.00, 'S', 0.0,
        );

        $this->assertDatabaseHas('bonus_calculations', [
            'employee_id' => $this->employee->id,
            'cycle_id'    => $this->juneCycle->id,
        ]);

        // 30000 * 1.2 = 36000 (tier S +20%)
        $this->assertEquals(36000.00, (float) $calc->tier_adjusted_bonus);
        $this->assertEquals(36000.00, (float) $calc->final_bonus_net);

        // 6 months after probation, June: 6/6 * 0.4 = 0.4
        $this->assertEquals(6, $calc->months_after_probation);
        $this->assertEquals(0.4, (float) $calc->unlock_percentage);

        // 36000 * 0.4 = 14400
        $this->assertEquals(14400.00, (float) $calc->actual_payment);
    }

    public function test_calculate_and_store_creates_audit_log(): void
    {
        $this->actingAs($this->user);

        $calc = $this->service->calculateAndStore(
            $this->employee->id, $this->juneCycle->id,
            30000.00, 'A', 0.0,
        );

        $this->assertDatabaseHas('bonus_audit_logs', [
            'calculation_id' => $calc->id,
            'action_type'    => 'created',
        ]);
    }

    public function test_calculate_inactive_employee_gets_zero_payment(): void
    {
        $inactive = Employee::create([
            'first_name' => 'Inactive', 'last_name' => 'Employee',
            'payroll_mode' => 'monthly_staff', 'status' => 'terminated',
            'is_active' => false, 'start_date' => '2025-01-01',
            'probation_end_date' => '2025-04-01',
        ]);

        $result = $this->service->calculate(
            $inactive->id, $this->juneCycle->id,
            30000.00, 'A', 0.0,
        );

        $this->assertFalse($result['is_active_on_payment']);
        $this->assertEquals(0.0, $result['actual_payment']);
        $this->assertEquals(0.0, $result['unlock_percentage']);
    }

    public function test_calculate_with_attendance_adjustment(): void
    {
        $result = $this->service->calculate(
            $this->employee->id, $this->juneCycle->id,
            30000.00, 'S', -0.05,
        );

        // 30000 * 1.2 = 36000, then * 0.95 = 34200
        $this->assertEquals(36000.00, $result['tier_adjusted_bonus']);
        $this->assertEquals(34200.00, $result['final_bonus_net']);
        // 6 months, June: 6/6 * 0.4 = 0.4, 34200 * 0.4 = 13680
        $this->assertEquals(0.4, $result['unlock_percentage']);
        $this->assertEquals(13680.00, $result['actual_payment']);
    }

    public function test_calculate_before_probation_ends_gets_zero(): void
    {
        $newHire = Employee::create([
            'first_name' => 'New', 'last_name' => 'Hire',
            'payroll_mode' => 'monthly_staff', 'status' => 'active',
            'is_active' => true, 'start_date' => '2026-05-01',
            'probation_end_date' => '2026-08-01', // after June payment
        ]);

        $result = $this->service->calculate(
            $newHire->id, $this->juneCycle->id,
            25000.00, 'A', 0.0,
        );

        $this->assertEquals(0, $result['months_after_probation']);
        $this->assertEquals(0.0, $result['unlock_percentage']);
        $this->assertEquals(0.0, $result['actual_payment']);
    }

    public function test_december_calculation_subtracts_june_ratio(): void
    {
        $this->actingAs($this->user);

        // First: June calculation
        $this->service->calculateAndStore(
            $this->employee->id, $this->juneCycle->id,
            30000.00, 'S', 0.0,
        );

        // Then: December calculation
        $decCalc = $this->service->calculateAndStore(
            $this->employee->id, $this->decCycle->id,
            30000.00, 'S', 0.0,
        );

        // 12 months after probation by Dec, unlock = 12/12 - 0.4 (June) = 0.6
        $this->assertEquals(0.6, (float) $decCalc->unlock_percentage);
        // 36000 * 0.6 = 21600
        $this->assertEquals(21600.00, (float) $decCalc->actual_payment);

        // Total for year = 14400 + 21600 = 36000 (= 100% of tier-adjusted)
        $juneCalc = BonusCalculation::where('employee_id', $this->employee->id)
            ->where('cycle_id', $this->juneCycle->id)->first();
        $total = (float) $juneCalc->actual_payment + (float) $decCalc->actual_payment;
        $this->assertEquals(36000.00, $total);
    }

    public function test_batch_calculate_processes_multiple_employees(): void
    {
        $this->actingAs($this->user);

        $this->service->setCycleSelectedMonths($this->juneCycle->id, ['2026-01', '2026-02', '2026-03'], $this->user->name);

        $emp2 = Employee::create([
            'first_name' => 'Second', 'last_name' => 'Person',
            'payroll_mode' => 'monthly_staff', 'status' => 'active',
            'is_active' => true, 'start_date' => '2025-07-01',
            'probation_end_date' => '2025-10-01',
        ]);

        $result = $this->service->batchCalculate($this->juneCycle->id, [
            ['employee_id' => $this->employee->id, 'base_reference' => 30000, 'tier_id' => 'S', 'attendance_adjustment' => 0],
            ['employee_id' => $emp2->id, 'base_reference' => 35000, 'tier_id' => 'A', 'attendance_adjustment' => -0.02],
        ]);

        $this->assertEquals(2, $result['total_employees']);
        $this->assertCount(2, $result['calculations']);

        // Cycle status should be 'calculated'
        $this->juneCycle->refresh();
        $this->assertEquals('calculated', $this->juneCycle->status);
    }

    public function test_approve_marks_calculations_as_approved(): void
    {
        $this->actingAs($this->user);

        $calc = $this->service->calculateAndStore(
            $this->employee->id, $this->juneCycle->id,
            30000.00, 'S', 0.0,
        );

        $result = $this->service->approve(
            $this->juneCycle->id,
            [$calc->id],
            'admin_user',
        );

        $this->assertEquals(1, $result['approved_count']);

        $calc->refresh();
        $this->assertEquals('approved', $calc->status);
        $this->assertEquals('admin_user', $calc->approved_by);
        $this->assertNotNull($calc->approved_at);

        // Audit log for approval
        $this->assertDatabaseHas('bonus_audit_logs', [
            'calculation_id' => $calc->id,
            'action_type'    => 'approved',
        ]);
    }

    public function test_employee_history_returns_correct_data(): void
    {
        $this->actingAs($this->user);

        $this->service->calculateAndStore(
            $this->employee->id, $this->juneCycle->id,
            30000.00, 'S', 0.0,
        );

        $history = $this->service->getEmployeeHistory($this->employee->id);

        $this->assertEquals(1, $history['total_cycles']);
        $this->assertEquals(14400.00, $history['lifetime_bonus']);
        $this->assertCount(1, $history['cycles']);
        $this->assertEquals('2026-JUN', $history['cycles'][0]['cycle_id']);
    }

    public function test_update_existing_calculation_creates_modified_audit(): void
    {
        $this->actingAs($this->user);

        // First calculation
        $this->service->calculateAndStore(
            $this->employee->id, $this->juneCycle->id,
            30000.00, 'S', 0.0,
        );

        // Re-calculate with different tier
        $this->service->calculateAndStore(
            $this->employee->id, $this->juneCycle->id,
            30000.00, 'A', 0.0,
        );

        $logs = BonusAuditLog::where('action_type', 'modified')->get();
        $this->assertGreaterThanOrEqual(1, $logs->count());
    }

    public function test_batch_calculate_rejects_paid_cycle(): void
    {
        $paidCycle = BonusCycle::create([
            'cycle_code' => '2025-JUN', 'cycle_year' => 2025,
            'cycle_period' => 'june', 'payment_date' => '2025-06-30',
            'max_allocation' => 0.40, 'status' => 'paid',
        ]);

        $this->expectException(\DomainException::class);

        $this->service->batchCalculate($paidCycle->id, [
            ['employee_id' => $this->employee->id, 'base_reference' => 30000, 'tier_id' => 'S'],
        ]);
    }

    // ─── Validation Tests ────────────────────────────────────────────────

    public function test_validate_input_catches_invalid_employee(): void
    {
        $errors = $this->service->validateInput([
            'employee_id' => 99999,
            'cycle_id'    => $this->juneCycle->id,
            'tier_id'     => 'S',
            'base_reference' => 30000,
            'attendance_adjustment' => 0,
        ]);

        $this->assertContains('Employee not found', $errors);
    }

    public function test_validate_input_catches_paid_cycle(): void
    {
        $paidCycle = BonusCycle::create([
            'cycle_code' => '2025-DEC', 'cycle_year' => 2025,
            'cycle_period' => 'december', 'payment_date' => '2025-12-31',
            'max_allocation' => 0.60, 'status' => 'paid',
        ]);

        $errors = $this->service->validateInput([
            'employee_id' => $this->employee->id,
            'cycle_id'    => $paidCycle->id,
            'tier_id'     => 'S',
            'base_reference' => 30000,
            'attendance_adjustment' => 0,
        ]);

        $this->assertContains('Cycle already paid', $errors);
    }

    public function test_validate_input_catches_negative_base_reference(): void
    {
        $errors = $this->service->validateInput([
            'employee_id' => $this->employee->id,
            'cycle_id'    => $this->juneCycle->id,
            'tier_id'     => 'S',
            'base_reference' => -100,
            'attendance_adjustment' => 0,
        ]);

        $this->assertContains('Base reference must be positive', $errors);
    }

    public function test_validate_input_catches_out_of_range_adjustment(): void
    {
        $errors = $this->service->validateInput([
            'employee_id' => $this->employee->id,
            'cycle_id'    => $this->juneCycle->id,
            'tier_id'     => 'S',
            'base_reference' => 30000,
            'attendance_adjustment' => -1.5,
        ]);

        $this->assertContains('Attendance adjustment out of range', $errors);
    }

    // ─── API Endpoint Tests ──────────────────────────────────────────────

    public function test_api_calculate_endpoint(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/bonus/calculate', [
            'employee_id'           => $this->employee->id,
            'cycle_id'              => $this->juneCycle->id,
            'base_reference'        => 30000.00,
            'tier_id'               => 'S',
            'attendance_adjustment' => -0.05,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $json = $response->json('calculation');
        $this->assertEquals(36000.00, $json['tier_adjusted_bonus']);
        $this->assertEquals(34200.00, $json['final_bonus_net']);
        $this->assertEquals(0.4, $json['unlock_percentage']);
        $this->assertEquals(13680.00, $json['actual_payment']);
    }

    public function test_api_calculate_validation_rejects_missing_fields(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/bonus/calculate', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['employee_id', 'cycle_id', 'base_reference']);
    }

    public function test_api_cycle_months_can_be_selected_and_listed(): void
    {
        $this->actingAs($this->user);

        $update = $this->putJson("/api/bonus/cycle/{$this->juneCycle->id}/months", [
            'months' => ['2026-01', '2026-03', '2026-06'],
        ]);

        $update->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.selected_count', 3);

        $list = $this->getJson("/api/bonus/cycle/{$this->juneCycle->id}/months");
        $list->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.selected_count', 3);
    }

    public function test_api_batch_calculate_requires_month_selection_before_processing(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/bonus/batch-calculate', [
            'cycle_id'  => $this->juneCycle->id,
            'employees' => [
                ['employee_id' => $this->employee->id, 'base_reference' => 30000, 'tier_id' => 'S', 'attendance_adjustment' => 0],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_api_batch_calculate_endpoint(): void
    {
        $this->actingAs($this->user);

        $this->service->setCycleSelectedMonths($this->juneCycle->id, ['2026-01', '2026-02', '2026-03'], $this->user->name);

        $emp2 = Employee::create([
            'first_name' => 'Other', 'last_name' => 'Person',
            'payroll_mode' => 'monthly_staff', 'status' => 'active',
            'is_active' => true, 'start_date' => '2025-07-01',
            'probation_end_date' => '2025-10-01',
        ]);

        $response = $this->postJson('/api/bonus/batch-calculate', [
            'cycle_id'  => $this->juneCycle->id,
            'employees' => [
                ['employee_id' => $this->employee->id, 'base_reference' => 30000, 'tier_id' => 'S', 'attendance_adjustment' => 0],
                ['employee_id' => $emp2->id, 'base_reference' => 35000, 'tier_id' => 'A', 'attendance_adjustment' => -0.02],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('total_employees', 2);
    }

    public function test_api_employee_history_endpoint(): void
    {
        $this->actingAs($this->user);

        $this->service->calculateAndStore(
            $this->employee->id, $this->juneCycle->id,
            30000.00, 'S', 0.0,
        );

        $response = $this->getJson("/api/bonus/employee/{$this->employee->id}/history");

        $response->assertOk()
            ->assertJsonPath('total_cycles', 1);
        $this->assertEquals(14400.00, $response->json('lifetime_bonus'));
    }

    public function test_api_approve_endpoint(): void
    {
        $this->actingAs($this->user);

        $calc = $this->service->calculateAndStore(
            $this->employee->id, $this->juneCycle->id,
            30000.00, 'S', 0.0,
        );

        $response = $this->postJson('/api/bonus/approve', [
            'cycle_id'        => $this->juneCycle->id,
            'approved_by'     => 'admin_user',
            'calculation_ids' => [$calc->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'approved')
            ->assertJsonPath('approved_count', 1);
    }

    public function test_api_cycle_summary_endpoint(): void
    {
        $this->actingAs($this->user);

        $this->service->calculateAndStore(
            $this->employee->id, $this->juneCycle->id,
            30000.00, 'S', 0.0,
        );

        $response = $this->getJson("/api/bonus/cycle/{$this->juneCycle->id}/summary");

        $response->assertOk()
            ->assertJsonPath('cycle', '2026-JUN')
            ->assertJsonPath('total_employees', 1)
            ->assertJsonStructure([
                'cycle', 'status', 'total_employees', 'total_payment',
                'calculations', 'tier_distribution',
            ]);
    }

    // ─── Full Scenario: June + December for same employee ────────────────

    public function test_full_year_scenario(): void
    {
        $this->actingAs($this->user);

        // June: tier S, no attendance issues
        $juneCalc = $this->service->calculateAndStore(
            $this->employee->id, $this->juneCycle->id,
            30000.00, 'S', 0.0,
        );

        // 30000 * 1.2 = 36000, 6 months: 6/6 * 0.4 = 0.4, 36000 * 0.4 = 14400
        $this->assertEquals(36000.00, (float) $juneCalc->tier_adjusted_bonus);
        $this->assertEquals(6, $juneCalc->months_after_probation);
        $this->assertEquals(0.4, (float) $juneCalc->unlock_percentage);
        $this->assertEquals(14400.00, (float) $juneCalc->actual_payment);

        // December: same tier, slight attendance deduction
        $decCalc = $this->service->calculateAndStore(
            $this->employee->id, $this->decCycle->id,
            30000.00, 'S', -0.03,
        );

        // 12 months: 36000 * 0.97 = 34920, unlock = 12/12 - 0.4 = 0.6
        $this->assertEquals(12, $decCalc->months_after_probation);
        $this->assertEquals(34920.00, (float) $decCalc->final_bonus_net);
        $this->assertEquals(0.6, (float) $decCalc->unlock_percentage);
        $this->assertEquals(20952.00, (float) $decCalc->actual_payment);

        // Year total
        $yearTotal = (float) $juneCalc->actual_payment + (float) $decCalc->actual_payment;
        $this->assertEquals(35352.00, $yearTotal);

        // Both calculations stored
        $this->assertEquals(2, BonusCalculation::where('employee_id', $this->employee->id)->count());

        // Audit trail complete
        $this->assertEquals(2, BonusAuditLog::count());
    }
}
