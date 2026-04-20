<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->boolean('is_swapped_day')->default(false)->after('day_type');
            $table->string('swapped_from_day_type')->nullable()->after('is_swapped_day');
            $table->timestamp('swapped_at')->nullable()->after('swapped_from_day_type');
            $table->unsignedBigInteger('swapped_by')->nullable()->after('swapped_at');

            $table->foreign('swapped_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['employee_id', 'is_swapped_day']);
        });

        Schema::create('attendance_day_swaps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('attendance_log_id')->nullable();
            $table->date('log_date');
            $table->string('from_day_type');
            $table->string('to_day_type');
            $table->string('swap_reason', 500)->nullable();
            $table->unsignedBigInteger('swapped_by')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('attendance_log_id')->references('id')->on('attendance_logs')->nullOnDelete();
            $table->foreign('swapped_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['employee_id', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_day_swaps');

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropForeign(['swapped_by']);
            $table->dropIndex(['employee_id', 'is_swapped_day']);
            $table->dropColumn(['is_swapped_day', 'swapped_from_day_type', 'swapped_at', 'swapped_by']);
        });
    }
};
