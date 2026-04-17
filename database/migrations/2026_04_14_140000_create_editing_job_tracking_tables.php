<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_reassignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('editing_job_id');
            $table->unsignedBigInteger('old_assignee');
            $table->unsignedBigInteger('new_assignee');
            $table->unsignedBigInteger('reassigned_by')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('reassigned_at')->nullable();
            $table->timestamps();

            $table->foreign('editing_job_id')->references('id')->on('editing_jobs')->cascadeOnDelete();
            $table->foreign('old_assignee')->references('id')->on('employees');
            $table->foreign('new_assignee')->references('id')->on('employees');
        });

        Schema::create('job_modifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('editing_job_id');
            $table->unsignedBigInteger('modified_by')->nullable();
            $table->string('field_name');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamp('modified_at')->useCurrent();
            $table->timestamps();

            $table->foreign('editing_job_id')->references('id')->on('editing_jobs')->cascadeOnDelete();
        });

        Schema::create('deadline_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('editing_job_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('notification_type'); // 3_days, 1_day, overdue
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->foreign('editing_job_id')->references('id')->on('editing_jobs')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deadline_notifications');
        Schema::dropIfExists('job_modifications');
        Schema::dropIfExists('job_reassignments');
    }
};
