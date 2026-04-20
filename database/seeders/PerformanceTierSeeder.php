<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PerformanceTierSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            ['tier_code' => 'SS', 'tier_name' => 'Exceptional',    'multiplier' =>  0.300, 'description' => 'Outstanding performance',  'display_order' => 1],
            ['tier_code' => 'S+', 'tier_name' => 'Outstanding',    'multiplier' =>  0.200, 'description' => 'Strongly exceeds expectations', 'display_order' => 2],
            ['tier_code' => 'S',  'tier_name' => 'Excellent',      'multiplier' =>  0.200, 'description' => 'Exceeds expectations',     'display_order' => 3],
            ['tier_code' => 'A',  'tier_name' => 'Good',           'multiplier' =>  0.100, 'description' => 'Meets expectations',       'display_order' => 4],
            ['tier_code' => 'B',  'tier_name' => 'Below Average',  'multiplier' => -0.100, 'description' => 'Needs improvement',        'display_order' => 5],
            ['tier_code' => 'C',  'tier_name' => 'Poor',           'multiplier' => -0.200, 'description' => 'Underperforming',          'display_order' => 6],
            ['tier_code' => 'D',  'tier_name' => 'Very Poor',      'multiplier' => -0.300, 'description' => 'Significantly below expectations', 'display_order' => 7],
            ['tier_code' => 'E',  'tier_name' => 'Critical',       'multiplier' => -0.400, 'description' => 'Critical performance issues', 'display_order' => 8],
            ['tier_code' => 'F',  'tier_name' => 'Failing',        'multiplier' => -0.500, 'description' => 'Fails to meet minimum expectations', 'display_order' => 9],
        ];

        foreach ($tiers as $tier) {
            DB::table('performance_tiers')->updateOrInsert(
                ['tier_code' => $tier['tier_code']],
                array_merge($tier, [
                    'min_clip_minutes_per_month' => null,
                    'max_clip_minutes_per_month' => null,
                    'min_qualified_months' => null,
                    'max_qualified_months' => null,
                    'is_active'  => true,
                    'auto_select_enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
