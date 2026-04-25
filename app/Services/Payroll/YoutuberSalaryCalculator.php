<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Services\SocialSecurityService;

class YoutuberSalaryCalculator
{
    public function __construct(
        protected MonthlyStaffCalculator $monthlyStaffCalculator,
        protected SocialSecurityService $ssoService
    ) {}

    public function calculate(Employee $employee, int $month, int $year): array
    {
        // YouTuber salary uses the same engine as monthly staff
        return $this->monthlyStaffCalculator->calculate($employee, $month, $year);
    }
}
