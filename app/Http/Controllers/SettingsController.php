<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRule;
use App\Models\CompanyHoliday;
use App\Models\SocialSecurityConfig;
use App\Services\HolidayService;
use Illuminate\Http\Request;
use App\Models\CompanyProfile;
use App\Services\AuditLogService;

class SettingsController extends Controller
{
    public function __construct(
        protected HolidayService $holidayService
    ) {}

    public function rules()
    {
        $ssoConfig = SocialSecurityConfig::getCurrentConfig();
        
        $rules = [
            'working_hours' => AttendanceRule::getActiveRule('working_hours'),
            'diligence' => AttendanceRule::getActiveRule('diligence'),
            'late_deduction' => AttendanceRule::getActiveRule('late_deduction'),
            'ot_rate' => AttendanceRule::getActiveRule('ot_rate'),
            'module_defaults' => AttendanceRule::getActiveRule('module_defaults'),
            'social_security_config' => $ssoConfig,
        ];

        $holidays = CompanyHoliday::orderBy('holiday_date', 'asc')->get();

        return view('settings.rules', compact('rules', 'holidays'));
    }

    public function updateRule(Request $request, string $type)
    {
        if ($type === 'social_security') {
            $sso = SocialSecurityConfig::where('is_active', true)->orderBy('effective_date', 'desc')->first();
            if (!$sso) {
                $sso = new SocialSecurityConfig(['is_active' => true, 'effective_date' => now()->startOfMonth()]);
            }

            $oldSso = $sso->getAttributes();

            $sso->update([
                'salary_ceiling' => $request->input('salary_ceiling'),
                'employee_rate' => $request->input('employee_contribution_rate'),
                'employer_rate' => $request->input('employer_contribution_rate'),
                'max_contribution' => ($request->input('salary_ceiling') * $request->input('employee_contribution_rate')) / 100,
            ]);

            AuditLogService::log($sso, 'updated', 'sso_config', $oldSso, $sso->getAttributes(), 'SSO config updated');

            return back()->with('success', 'อัปเดตตั้งค่าประกันสังคมสำเร็จ');
        }

        if ($type === 'module_defaults') {
            $rule = AttendanceRule::where('rule_type', $type)->where('is_active', true)->first();

            if (!$rule) {
                $rule = AttendanceRule::create([
                    'rule_type' => 'module_defaults',
                    'config' => [],
                    'effective_date' => now()->toDateString(),
                    'is_active' => true,
                ]);
            }

            $oldConfig = $rule->config ?? [];
            $config = [
                'enable_overtime' => $request->boolean('enable_overtime', true),
                'enable_diligence' => $request->boolean('enable_diligence', true),
                'default_sso_deduction' => $request->boolean('default_sso_deduction', true),
                'default_deduct_late' => $request->boolean('default_deduct_late', true),
                'default_deduct_early' => $request->boolean('default_deduct_early', true),
            ];

            $rule->update(['config' => $config]);
            AuditLogService::log($rule, 'updated', 'config', $oldConfig, $config, "Rule '{$type}' updated");

            return back()->with('success', 'อัปเดตค่าเริ่มต้นของโมดูลสำเร็จ');
        }

        $rule = AttendanceRule::where('rule_type', $type)->where('is_active', true)->first();
        
        if (!$rule) {
            return back()->withErrors(['rule' => "Rule type $type not found."]);
        }

        $config = $rule->config;
        $inputs = $request->except(['_token', '_method']);

        if ($type === 'late_deduction' && !isset($inputs['type'])) {
            $inputs['type'] = 'per_minute';
        }

        // Special handling for Tiered Diligence
        if ($type === 'diligence') {
            // If the request has 'tiers', it's the new multi-tier format
            if ($request->has('tiers')) {
                $inputs['tiers'] = $request->input('tiers');
                // Auto-cleanup: remove empty tiers
                $inputs['tiers'] = array_filter($inputs['tiers'], fn($t) => is_numeric($t['amount']));
                $inputs['tiers'] = array_values($inputs['tiers']);
                $inputs['use_tiers'] = true;
            } else {
                $inputs['require_zero_late'] = $request->has('require_zero_late');
                $inputs['require_zero_lwop'] = $request->has('require_zero_lwop');
                $inputs['use_tiers'] = false;
            }
        }

        $oldConfig = $rule->config;

        foreach ($inputs as $key => $value) {
            $config[$key] = $value;
        }

        $rule->update(['config' => $config]);

        AuditLogService::log($rule, 'updated', 'config', $oldConfig, $config, "Rule '{$type}' updated");

        return back()->with('success', 'อัปเดตกฎการทำงานสำเร็จ');
    }

    public function loadLegalHolidays(Request $request)
    {
        $year = $request->input('year', 2026);
        $holidays = $this->holidayService->getThaiPublicHolidays($year);
        $count = 0;

        foreach ($holidays as $h) {
            $exists = CompanyHoliday::where('holiday_date', $h['date'])->exists();
            if (!$exists) {
                CompanyHoliday::create([
                    'holiday_date' => $h['date'],
                    'name' => $h['name'],
                    'is_active' => true
                ]);
                $count++;
            }
        }

        return back()->with('success', "ดึงข้อมูลวันหยุดราชการประจำปี $year เรียบร้อยแล้ว เข้ามาทั้งหมด $count วันครับ");
    }

    public function addHoliday(Request $request)
    {
        $validated = $request->validate([
            'holiday_date' => 'required|date|unique:company_holidays,holiday_date',
            'name' => 'required|string|max:255',
        ]);

        CompanyHoliday::create($validated + ['is_active' => true]);

        AuditLogService::logCreated(CompanyHoliday::latest()->first(), 'Holiday added');

        $redirect = $request->input('_redirect');
        return ($redirect ? redirect($redirect) : back())->with('success', 'เพิ่มวันหยุดบริษัทสำเร็จ');
    }

    public function deleteHoliday(CompanyHoliday $holiday)
    {
        AuditLogService::logDeleted($holiday, 'Holiday deleted: ' . $holiday->name);
        $holiday->delete();
        return back()->with('success', 'ลบวันหยุดบริษัทสำเร็จ');
    }
    public function company()
    {
        $company = CompanyProfile::active();
        
        return view('settings.company', compact('company'));
    }

    public function updateCompany(Request $request)
    {
        $company = CompanyProfile::active();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tagline' => 'nullable|string|max:255',
            'payslip_header_subtitle' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'payslip_footer_text' => 'nullable|string',
            'signature_approver_name' => 'nullable|string|max:100',
            'signature_approver_image' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'signature_receiver_name' => 'nullable|string|max:100',
            'signature_receiver_image' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
        ]);

        // Handle approver signature upload
        if ($request->hasFile('signature_approver_image')) {
            $path = $request->file('signature_approver_image')->store('signatures', 'public');
            $validated['signature_approver_image_path'] = $path;
        }

        // Handle receiver signature upload
        if ($request->hasFile('signature_receiver_image')) {
            $path = $request->file('signature_receiver_image')->store('signatures', 'public');
            $validated['signature_receiver_image_path'] = $path;
        }

        // Remove file inputs from validated array
        unset($validated['signature_approver_image']);
        unset($validated['signature_receiver_image']);

        $oldData = $company->getAttributes();

        $company->update($validated);

        AuditLogService::log($company, 'updated', 'company_profile', $oldData, $company->getAttributes(), 'Company profile updated');

        return redirect()
            ->route('settings.company')
            ->with('success', 'บันทึกการตั้งค่าบริษัทสำเร็จ');
    }
}
