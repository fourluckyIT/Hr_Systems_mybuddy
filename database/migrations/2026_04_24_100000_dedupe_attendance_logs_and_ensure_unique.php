<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Dedupe attendance_logs by (employee_id, log_date).
        //    Keep the row with the richest data: prefer swapped row, then row with
        //    non-null check_in/out or ot fields, else the highest id (most recent).
        $groups = DB::table('attendance_logs')
            ->select('employee_id', 'log_date', DB::raw('COUNT(*) as cnt'))
            ->groupBy('employee_id', 'log_date')
            ->having('cnt', '>', 1)
            ->get();

        foreach ($groups as $g) {
            $rows = DB::table('attendance_logs')
                ->where('employee_id', $g->employee_id)
                ->where('log_date', $g->log_date)
                ->orderByDesc('is_swapped_day')
                ->orderByRaw('(check_in IS NOT NULL OR check_out IS NOT NULL OR ot_minutes > 0 OR ot_request_id IS NOT NULL) DESC')
                ->orderByDesc('id')
                ->get();

            $keep = $rows->shift();
            $deleteIds = $rows->pluck('id')->all();

            if (!empty($deleteIds)) {
                // Re-point any FK references to the kept row before deleting.
                if (Schema::hasTable('attendance_day_swaps')) {
                    DB::table('attendance_day_swaps')
                        ->whereIn('attendance_log_id', $deleteIds)
                        ->update(['attendance_log_id' => $keep->id]);
                }

                DB::table('attendance_logs')->whereIn('id', $deleteIds)->delete();
            }
        }

        // 2. Ensure unique index exists on (employee_id, log_date).
        $indexName = 'attendance_logs_employee_id_log_date_unique';
        try {
            Schema::table('attendance_logs', function (Blueprint $table) use ($indexName) {
                $table->unique(['employee_id', 'log_date'], $indexName);
            });
        } catch (\Throwable $e) {
            // Index already exists — ignore.
        }
    }

    public function down(): void
    {
        // Intentionally no-op: we do not want to restore duplicates.
    }
};
