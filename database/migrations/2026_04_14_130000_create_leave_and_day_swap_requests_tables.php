<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('leave_date');
            $table->string('leave_type', 30); // sick_leave, personal_leave, vacation_leave, lwop
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, cancelled
            $table->unsignedBigInteger('requested_by')->nullable(); // user_id
            $table->unsignedBigInteger('reviewed_by')->nullable(); // user_id (admin)
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['employee_id', 'leave_date'], 'leave_requests_emp_date_unique');
            $table->index(['employee_id', 'status'], 'leave_requests_emp_status_idx');
            $table->index(['status', 'leave_date'], 'leave_requests_status_date_idx');
        });

        Schema::create('day_swap_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('work_date');            // วันที่จะมาทำงานแทน
            $table->date('off_date');             // วันที่จะหยุดแทน
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, cancelled
            $table->unsignedBigInteger('requested_by')->nullable(); // user_id
            $table->unsignedBigInteger('reviewed_by')->nullable(); // user_id (admin)
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['employee_id', 'status'], 'day_swap_requests_emp_status_idx');
            $table->index(['status', 'work_date'], 'day_swap_requests_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('day_swap_requests');
        Schema::dropIfExists('leave_requests');
    }
};
