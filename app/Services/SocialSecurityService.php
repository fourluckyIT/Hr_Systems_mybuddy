<?php

namespace App\Services;

use App\Models\SocialSecurityConfig;

class SocialSecurityService
{
    public function calculate(float $baseSalary, ?string $date = null): array
    {
        $config = SocialSecurityConfig::where('is_active', true)
            ->where('effective_date', '<=', $date ?? now()->toDateString())
            ->orderBy('effective_date', 'desc')
            ->first();

        if (!$config) {
            return ['employee' => 0, 'employer' => 0];
        }

        $cappedSalary = min($baseSalary, (float) $config->salary_ceiling);
        $employeeContribution = min(
            round($cappedSalary * (float) $config->employee_rate / 100, 2),
            (float) $config->max_contribution
        );
        $employerContribution = min(
            round($cappedSalary * (float) $config->employer_rate / 100, 2),
            (float) $config->max_contribution
        );

        return [
            'employee' => $employeeContribution,
            'employer' => $employerContribution,
        ];
    }
}
