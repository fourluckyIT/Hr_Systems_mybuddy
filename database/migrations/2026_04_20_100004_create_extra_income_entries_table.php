<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_income_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedInteger('month');
            $table->unsignedInteger('year');
            $table->string('label');                 // e.g. "ยอดวิวโบนัส", "Brand Deal – ช่อง A"
            $table->string('category')->nullable();  // free-text category (YouTube/Brand/Tip/etc.)
            $table->decimal('amount', 12, 2)->default(0);
            $table->boolean('include_in_payslip')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['employee_id', 'month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_income_entries');
    }
};
