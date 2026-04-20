<?php

namespace Tests\Feature;

use App\Models\AttendanceRule;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsEnhancementFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsAdmin(): User
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        $user = User::create([
            'name' => 'Settings Admin',
            'email' => 'settings-admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->roles()->attach($adminRole);

        $this->actingAs($user);

        return $user;
    }

    public function test_admin_can_update_module_defaults_rule(): void
    {
        $this->actingAsAdmin();

        $response = $this->patch(route('settings.rules.update', 'module_defaults'), [
            'enable_overtime' => '1',
            'enable_diligence' => '0',
            'default_sso_deduction' => '1',
            'default_deduct_late' => '0',
            'default_deduct_early' => '1',
        ]);

        $response->assertRedirect();

        $rule = AttendanceRule::where('rule_type', 'module_defaults')->where('is_active', true)->first();
        $this->assertNotNull($rule);

        $this->assertTrue((bool) ($rule->config['enable_overtime'] ?? false));
        $this->assertFalse((bool) ($rule->config['enable_diligence'] ?? true));
        $this->assertTrue((bool) ($rule->config['default_sso_deduction'] ?? false));
        $this->assertFalse((bool) ($rule->config['default_deduct_late'] ?? true));
        $this->assertTrue((bool) ($rule->config['default_deduct_early'] ?? false));
    }

    public function test_master_data_page_contains_job_stages_tab(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('settings.master-data'));
        $response->assertOk();
        $response->assertSee('Job Stages');
        $response->assertSee('เพิ่มสถานะงานใหม่');
    }
}
