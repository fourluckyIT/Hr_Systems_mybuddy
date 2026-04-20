<?php

namespace App\Services;

use Carbon\Carbon;

class WorkCalendarService
{
    /**
     * Count the number of Mondays through Fridays in a given month.
     * This is used as the standard divider for salary-based rates.
     */
    public function getWeekDayCount(int $month, int $year): int
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $count = 0;

        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            if ($date->isWeekday()) { // isWeekday() in Carbon returns true for Mon-Fri
                $count++;
            }
        }

        return $count;
    }
}
