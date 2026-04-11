<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recording_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('game')->nullable();
            $table->string('map')->nullable();
            $table->date('scheduled_date')->nullable();
            $table->integer('planned_duration_minutes')->nullable();
            $table->string('status')->default('draft'); // draft, scheduled, recording, shot, cancelled
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index('status');
            $table->index('scheduled_date');
        });

        Schema::create('recording_job_assignees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recording_job_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('role')->default('creator'); // creator, talent, cameraman, host
            $table->timestamps();

            $table->foreign('recording_job_id')->references('id')->on('recording_jobs')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->unique(['recording_job_id', 'employee_id', 'role']);
        });

        Schema::create('media_resources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recording_job_id')->nullable();
            $table->string('footage_code')->unique();
            $table->string('title')->nullable();
            $table->integer('raw_length_seconds')->nullable();
            $table->integer('usable_length_seconds')->nullable();
            $table->string('status')->default('raw'); // raw, uploaded, ready_for_edit, in_use, archived
            $table->integer('usage_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('recording_job_id')->references('id')->on('recording_jobs')->nullOnDelete();
            $table->index('status');
            $table->index('footage_code');
        });

        Schema::create('edit_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('media_resource_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable(); // employee_id of editor
            $table->string('title');
            $table->string('status')->default('pending_resource'); // pending_resource, assigned, editing, submitted, approved, done
            $table->string('priority')->default('normal');
            $table->date('due_date')->nullable();
            $table->date('finished_date')->nullable();
            $table->integer('output_duration_seconds')->nullable();
            $table->text('output_notes')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('media_resource_id')->references('id')->on('media_resources')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('employees')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index('status');
            $table->index('due_date');
        });

        Schema::create('approved_work_outputs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('edit_job_id');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('title');
            $table->string('platform')->nullable(); // youtube, tiktok, etc
            $table->date('publish_date')->nullable();
            $table->integer('final_duration_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('edit_job_id')->references('id')->on('edit_jobs')->cascadeOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approved_work_outputs');
        Schema::dropIfExists('edit_jobs');
        Schema::dropIfExists('media_resources');
        Schema::dropIfExists('recording_job_assignees');
        Schema::dropIfExists('recording_jobs');
    }
};
