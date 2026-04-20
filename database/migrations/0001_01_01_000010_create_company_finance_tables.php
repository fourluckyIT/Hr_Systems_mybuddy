<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // dubbing, subscription, game, software, other
            $table->string('description');
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedInteger('month');
            $table->unsignedInteger('year');
            $table->timestamps();

            $table->index(['month', 'year']);
        });

        Schema::create('company_revenues', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('description')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedInteger('month');
            $table->unsignedInteger('year');
            $table->timestamps();

            $table->index(['month', 'year']);
        });

        Schema::create('subscription_costs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('amount', 12, 2)->default(0);
            $table->boolean('is_recurring')->default(true);
            $table->unsignedInteger('month');
            $table->unsignedInteger('year');
            $table->timestamps();

            $table->index(['month', 'year']);
        });

        Schema::create('expense_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('description');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->unsignedInteger('month');
            $table->unsignedInteger('year');
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_claims');
        Schema::dropIfExists('subscription_costs');
        Schema::dropIfExists('company_revenues');
        Schema::dropIfExists('company_expenses');
    }
};
