<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('employee_code')->nullable()->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('nickname')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('position_id')->nullable();
            $table->string('payroll_mode')->default('monthly_staff'); // monthly_staff, freelance_layer, freelance_fixed, youtuber_salary, youtuber_settlement, custom_hybrid
            $table->string('status')->default('active'); // active, inactive, terminated
            $table->boolean('is_active')->default(true);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('position_id')->references('id')->on('positions')->onDelete('set null');
            $table->index('payroll_mode');
            $table->index('status');
        });

        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->unique();
            $table->string('id_card', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('photo')->nullable();
            $table->date('birth_date')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });

        Schema::create('employee_salary_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->date('effective_date');
            $table->text('notes')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->index(['employee_id', 'is_current']);
        });

        Schema::create('employee_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('bank_name');
            $table->string('account_number');
            $table->string('account_name');
            $table->boolean('is_primary')->default(true);
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_bank_accounts');
        Schema::dropIfExists('employee_salary_profiles');
        Schema::dropIfExists('employee_profiles');
        Schema::dropIfExists('employees');
    }
};
