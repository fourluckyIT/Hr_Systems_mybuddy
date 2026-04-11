<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('rate_type'); // fixed, hourly
            $table->decimal('rate', 12, 2)->default(0);
            $table->date('effective_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });

        Schema::create('layer_rate_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedInteger('layer_from');
            $table->unsignedInteger('layer_to');
            $table->decimal('rate_per_minute', 12, 4)->default(0);
            $table->date('effective_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->index(['employee_id', 'is_active']);
        });

        Schema::create('bonus_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('payroll_mode')->nullable();
            $table->string('condition_type')->nullable(); // performance, attendance, custom
            $table->string('condition_value')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('threshold_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('metric'); // total_hours, ot_hours, late_count, etc.
            $table->string('operator'); // >=, <=, =, >, <
            $table->decimal('threshold_value', 12, 2)->default(0);
            $table->string('result_action'); // add_income, add_deduction, set_value
            $table->decimal('result_value', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('social_security_configs', function (Blueprint $table) {
            $table->id();
            $table->date('effective_date');
            $table->decimal('employee_rate', 5, 2)->default(5.00); // percent
            $table->decimal('employer_rate', 5, 2)->default(5.00);
            $table->decimal('salary_ceiling', 12, 2)->default(15000.00);
            $table->decimal('max_contribution', 12, 2)->default(750.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('attendance_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_type'); // late_deduction, ot_rate, diligence, grace_period
            $table->json('config'); // flexible JSON config
            $table->date('effective_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('module_toggles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('module_name');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->unique(['employee_id', 'module_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_toggles');
        Schema::dropIfExists('attendance_rules');
        Schema::dropIfExists('social_security_configs');
        Schema::dropIfExists('threshold_rules');
        Schema::dropIfExists('bonus_rules');
        Schema::dropIfExists('layer_rate_rules');
        Schema::dropIfExists('rate_rules');
    }
};
