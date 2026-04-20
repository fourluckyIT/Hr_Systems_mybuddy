<?php

namespace Tests\Unit;

use App\Services\BonusCalculationService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class BonusCalculationServiceTest extends TestCase
{
    protected BonusCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BonusCalculationService();
    }

    // ─── calculateMonthsAfterProbation ───────────────────────────────────

    public function test_months_after_probation_returns_zero_when_before_probation(): void
    {
        $probEnd  = Carbon::parse('2026-07-01');
        $payDate  = Carbon::parse('2026-06-30');

        $this->assertEquals(0, $this->service->calculateMonthsAfterProbation($probEnd, $payDate));
    }

    public function test_months_after_probation_exact_same_day(): void
    {
        $date = Carbon::parse('2026-06-30');

        $this->assertEquals(0, $this->service->calculateMonthsAfterProbation($date, $date));
    }

    public function test_months_after_probation_full_months(): void
    {
        $probEnd = Carbon::parse('2026-01-15');
        $payDate = Carbon::parse('2026-06-30');

        $this->assertEquals(5, $this->service->calculateMonthsAfterProbation($probEnd, $payDate));
    }

    public function test_months_after_probation_partial_month_day_before(): void
    {
        $probEnd = Carbon::parse('2026-01-20');
        $payDate = Carbon::parse('2026-06-15');

        // 5 months minus 1 because 15 < 20
        $this->assertEquals(4, $this->service->calculateMonthsAfterProbation($probEnd, $payDate));
    }

    public function test_months_after_probation_across_year(): void
    {
        $probEnd = Carbon::parse('2025-09-01');
        $payDate = Carbon::parse('2026-06-30');

        $this->assertEquals(9, $this->service->calculateMonthsAfterProbation($probEnd, $payDate));
    }

    // ─── calculateUnlockPercentage ───────────────────────────────────────

    public function test_unlock_zero_months_returns_zero(): void
    {
        $this->assertEquals(0.0, $this->service->calculateUnlockPercentage(0, 'june'));
        $this->assertEquals(0.0, $this->service->calculateUnlockPercentage(0, 'december'));
    }

    public function test_unlock_june_3_months(): void
    {
        // 3/6 * 0.4 = 0.2
        $this->assertEquals(0.2, $this->service->calculateUnlockPercentage(3, 'june'));
    }

    public function test_unlock_june_6_months_caps_at_40(): void
    {
        $this->assertEquals(0.4, $this->service->calculateUnlockPercentage(6, 'june'));
    }

    public function test_unlock_june_exceeding_6_months_still_caps(): void
    {
        $this->assertEquals(0.4, $this->service->calculateUnlockPercentage(12, 'june'));
    }

    public function test_unlock_june_1_month(): void
    {
        // 1/6 * 0.4 = 0.0667
        $this->assertEquals(0.0667, $this->service->calculateUnlockPercentage(1, 'june'));
    }

    public function test_unlock_december_12_months_no_previous(): void
    {
        // 12/12 = 1.0, no previous = 1.0
        $this->assertEquals(1.0, $this->service->calculateUnlockPercentage(12, 'december', 0.0));
    }

    public function test_unlock_december_12_months_with_june_paid(): void
    {
        // 12/12 = 1.0 - 0.4 (June) = 0.6
        $this->assertEquals(0.6, $this->service->calculateUnlockPercentage(12, 'december', 0.4));
    }

    public function test_unlock_december_6_months_with_june_paid(): void
    {
        // 6/12 = 0.5 - 0.2 (partial June) = 0.3
        $this->assertEquals(0.3, $this->service->calculateUnlockPercentage(6, 'december', 0.2));
    }

    public function test_unlock_december_3_months_no_previous(): void
    {
        // 3/12 = 0.25
        $this->assertEquals(0.25, $this->service->calculateUnlockPercentage(3, 'december', 0.0));
    }

    public function test_unlock_december_does_not_go_negative(): void
    {
        // 1/12 = 0.0833 - 0.4 = negative → 0
        $this->assertEquals(0.0, $this->service->calculateUnlockPercentage(1, 'december', 0.4));
    }

    public function test_unlock_invalid_period(): void
    {
        $this->assertEquals(0.0, $this->service->calculateUnlockPercentage(6, 'invalid'));
    }

    // ─── Annual Cap Rule ─────────────────────────────────────────────────

    public function test_annual_cap_june_plus_december_equals_100(): void
    {
        $juneUnlock = $this->service->calculateUnlockPercentage(6, 'june');
        $decUnlock  = $this->service->calculateUnlockPercentage(12, 'december', $juneUnlock);

        $this->assertLessThanOrEqual(1.0, $juneUnlock + $decUnlock);
        $this->assertEquals(1.0, $juneUnlock + $decUnlock);
    }

    public function test_annual_cap_partial_employee(): void
    {
        // Employee with 3 months in June, 9 months by December
        $juneUnlock = $this->service->calculateUnlockPercentage(3, 'june');  // 0.2
        $decUnlock  = $this->service->calculateUnlockPercentage(9, 'december', $juneUnlock); // 9/12 - 0.2 = 0.55

        $this->assertLessThanOrEqual(1.0, $juneUnlock + $decUnlock);
        $this->assertEquals(0.2, $juneUnlock);
        $this->assertEquals(0.55, $decUnlock);
    }

    // ─── Edge Cases ──────────────────────────────────────────────────────

    public function test_unlock_june_2_months(): void
    {
        // 2/6 * 0.4 = 0.1333
        $this->assertEquals(0.1333, $this->service->calculateUnlockPercentage(2, 'june'));
    }

    public function test_unlock_june_4_months(): void
    {
        // 4/6 * 0.4 = 0.2667
        $this->assertEquals(0.2667, $this->service->calculateUnlockPercentage(4, 'june'));
    }

    public function test_unlock_june_5_months(): void
    {
        // 5/6 * 0.4 = 0.3333
        $this->assertEquals(0.3333, $this->service->calculateUnlockPercentage(5, 'june'));
    }
}
