<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('record_date');
            $table->unsignedInteger('month');
            $table->unsignedInteger('year');
            $table->decimal('score', 5, 2)->default(0);
            $table->string('category')->default('manual'); // excellent, good, warning, critical, manual
            $table->text('notes')->nullable();
            $table->string('source')->default('manual'); // manual, auto_attendance
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->index(['employee_id', 'month', 'year']);
            $table->unique(['employee_id', 'record_date', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_records');
    }
};
