<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ot_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('log_date');
            $table->unsignedInteger('requested_minutes')->default(0);
            $table->text('reason');
            $table->string('job_reference')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, cancelled
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['employee_id', 'log_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ot_requests');
    }
};
