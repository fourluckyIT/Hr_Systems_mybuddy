<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bonus_cycles', function (Blueprint $table) {
            // Unlock ratio rules (configurable per cycle)
            $table->decimal('june_max_ratio', 4, 3)->default(0.400)->after('max_allocation');
            $table->unsignedTinyInteger('june_scale_months')->default(6)->after('june_max_ratio');
            $table->unsignedTinyInteger('full_scale_months')->default(12)->after('june_scale_months');

            // Attendance adjustment penalty rules (configurable per cycle)
            $table->decimal('absent_penalty_per_day', 5, 4)->default(-0.0100)->after('full_scale_months');
            $table->decimal('late_penalty_per_occurrence', 5, 4)->default(-0.0020)->after('absent_penalty_per_day');
            $table->unsignedTinyInteger('leave_free_days')->default(5)->after('late_penalty_per_occurrence');
            $table->decimal('leave_penalty_rate', 5, 4)->default(0.0100)->after('leave_free_days');
        });
    }

    public function down(): void
    {
        Schema::table('bonus_cycles', function (Blueprint $table) {
            $table->dropColumn([
                'june_max_ratio',
                'june_scale_months',
                'full_scale_months',
                'absent_penalty_per_day',
                'late_penalty_per_occurrence',
                'leave_free_days',
                'leave_penalty_rate',
            ]);
        });
    }
};
