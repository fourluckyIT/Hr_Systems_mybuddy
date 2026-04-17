<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSalaryProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessFlowTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_EMAIL = 'admin-role@test.local';
    private const OWNER_EMAIL = 'owner-role@test.local';
    private const PASSWORD = 'password123';

    protected User $adminUser;
    protected User $ownerUser;
    protected Employee $monthlyEmployee;
    protected Employee $freelanceLayerEmployee;
    protected Employee $freelanceFixedEmployee;
    protected Employee $ownerEmployee;
    protected Employee $youtuberSettlementEmployee;
    protected Employee $otherEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'admin', 'display_name' => 'Administrator']);
        $ownerRole = Role::create(['name' => 'owner', 'display_name' => 'Employee / Owner']);

        $this->adminUser = User::create([
            'name' => 'Admin Tester',
            'email' => self::ADMIN_EMAIL,
            'password' => bcrypt(self::PASSWORD),
        ]);
        $this->adminUser->roles()->attach($adminRole);

        $this->ownerUser = User::create([
            'name' => 'Owner Tester',
            'email' => self::OWNER_EMAIL,
            'password' => bcrypt(self::PASSWORD),
        ]);
        $this->ownerUser->roles()->attach($ownerRole);

        $this->ownerEmployee = Employee::create([
            'user_id' => $this->ownerUser->id,
            'first_name' => 'Owner',
            'last_name' => 'Tester',
            'payroll_mode' => 'youtuber_salary',
            'status' => 'active',
            'is_active' => true,
            'start_date' => '2024-01-01',
        ]);

        EmployeeSalaryProfile::create([
            'employee_id' => $this->ownerEmployee->id,
            'base_salary' => 20000,
            'effective_date' => '2024-01-01',
            'is_current' => true,
        ]);

        $this->monthlyEmployee = Employee::create([
            'first_name' => 'Other',
            'last_name' => 'Monthly',
            'payroll_mode' => 'monthly_staff',
            'status' => 'active',
            'is_active' => true,
            'start_date' => '2024-01-01',
        ]);

        $this->freelanceLayerEmployee = Employee::create([
            'first_name' => 'Other',
            'last_name' => 'Layer',
            'payroll_mode' => 'freelance_layer',
            'status' => 'active',
            'is_active' => true,
            'start_date' => '2024-01-01',
        ]);

        $this->freelanceFixedEmployee = Employee::create([
            'first_name' => 'Other',
            'last_name' => 'Fixed',
            'payroll_mode' => 'freelance_fixed',
            'status' => 'active',
            'is_active' => true,
            'start_date' => '2024-01-01',
        ]);

        $this->youtuberSettlementEmployee = Employee::create([
            'first_name' => 'Other',
            'last_name' => 'Settlement',
            'payroll_mode' => 'youtuber_settlement',
            'status' => 'active',
            'is_active' => true,
            'start_date' => '2024-01-01',
        ]);

        $this->otherEmployee = $this->monthlyEmployee;
    }

    public function test_guest_is_redirected_to_login_for_protected_pages(): void
    {
        $this->get('/employees')->assertRedirect('/login');
        $this->get('/my/workspace')->assertRedirect('/login');
        $this->get('/workspace/' . $this->ownerEmployee->id . '/4/2026')->assertRedirect('/login');
    }

    public function test_admin_step_flow_can_access_all_admin_pages(): void
    {
        $this->post('/login', [
            'email' => self::ADMIN_EMAIL,
            'password' => self::PASSWORD,
        ])->assertRedirect('/employees');

        $this->actingAs($this->adminUser)->get('/employees')->assertOk();
        $this->actingAs($this->adminUser)->get('/calendar')->assertOk();
        $this->actingAs($this->adminUser)->get('/leave')->assertOk();
        $this->actingAs($this->adminUser)->get('/company/finance')->assertOk();
        $this->actingAs($this->adminUser)->get('/annual')->assertOk();
        $this->actingAs($this->adminUser)->get('/work')->assertOk();
        $this->actingAs($this->adminUser)->get('/audit-logs')->assertOk();
        $this->actingAs($this->adminUser)->get('/settings/rules')->assertOk();
        $this->actingAs($this->adminUser)->get('/settings/master-data')->assertOk();
    }

    public function test_owner_step_flow_is_restricted_to_owner_pages_only(): void
    {
        $this->post('/login', [
            'email' => self::OWNER_EMAIL,
            'password' => self::PASSWORD,
        ])->assertRedirect('/my/workspace');

        $this->actingAs($this->ownerUser)->get('/my/workspace')->assertRedirect('/workspace/' . $this->ownerEmployee->id . '/' . now()->month . '/' . now()->year);
        $this->actingAs($this->ownerUser)->get('/workspace/' . $this->ownerEmployee->id . '/4/2026')->assertOk();
        $this->actingAs($this->ownerUser)->get('/calendar')->assertOk();
        $this->actingAs($this->ownerUser)->get('/leave')->assertOk();
        $this->actingAs($this->ownerUser)->get('/payslip/' . $this->ownerEmployee->id . '/4/2026/preview')->assertOk();

        $this->actingAs($this->ownerUser)->get('/employees')->assertForbidden();
        $this->actingAs($this->ownerUser)->get('/company/finance')->assertForbidden();
        $this->actingAs($this->ownerUser)->get('/annual')->assertForbidden();
        $this->actingAs($this->ownerUser)->get('/audit-logs')->assertForbidden();
        $this->actingAs($this->ownerUser)->get('/settings/rules')->assertForbidden();
        $this->actingAs($this->ownerUser)->get('/work')->assertForbidden();
    }

    public function test_admin_navigation_labels_are_visible_and_clickable(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/employees');

        $response->assertOk();
        $response->assertSeeText('พนักงาน');
        $response->assertSeeText('ปฏิทินหลัก');
        $response->assertSeeText('การเงินบริษัท');
        $response->assertSeeText('สรุปรายปี');
        $response->assertSeeText('WORK Center');
        $response->assertSeeText('Audit Log');
        $response->assertSeeText('Master Data');
        $response->assertSeeText('ตั้งค่า');

        $this->actingAs($this->adminUser)->get('/calendar')->assertOk();
        $this->actingAs($this->adminUser)->get('/company/finance')->assertOk();
        $this->actingAs($this->adminUser)->get('/annual')->assertOk();
        $this->actingAs($this->adminUser)->get('/work')->assertOk();
        $this->actingAs($this->adminUser)->get('/audit-logs')->assertOk();
        $this->actingAs($this->adminUser)->get('/settings/master-data')->assertOk();
        $this->actingAs($this->adminUser)->get('/settings/rules')->assertOk();
    }

    public function test_owner_navigation_shows_only_owner_menu_items(): void
    {
        $response = $this->actingAs($this->ownerUser)
            ->get('/workspace/' . $this->ownerEmployee->id . '/4/2026');

        $response->assertOk();
        $response->assertSeeText('My Workspace');
        $response->assertSeeText('ปฏิทินหลัก');

        $response->assertDontSee('>พนักงาน<', false);
        $response->assertDontSee('>การเงินบริษัท<', false);
        $response->assertDontSee('>สรุปรายปี<', false);
        $response->assertDontSee('>WORK Center<', false);
        $response->assertDontSee('>Audit Log<', false);
        $response->assertDontSee('>Master Data<', false);
        $response->assertDontSee('>ตั้งค่า<', false);
    }

    public function test_workspace_main_section_renders_correctly_for_each_payroll_mode(): void
    {
        $monthly = $this->actingAs($this->adminUser)
            ->get('/workspace/' . $this->monthlyEmployee->id . '/4/2026');
        $monthly->assertOk();
        $monthly->assertSeeText('ตารางเข้างาน');
        $monthly->assertSeeText('Assigned Edit Jobs');

        $layer = $this->actingAs($this->adminUser)
            ->get('/workspace/' . $this->freelanceLayerEmployee->id . '/4/2026');
        $layer->assertOk();
        $layer->assertSeeText('ฟรีแลนซ์ เรทเลเยอร์');
        $layer->assertSeeText('งานที่ได้รับมอบหมาย');

        $fixed = $this->actingAs($this->adminUser)
            ->get('/workspace/' . $this->freelanceFixedEmployee->id . '/4/2026');
        $fixed->assertOk();
        $fixed->assertSeeText('ฟรีแลนซ์ ฟิกเรท');
        $fixed->assertSeeText('งานที่ได้รับมอบหมาย');

        $youtuberSettlement = $this->actingAs($this->adminUser)
            ->get('/workspace/' . $this->youtuberSettlementEmployee->id . '/4/2026');
        $youtuberSettlement->assertOk();
        $youtuberSettlement->assertSeeText('YouTuber Settlement — รายรับ-รายจ่าย');
        $youtuberSettlement->assertDontSeeText('Assigned Edit Jobs');

        $youtuberSalary = $this->actingAs($this->adminUser)
            ->get('/workspace/' . $this->ownerEmployee->id . '/4/2026');
        $youtuberSalary->assertOk();
        $youtuberSalary->assertSeeText('YouTuber เงินเดือน — ยอดคงที่รายเดือน');
        $youtuberSalary->assertDontSeeText('Assigned Edit Jobs');
    }

    public function test_owner_cannot_access_other_employee_workspace(): void
    {
        $this->actingAs($this->ownerUser)
            ->get('/workspace/' . $this->otherEmployee->id . '/4/2026')
            ->assertForbidden();
    }

    public function test_youtuber_workspace_does_not_render_assigned_edit_jobs_card(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get('/workspace/' . $this->ownerEmployee->id . '/4/2026');

        $response->assertOk();
        $response->assertDontSee('Assigned Edit Jobs');
    }

    public function test_owner_cannot_see_finalize_button_on_payslip_preview(): void
    {
        $previewUrl = '/payslip/' . $this->ownerEmployee->id . '/4/2026/preview';
        $finalizeUrl = '/payslip/' . $this->ownerEmployee->id . '/4/2026/finalize';

        $response = $this->actingAs($this->ownerUser)->get($previewUrl);

        $response->assertOk();
        $response->assertDontSee($finalizeUrl);
    }
}
