<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_item_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('label_th');
            $table->string('label_en');
            $table->string('category'); // income, deduction
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payroll_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('month');
            $table->unsignedInteger('year');
            $table->string('status')->default('draft'); // draft, processing, finalized
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['month', 'year']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('payroll_batch_id');
            $table->string('item_type_code'); // references payroll_item_types.code
            $table->string('category'); // income, deduction
            $table->string('label');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('source_flag')->default('auto'); // auto, manual, override, master, rule_applied
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('payroll_batch_id')->references('id')->on('payroll_batches')->onDelete('cascade');
            $table->index(['employee_id', 'payroll_batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_batches');
        Schema::dropIfExists('payroll_item_types');
    }
};
