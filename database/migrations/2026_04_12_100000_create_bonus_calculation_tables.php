<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonus_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('cycle_code', 20)->unique(); // e.g. "2026-JUN"
            $table->unsignedSmallInteger('cycle_year');
            $table->string('cycle_period', 10); // june, december
            $table->date('payment_date');
            $table->decimal('max_allocation', 3, 2); // 0.40 or 0.60
            $table->string('status', 20)->default('draft'); // draft, calculating, calculated, reviewed, approved, paid, closed, rejected
            $table->timestamps();

            $table->index(['cycle_year', 'cycle_period']);
            $table->index('status');
        });

        Schema::create('performance_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('tier_code', 10)->unique(); // SS, S, A, B, C
            $table->string('tier_name', 50);
            $table->decimal('multiplier', 4, 3); // e.g. 0.300, -0.200
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('display_order');
        });

        Schema::create('attendance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('cycle_id');
            $table->unsignedSmallInteger('absent_days')->default(0);
            $table->unsignedSmallInteger('late_count')->default(0);
            $table->unsignedSmallInteger('leave_days')->default(0);
            $table->decimal('total_adjustment', 5, 4)->default(0); // -1.0 to +1.0
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('cycle_id')->references('id')->on('bonus_cycles')->onDelete('cascade');
            $table->unique(['employee_id', 'cycle_id']);
        });

        Schema::create('bonus_calculations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('cycle_id');

            // Base amounts
            $table->decimal('base_reference', 12, 2);
            $table->unsignedBigInteger('tier_id');
            $table->decimal('tier_multiplier', 4, 3);
            $table->decimal('tier_adjusted_bonus', 12, 2);

            // Attendance adjustment
            $table->decimal('attendance_adjustment', 5, 4)->default(0);
            $table->decimal('final_bonus_net', 12, 2);

            // Time-based unlock
            $table->unsignedSmallInteger('months_after_probation')->default(0);
            $table->decimal('unlock_percentage', 5, 4)->default(0);
            $table->decimal('actual_payment', 12, 2)->default(0);

            // Metadata
            $table->boolean('is_active_on_payment')->default(true);
            $table->string('status', 20)->default('calculated'); // calculated, reviewed, approved, paid, cancelled
            $table->string('approved_by', 50)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('cycle_id')->references('id')->on('bonus_cycles')->onDelete('cascade');
            $table->foreign('tier_id')->references('id')->on('performance_tiers');
            $table->unique(['employee_id', 'cycle_id']);
            $table->index(['cycle_id', 'employee_id']);
            $table->index(['employee_id', 'cycle_id']);
            $table->index('status');
        });

        Schema::create('bonus_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('calculation_id');
            $table->string('action_type', 20); // created, modified, approved, paid, cancelled
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->string('changed_by', 50)->nullable();
            $table->timestamp('changed_at')->useCurrent();
            $table->text('reason')->nullable();

            $table->foreign('calculation_id')->references('id')->on('bonus_calculations')->onDelete('cascade');
            $table->index(['calculation_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_audit_logs');
        Schema::dropIfExists('bonus_calculations');
        Schema::dropIfExists('attendance_adjustments');
        Schema::dropIfExists('performance_tiers');
        Schema::dropIfExists('bonus_cycles');
    }
};
