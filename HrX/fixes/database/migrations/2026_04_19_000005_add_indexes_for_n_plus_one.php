<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix BUG-22, BUG-23, BUG-24. Composite indexes for the hot paths of A2/A3/A5.
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::table('editing_jobs', function (Blueprint $t) {
            $t->index(['status', 'deadline_date'], 'idx_editing_jobs_status_deadline');
        });

        Schema::table('work_logs', function (Blueprint $t) {
            $t->index(['employee_id', 'month'], 'idx_worklog_emp_month');
        });

        Schema::table('attendance_logs', function (Blueprint $t) {
            $t->index(['employee_id', 'work_date'], 'idx_attlog_emp_date');
            $t->index(['work_date', 'day_type'], 'idx_attlog_date_type');
        });
    }

    public function down(): void
    {
        Schema::table('editing_jobs', fn (Blueprint $t) => $t->dropIndex('idx_editing_jobs_status_deadline'));
        Schema::table('work_logs', fn (Blueprint $t) => $t->dropIndex('idx_worklog_emp_month'));
        Schema::table('attendance_logs', function (Blueprint $t) {
            $t->dropIndex('idx_attlog_emp_date');
            $t->dropIndex('idx_attlog_date_type');
        });
    }
};
