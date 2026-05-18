<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The 2026_05_06 migration that introduced per-type probation flags
 * back-filled them from the old `available_during_probation` column.
 * For installations where that old flag was `0`, every per-type flag
 * ended up `0` too — blocking ลาป่วย and ลากิจ during probation, which
 * contradicts ม.32 / ม.34.
 *
 * This migration repairs any leave_policies row where ALL three per-type
 * flags are still 0 by resetting them to the law-aligned defaults:
 *   sick     = 1   (ม.32)
 *   personal = 1   (ม.34)
 *   vacation = 0   (ม.30)
 * Policies that have been explicitly tuned are left untouched.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('leave_policies')
            ->where('sick_during_probation', 0)
            ->where('personal_during_probation', 0)
            ->where('vacation_during_probation', 0)
            ->update([
                'sick_during_probation'     => 1,
                'personal_during_probation' => 1,
                'vacation_during_probation' => 0,
            ]);
    }

    public function down(): void
    {
        // No-op — we don't want to re-break installations.
    }
};
