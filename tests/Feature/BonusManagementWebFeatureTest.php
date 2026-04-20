<?php

namespace Tests\Feature;

use App\Models\BonusCalculation;
use App\Models\BonusCycle;
use App\Models\Employee;
use App\Models\EmployeeSalaryProfile;
use App\Models\PerformanceTier;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BonusManagementWebFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsAdmin(): User
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        $user = User::create([
            'name' => 'Bonus Admin',
            'email' => 'bonus-admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->roles()->attach($adminRole);

        $this->actingAs($user);

        return $user;
    }

    protected function seedBonusBaseData(): array
    {
        $cycle = BonusCycle::create([
            'cycle_code' => '2026-JUN',
            'cycle_year' => 2026,
            'cycle_period' => 'june',
            'payment_date' => '2026-06-30',
            'max_allocation' => 0.40,
            'status' => 'draft',
        ]);

        $tier = PerformanceTier::create([
            'tier_code' => 'S',
            'tier_name' => 'Excellent',
            'multiplier' => 0.200,
            'display_order' => 1,
            'is_active' => true,
        ]);

        $employee = Employee::create([
            'first_name' => 'Bonus',
            'last_name' => 'Employee',
            'payroll_mode' => 'monthly_staff',
            'status' => 'active',
            'is_active' => true,
            'start_date' => '2025-01-01',
            'probation_end_date' => '2025-12-01',
        ]);

        EmployeeSalaryProfile::create([
            'employee_id' => $employee->id,
            'base_salary' => 30000,
            'effective_date' => '2026-01-01',
            'is_current' => true,
        ]);

        return [$cycle, $tier, $employee];
    }

    public function test_admin_can_view_bonus_manager_page(): void
    {
        $this->actingAsAdmin();
        [$cycle] = $this->seedBonusBaseData();

        $response = $this->get(route('settings.bonus.index', ['cycle_id' => $cycle->id]));

        $response->assertOk();
        $response->assertSee('Bonus Manager');
        $response->assertSee('2026-JUN');
    }

    public function test_admin_can_calculate_and_approve_bonus_from_web(): void
    {
        $this->actingAsAdmin();
        [$cycle, $tier, $employee] = $this->seedBonusBaseData();

        $calcResponse = $this->post(route('settings.bonus.calculate'), [
            'cycle_id' => $cycle->id,
            'employee_id' => $employee->id,
            'base_reference' => 30000,
            'tier_code' => $tier->tier_code,
            'absent_days' => 0,
            'late_count' => 0,
            'leave_days' => 0,
        ]);

        $calcResponse->assertRedirect(route('settings.bonus.index', ['cycle_id' => $cycle->id]));

        $calculation = BonusCalculation::where('employee_id', $employee->id)
            ->where('cycle_id', $cycle->id)
            ->first();

        $this->assertNotNull($calculation);

        $approveResponse = $this->post(route('settings.bonus.approve'), [
            'cycle_id' => $cycle->id,
            'calculation_ids' => [$calculation->id],
        ]);

        $approveResponse->assertRedirect(route('settings.bonus.index', ['cycle_id' => $cycle->id]));

        $calculation->refresh();
        $this->assertSame('approved', $calculation->status);
    }
}
