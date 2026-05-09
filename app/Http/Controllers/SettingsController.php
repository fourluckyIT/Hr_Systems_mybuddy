<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\AttendanceRule;
use App\Models\CompanyHoliday;
use App\Models\HolidayType;
use App\Models\Employee;
use App\Models\SocialSecurityConfig;
use App\Services\HolidayService;
use Carbon\Carbon;
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

        $diligenceTiers = $this->normalizeDiligenceTiers($rules['diligence']?->config ?? []);

        $holidays = CompanyHoliday::with('holidayType')->orderBy('holiday_date', 'asc')->get();
        $holidayTypes = HolidayType::where('is_active', true)->orderBy('sort_order')->get();
        $colorPresets = HolidayType::COLOR_PRESETS;

        // Stats — current year breakdown so the user can see "how many used / how many left"
        $today = \Carbon\Carbon::today();
        $thisYear = $today->year;
        $yearHolidays = $holidays->filter(fn($h) => $h->holiday_date->year === $thisYear);
        $holidayStats = [
            'year' => $thisYear,
            'total' => $yearHolidays->count(),
            'passed' => $yearHolidays->filter(fn($h) => $h->holiday_date->lt($today))->count(),
            'today' => $yearHolidays->filter(fn($h) => $h->holiday_date->isSameDay($today))->count(),
            'upcoming' => $yearHolidays->filter(fn($h) => $h->holiday_date->gt($today))->count(),
            'by_type' => $yearHolidays->groupBy('holiday_type_id')->map->count(),
        ];

        return view('settings.rules', compact('rules', 'holidays', 'diligenceTiers', 'holidayTypes', 'colorPresets', 'holidayStats'));
    }

    /**
     * Convert legacy diligence config (single-mode or old tier keys) to the new
     * toggleable-condition tier shape used by the editor and PayrollRuleService.
     */
    protected function normalizeDiligenceTiers(array $config): array
    {
        $rawTiers = $config['tiers'] ?? [];

        // Legacy single-mode → synthesise one tier from amount + require_zero flags.
        if (empty($rawTiers) && isset($config['amount'])) {
            $rawTiers = [[
                'amount' => $config['amount'],
                'check_lwop' => $config['require_zero_lwop'] ?? true,
                'lwop_max' => 0,
                'check_late_count' => $config['require_zero_late'] ?? true,
                'late_count_max' => 0,
            ]];
        }

        $normalized = [];
        foreach ($rawTiers as $t) {
            // Legacy tier had only late_count_max + lwop_days_max + amount.
            $hasFlags = isset($t['check_lwop']) || isset($t['check_late_count'])
                || isset($t['check_late_minutes']) || isset($t['check_early_leave'])
                || isset($t['check_min_attended']);

            $normalized[] = [
                'amount' => (float) ($t['amount'] ?? 0),
                'check_lwop' => $hasFlags ? !empty($t['check_lwop']) : isset($t['lwop_days_max']),
                'lwop_max' => (float) ($t['lwop_max'] ?? $t['lwop_days_max'] ?? 0),
                'check_late_count' => $hasFlags ? !empty($t['check_late_count']) : isset($t['late_count_max']),
                'late_count_max' => (float) ($t['late_count_max'] ?? 0),
                'check_late_minutes' => !empty($t['check_late_minutes']),
                'late_minutes_max' => (float) ($t['late_minutes_max'] ?? 0),
                'check_early_leave' => !empty($t['check_early_leave']),
                'early_leave_max' => (float) ($t['early_leave_max'] ?? 0),
                'check_min_attended' => !empty($t['check_min_attended']),
                'min_attended_days' => (float) ($t['min_attended_days'] ?? 0),
            ];
        }

        usort($normalized, fn($a, $b) => $b['amount'] <=> $a['amount']);
        return $normalized;
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

        // Diligence: tier-only structure with per-tier toggleable conditions.
        if ($type === 'diligence') {
            $rawTiers = $request->input('tiers', []);
            $checkFlags = ['check_lwop', 'check_late_count', 'check_late_minutes', 'check_early_leave', 'check_min_attended'];
            $thresholdKeys = ['lwop_max', 'late_count_max', 'late_minutes_max', 'early_leave_max', 'min_attended_days'];

            $tiers = [];
            foreach ($rawTiers as $t) {
                if (!is_array($t) || !is_numeric($t['amount'] ?? null)) continue;
                $tier = ['amount' => (float) $t['amount']];
                foreach ($checkFlags as $flag) {
                    $tier[$flag] = !empty($t[$flag]);
                }
                foreach ($thresholdKeys as $key) {
                    $tier[$key] = is_numeric($t[$key] ?? null) ? (float) $t[$key] : 0;
                }
                $tiers[] = $tier;
            }

            // Sort tiers descending by amount so the editor stays consistent with eval order.
            usort($tiers, fn($a, $b) => $b['amount'] <=> $a['amount']);

            $inputs = ['tiers' => $tiers, 'use_tiers' => true];
            // Drop legacy single-mode keys.
            unset($config['require_zero_late'], $config['require_zero_lwop'], $config['amount']);
        }

        $oldConfig = $rule->config;

        foreach ($inputs as $key => $value) {
            $config[$key] = $value;
        }

        $rule->update(['config' => $config]);

        AuditLogService::log($rule, 'updated', 'config', $oldConfig, $config, "Rule '{$type}' updated");

        // When working_hours rule changes (check-in/out times or work duration),
        // re-derive late_minutes, early_leave_minutes, ot_minutes for all employees
        // in the current month so figures stay consistent without manual re-save.
        if ($type === 'working_hours') {
            $this->resyncCurrentMonthAttendance($config);
        }

        return back()->with('success', 'อัปเดตกฎการทำงานสำเร็จ');
    }

    /**
     * After saving a working_hours rule, recalculate derived attendance fields
     * (late_minutes, early_leave_minutes, ot_minutes) for all employees whose
     * attendance logs exist in the current month.
     */
    protected function resyncCurrentMonthAttendance(array $newConfig): void
    {
        $month = (int) now()->month;
        $year  = (int) now()->year;

        $targetIn  = $newConfig['target_check_in']  ?? '09:30';
        $targetOut = $newConfig['target_check_out'] ?? '18:30';

        $logs = AttendanceLog::whereMonth('log_date', $month)
            ->whereYear('log_date', $year)
            ->where('is_disabled', false)
            ->whereNotNull('check_in')
            ->whereNotNull('check_out')
            ->get();

        foreach ($logs as $log) {
            $dayType = (string) $log->day_type;
            $isWorkday = in_array($dayType, ['workday', 'ot_full_day'], true);
            $isHoliday = in_array($dayType, ['holiday', 'company_holiday'], true);

            if (!$isWorkday && !$isHoliday) {
                continue;
            }

            $dateStr   = Carbon::parse($log->log_date)->format('Y-m-d');
            $rawIn     = $log->check_in;
            $rawOut    = $log->check_out;

            // Normalise "HH:MM" → "HH:MM:00" to avoid double-appending seconds.
            $normalise = fn(string $t): string =>
                preg_match('/^\d{2}:\d{2}$/', $t) ? "{$t}:00" : $t;

            $inAt  = Carbon::parse("{$dateStr} " . $normalise($rawIn));
            $outAt = Carbon::parse("{$dateStr} " . $normalise($rawOut));
            if ($outAt->lessThanOrEqualTo($inAt)) {
                $outAt->addDay();
            }

            $targetInAt  = Carbon::parse("{$dateStr} {$targetIn}:00");
            $targetOutAt = Carbon::parse("{$dateStr} {$targetOut}:00");

            $lateMinutes       = 0;
            $earlyLeaveMinutes = 0;
            $otMinutes         = 0;

            if ($isWorkday) {
                if ($inAt->greaterThan($targetInAt)) {
                    $lateMinutes = (int) $targetInAt->diffInMinutes($inAt);
                }
                if ($outAt->lessThan($targetOutAt)) {
                    $earlyLeaveMinutes = (int) $outAt->diffInMinutes($targetOutAt);
                }
            }

            if ($log->ot_enabled && $outAt->greaterThan($targetOutAt)) {
                $otMinutes = (int) $targetOutAt->diffInMinutes($outAt);
            }

            $updates = [];
            if ((int) $log->late_minutes        !== $lateMinutes)       $updates['late_minutes']        = $lateMinutes;
            if ((int) $log->early_leave_minutes !== $earlyLeaveMinutes) $updates['early_leave_minutes'] = $earlyLeaveMinutes;
            if ((int) $log->ot_minutes          !== $otMinutes)         $updates['ot_minutes']          = $otMinutes;

            if (!empty($updates)) {
                $log->update($updates);
            }
        }
    }

    public function loadLegalHolidays(Request $request)
    {
        $year = $request->input('year', 2026);
        $holidays = $this->holidayService->getThaiPublicHolidays($year);
        $publicTypeId = HolidayType::where('code', 'public')->value('id');

        // Pre-load existing dates so we don't hit the DB once per holiday.
        $existingDates = CompanyHoliday::whereYear('holiday_date', $year)
            ->pluck('holiday_date')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))
            ->all();
        $existingDates = array_flip($existingDates);

        $added = 0;
        $skipped = 0;
        foreach ($holidays as $h) {
            if (isset($existingDates[$h['date']])) {
                $skipped++;
                continue;
            }
            CompanyHoliday::create([
                'holiday_date' => $h['date'],
                'name' => $h['name'],
                'holiday_type_id' => $publicTypeId,
                'is_active' => true,
            ]);
            $existingDates[$h['date']] = true;
            $added++;
        }

        $msg = "ดึงวันหยุดราชการปี $year — เพิ่มใหม่ {$added} วัน";
        if ($skipped > 0) {
            $msg .= " (ข้าม {$skipped} วันที่มีอยู่แล้ว)";
        }
        return back()->with('success', $msg);
    }

    public function addHoliday(Request $request)
    {
        $validated = $request->validate([
            'holiday_date' => 'required|date|unique:company_holidays,holiday_date',
            'name' => 'required|string|max:255',
            'holiday_type_id' => 'nullable|exists:holiday_types,id',
            'color' => ['nullable', 'string', 'max:30', \Illuminate\Validation\Rule::in(array_keys(HolidayType::COLOR_PRESETS))],
        ]);

        // Default to "company" type when not specified
        if (empty($validated['holiday_type_id'])) {
            $validated['holiday_type_id'] = HolidayType::where('code', 'company')->value('id');
        }

        $holiday = CompanyHoliday::create($validated + ['is_active' => true]);

        AuditLogService::logCreated($holiday, 'Holiday added');

        $redirect = $request->input('_redirect');
        return ($redirect ? redirect($redirect) : back())->with('success', 'เพิ่มวันหยุดสำเร็จ');
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
