<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('log_date');
            $table->string('day_type')->default('workday'); // workday, holiday, sick_leave, personal_leave, ot_full_day, vacation
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->integer('late_minutes')->default(0);
            $table->integer('early_leave_minutes')->default(0);
            $table->integer('ot_minutes')->default(0);
            $table->boolean('ot_enabled')->default(false);
            $table->boolean('lwop_flag')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->unique(['employee_id', 'log_date']);
            $table->index('log_date');
        });

        Schema::create('work_log_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('payroll_mode')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('work_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedInteger('month');
            $table->unsignedInteger('year');
            $table->date('log_date')->nullable();
            $table->string('work_type')->nullable();
            $table->unsignedInteger('layer')->nullable();
            $table->integer('hours')->default(0);
            $table->integer('minutes')->default(0);
            $table->integer('seconds')->default(0);
            $table->integer('quantity')->default(0);
            $table->decimal('rate', 12, 4)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->index(['employee_id', 'month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_logs');
        Schema::dropIfExists('work_log_types');
        Schema::dropIfExists('attendance_logs');
    }
};
