<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Merge freelance_fixed into freelance_layer.
        // fixed_rate_per_clip is preserved on employees and now acts as
        // "default flat price per clip" — applied to new WorkLogs as custom_rate.
        DB::table('employees')
            ->where('payroll_mode', 'freelance_fixed')
            ->update(['payroll_mode' => 'freelance_layer']);

        // Existing WorkLogs seeded from fixed jobs already have rate=amount.
        // Mark them as custom so the new flat calculator keeps paying the same amount.
        DB::table('work_logs')
            ->whereNull('pricing_mode')
            ->whereRaw('rate = amount')
            ->where('rate', '>', 0)
            ->where('hours', 0)
            ->where('minutes', 0)
            ->where('seconds', 0)
            ->update(['pricing_mode' => 'custom', 'custom_rate' => DB::raw('rate')]);
    }

    public function down(): void
    {
        // one-way: merging back requires knowing which employees were originally fixed
    }
};
