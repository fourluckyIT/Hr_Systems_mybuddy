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
            $table->string('game_type')->nullable();
            $table->string('game')->nullable();
            $table->string('map')->nullable();
            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_time')->nullable();
            $table->integer('planned_duration_minutes')->nullable();
            $table->integer('footage_count')->nullable();
            $table->integer('longest_footage_seconds')->nullable();
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
            $table->string('role')->default('youtuber');
            $table->timestamps();

            $table->foreign('recording_job_id')->references('id')->on('recording_jobs')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });

        Schema::create('media_resources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recording_job_id')->nullable();
            $table->string('footage_code')->unique();
            $table->string('title')->nullable();
            $table->integer('raw_length_seconds')->nullable();
            $table->string('status')->default('ready_for_edit');
            $table->integer('footage_count')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('recording_job_id')->references('id')->on('recording_jobs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_resources');
        Schema::dropIfExists('recording_job_assignees');
        Schema::dropIfExists('recording_jobs');
    }
};
