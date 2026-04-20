<?php

namespace App\Services;

class HolidayService
{
    /**
     * Return Thai public holidays for the given year.
     * Dates are based on official Thai government calendar.
     * Buddhist calendar holidays (Makha, Visakha, Asahna) are approximate
     * and should be verified each year via the company_holidays table.
     */
    public function getThaiPublicHolidays(int $year = 2026): array
    {
        $datasets = [
            2025 => [
                ['date' => '2025-01-01', 'name' => 'วันขึ้นปีใหม่ (New Year\'s Day)'],
                ['date' => '2025-02-12', 'name' => 'วันมาฆบูชา (Makha Bucha)'],
                ['date' => '2025-04-06', 'name' => 'วันจักรี (Chakri Day)'],
                ['date' => '2025-04-13', 'name' => 'วันสงกรานต์ (Songkran)'],
                ['date' => '2025-04-14', 'name' => 'วันสงกรานต์ (Songkran)'],
                ['date' => '2025-04-15', 'name' => 'วันสงกรานต์ (Songkran)'],
                ['date' => '2025-05-01', 'name' => 'วันแรงงานแห่งชาติ (Labour Day)'],
                ['date' => '2025-05-05', 'name' => 'วันฉัตรมงคล (Coronation Day)'],
                ['date' => '2025-05-12', 'name' => 'วันวิสาขบูชา (Visakha Bucha)'],
                ['date' => '2025-06-03', 'name' => 'วันเฉลิมพระชนมพรรษาพระราชินี (Queen\'s Birthday)'],
                ['date' => '2025-07-10', 'name' => 'วันอาสาฬหบูชา (Asahna Bucha)'],
                ['date' => '2025-07-28', 'name' => 'วันเฉลิมพระชนมพรรษา ร.10 (King\'s Birthday)'],
                ['date' => '2025-08-12', 'name' => 'วันแม่แห่งชาติ (Mother\'s Day)'],
                ['date' => '2025-10-13', 'name' => 'วันคล้ายวันสวรรคต ร.9 (Rama IX Memorial)'],
                ['date' => '2025-10-23', 'name' => 'วันปิยมหาราช (Chulalongkorn Day)'],
                ['date' => '2025-12-05', 'name' => 'วันพ่อแห่งชาติ (Father\'s Day)'],
                ['date' => '2025-12-10', 'name' => 'วันรัฐธรรมนูญ (Constitution Day)'],
                ['date' => '2025-12-31', 'name' => 'วันสิ้นปี (New Year\'s Eve)'],
            ],
            2026 => [
                ['date' => '2026-01-01', 'name' => 'วันขึ้นปีใหม่ (New Year\'s Day)'],
                ['date' => '2026-03-03', 'name' => 'วันมาฆบูชา (Makha Bucha)'],
                ['date' => '2026-04-06', 'name' => 'วันจักรี (Chakri Day)'],
                ['date' => '2026-04-13', 'name' => 'วันสงกรานต์ (Songkran)'],
                ['date' => '2026-04-14', 'name' => 'วันสงกรานต์ (Songkran)'],
                ['date' => '2026-04-15', 'name' => 'วันสงกรานต์ (Songkran)'],
                ['date' => '2026-05-01', 'name' => 'วันแรงงานแห่งชาติ (Labour Day)'],
                ['date' => '2026-05-04', 'name' => 'วันฉัตรมงคล (Coronation Day)'],
                ['date' => '2026-05-31', 'name' => 'วันวิสาขบูชา (Visakha Bucha)'],
                ['date' => '2026-06-03', 'name' => 'วันเฉลิมพระชนมพรรษาพระราชินี (Queen\'s Birthday)'],
                ['date' => '2026-07-28', 'name' => 'วันเฉลิมพระชนมพรรษา ร.10 (King\'s Birthday)'],
                ['date' => '2026-07-29', 'name' => 'วันอาสาฬหบูชา (Asahna Bucha)'],
                ['date' => '2026-08-12', 'name' => 'วันแม่แห่งชาติ (Mother\'s Day)'],
                ['date' => '2026-10-13', 'name' => 'วันคล้ายวันสวรรคต ร.9 (Rama IX Memorial)'],
                ['date' => '2026-10-23', 'name' => 'วันปิยมหาราช (Chulalongkorn Day)'],
                ['date' => '2026-12-05', 'name' => 'วันพ่อแห่งชาติ (Father\'s Day)'],
                ['date' => '2026-12-10', 'name' => 'วันรัฐธรรมนูญ (Constitution Day)'],
                ['date' => '2026-12-31', 'name' => 'วันสิ้นปี (New Year\'s Eve)'],
            ],
            2027 => [
                ['date' => '2027-01-01', 'name' => 'วันขึ้นปีใหม่ (New Year\'s Day)'],
                ['date' => '2027-03-01', 'name' => 'วันมาฆบูชา (Makha Bucha)'],
                ['date' => '2027-04-06', 'name' => 'วันจักรี (Chakri Day)'],
                ['date' => '2027-04-13', 'name' => 'วันสงกรานต์ (Songkran)'],
                ['date' => '2027-04-14', 'name' => 'วันสงกรานต์ (Songkran)'],
                ['date' => '2027-04-15', 'name' => 'วันสงกรานต์ (Songkran)'],
                ['date' => '2027-05-01', 'name' => 'วันแรงงานแห่งชาติ (Labour Day)'],
                ['date' => '2027-05-04', 'name' => 'วันฉัตรมงคล (Coronation Day)'],
                ['date' => '2027-05-20', 'name' => 'วันวิสาขบูชา (Visakha Bucha)'],
                ['date' => '2027-06-03', 'name' => 'วันเฉลิมพระชนมพรรษาพระราชินี (Queen\'s Birthday)'],
                ['date' => '2027-07-18', 'name' => 'วันอาสาฬหบูชา (Asahna Bucha)'],
                ['date' => '2027-07-28', 'name' => 'วันเฉลิมพระชนมพรรษา ร.10 (King\'s Birthday)'],
                ['date' => '2027-08-12', 'name' => 'วันแม่แห่งชาติ (Mother\'s Day)'],
                ['date' => '2027-10-13', 'name' => 'วันคล้ายวันสวรรคต ร.9 (Rama IX Memorial)'],
                ['date' => '2027-10-23', 'name' => 'วันปิยมหาราช (Chulalongkorn Day)'],
                ['date' => '2027-12-05', 'name' => 'วันพ่อแห่งชาติ (Father\'s Day)'],
                ['date' => '2027-12-10', 'name' => 'วันรัฐธรรมนูญ (Constitution Day)'],
                ['date' => '2027-12-31', 'name' => 'วันสิ้นปี (New Year\'s Eve)'],
            ],
        ];

        if (isset($datasets[$year])) {
            return $datasets[$year];
        }

        // Fallback: return fixed-date holidays that don't change year-to-year
        // Buddhist calendar holidays (Makha, Visakha, Asahna) are excluded
        // since their dates vary and must be configured manually per year
        return [
            ['date' => "{$year}-01-01", 'name' => 'วันขึ้นปีใหม่ (New Year\'s Day)'],
            ['date' => "{$year}-04-06", 'name' => 'วันจักรี (Chakri Day)'],
            ['date' => "{$year}-04-13", 'name' => 'วันสงกรานต์ (Songkran)'],
            ['date' => "{$year}-04-14", 'name' => 'วันสงกรานต์ (Songkran)'],
            ['date' => "{$year}-04-15", 'name' => 'วันสงกรานต์ (Songkran)'],
            ['date' => "{$year}-05-01", 'name' => 'วันแรงงานแห่งชาติ (Labour Day)'],
            ['date' => "{$year}-05-04", 'name' => 'วันฉัตรมงคล (Coronation Day)'],
            ['date' => "{$year}-06-03", 'name' => 'วันเฉลิมพระชนมพรรษาพระราชินี (Queen\'s Birthday)'],
            ['date' => "{$year}-07-28", 'name' => 'วันเฉลิมพระชนมพรรษา ร.10 (King\'s Birthday)'],
            ['date' => "{$year}-08-12", 'name' => 'วันแม่แห่งชาติ (Mother\'s Day)'],
            ['date' => "{$year}-10-13", 'name' => 'วันคล้ายวันสวรรคต ร.9 (Rama IX Memorial)'],
            ['date' => "{$year}-10-23", 'name' => 'วันปิยมหาราช (Chulalongkorn Day)'],
            ['date' => "{$year}-12-05", 'name' => 'วันพ่อแห่งชาติ (Father\'s Day)'],
            ['date' => "{$year}-12-10", 'name' => 'วันรัฐธรรมนูญ (Constitution Day)'],
            ['date' => "{$year}-12-31", 'name' => 'วันสิ้นปี (New Year\'s Eve)'],
        ];
    }
}
