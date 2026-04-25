<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite (and any text-stored DATE) may have rows with mixed formats:
        // "2026-04-18" vs "2026-04-18 00:00:00" — caused by Carbon-cast attributes
        // being passed into where()/insert() before the controller fix.
        //
        // 1. Normalize all log_date values to pure YYYY-MM-DD strings.
        // 2. Re-dedupe (the previous dedupe missed mixed-format rows).
        // 3. Mirror the same fix for off_date / work_date / leave_date columns.

        // Order matters: dedupe attendance_logs FIRST using normalized comparison
        // (so we don't fail unique constraint when normalizing), then normalize.
        $this->dedupeAttendanceLogsByNormalizedDate();

        $this->normalizeColumn('attendance_logs', 'log_date');
        $this->normalizeColumn('day_swap_requests', 'work_date');
        $this->normalizeColumn('day_swap_requests', 'off_date');
        $this->normalizeColumn('leave_requests', 'leave_date');
        $this->normalizeColumn('ot_requests', 'log_date');
    }

    protected function dedupeAttendanceLogsByNormalizedDate(): void
    {
        $all = DB::table('attendance_logs')
            ->select('id', 'employee_id', 'log_date', 'day_type', 'is_swapped_day',
                     'check_in', 'check_out', 'ot_minutes', 'ot_request_id')
            ->orderBy('id')
            ->get();

        $buckets = [];
        foreach ($all as $row) {
            $normDate = substr(preg_replace('/[T ].*/', '', (string) $row->log_date), 0, 10);
            $key = $row->employee_id . '|' . $normDate;
            $buckets[$key][] = $row;
        }

        foreach ($buckets as $rows) {
            if (count($rows) < 2) continue;

            // Keep the richest row: prefer is_swapped_day, then has data, then highest id.
            usort($rows, function ($a, $b) {
                $score = function ($r) {
                    $hasData = ($r->check_in !== null || $r->check_out !== null
                                || (int) $r->ot_minutes > 0 || $r->ot_request_id !== null) ? 1 : 0;
                    return ((int) $r->is_swapped_day) * 100 + $hasData * 10;
                };
                $sa = $score($a); $sb = $score($b);
                if ($sa !== $sb) return $sb <=> $sa;
                return $b->id <=> $a->id;
            });

            $keep = array_shift($rows);
            $deleteIds = array_map(fn($r) => $r->id, $rows);

            if (Schema::hasTable('attendance_day_swaps')) {
                DB::table('attendance_day_swaps')
                    ->whereIn('attendance_log_id', $deleteIds)
                    ->update(['attendance_log_id' => $keep->id]);
            }

            DB::table('attendance_logs')->whereIn('id', $deleteIds)->delete();
        }
    }

    protected function normalizeColumn(string $table, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        // Find any value that isn't a clean YYYY-MM-DD (i.e. contains a space or T).
        DB::table($table)
            ->where(function ($q) use ($column) {
                $q->where($column, 'like', '% %')
                  ->orWhere($column, 'like', '%T%');
            })
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $column) {
                foreach ($rows as $row) {
                    $raw = (string) $row->{$column};
                    $normalized = substr(preg_replace('/[T ].*/', '', $raw), 0, 10);
                    if ($normalized !== '' && $normalized !== $raw) {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->update([$column => $normalized]);
                    }
                }
            });
    }

    public function down(): void
    {
        // No-op: do not restore mixed formats or duplicates.
    }
};
