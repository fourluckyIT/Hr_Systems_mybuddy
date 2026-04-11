<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('work_log_type_id');
            $table->date('assigned_date');
            $table->date('due_date')->nullable();
            $table->string('status')->default('action_select');
            $table->string('priority')->default('normal');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('work_log_type_id')->references('id')->on('work_log_types')->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['employee_id', 'status']);
            $table->index(['work_log_type_id', 'status']);
        });

        Schema::table('performance_records', function (Blueprint $table) {
            $table->unsignedBigInteger('work_assignment_id')->nullable()->after('employee_id');
            $table->foreign('work_assignment_id')->references('id')->on('work_assignments')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('performance_records', function (Blueprint $table) {
            $table->dropForeign(['work_assignment_id']);
            $table->dropColumn('work_assignment_id');
        });

        Schema::dropIfExists('work_assignments');
    }
};
