<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add entitlement columns to employees
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'vacation_entitlement')) {
                $table->unsignedSmallInteger('vacation_entitlement')->default(6)
                    ->after('end_date')->comment('โควต้าวันลาพักร้อนต่อปี');
            }
            if (!Schema::hasColumn('employees', 'sick_leave_entitlement')) {
                $table->unsignedSmallInteger('sick_leave_entitlement')->default(30)
                    ->after('vacation_entitlement')->comment('โควต้าวันลาป่วยต่อปี (ตาม กม. = 30)');
            }
            if (!Schema::hasColumn('employees', 'personal_leave_entitlement')) {
                $table->unsignedSmallInteger('personal_leave_entitlement')->default(3)
                    ->after('sick_leave_entitlement')->comment('โควต้าวันลากิจต่อปี (ตาม กม. = อย่างน้อย 3)');
            }
        });

        // 2. Carryover table — admin manually carries unused leave to next year
        Schema::create('leave_carryovers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedSmallInteger('year')->comment('ปี ค.ศ. ที่ยอดนี้ใช้ได้ (ปลายทางการยกยอด)');
            $table->string('leave_type', 30)->comment('ประเภทวันลาที่ยกยอด เช่น vacation_leave');
            $table->decimal('days', 5, 2)->comment('จำนวนวันที่ยกยอดเข้ามา');
            $table->string('source_year', 4)->nullable()->comment('ยกยอดมาจากปีไหน');
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['employee_id', 'year', 'leave_type']);
        });

        // 3. Encashment table — admin converts unused leave days to cash
        Schema::create('leave_encashments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedSmallInteger('year')->comment('ปี ค.ศ. ของสิทธิที่นำมาแลกเป็นเงิน');
            $table->string('leave_type', 30)->comment('ประเภทวันลาที่แลก เช่น vacation_leave');
            $table->decimal('days', 5, 2)->comment('จำนวนวันที่แลกเป็นเงิน');
            $table->decimal('rate_per_day', 10, 2)->comment('อัตราต่อวัน (มักใช้ฐานเงินเดือนหาร 30)');
            $table->decimal('amount', 12, 2)->comment('จำนวนเงินรวม = days × rate_per_day');
            $table->unsignedSmallInteger('payout_month')->comment('เดือนที่จ่ายเงิน (1-12)');
            $table->unsignedSmallInteger('payout_year')->comment('ปีที่จ่ายเงิน ค.ศ.');
            $table->unsignedBigInteger('extra_income_entry_id')->nullable()->comment('FK → extra_income_entries ที่สร้างจากการแลก');
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['employee_id', 'year', 'leave_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_encashments');
        Schema::dropIfExists('leave_carryovers');
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'personal_leave_entitlement')) {
                $table->dropColumn('personal_leave_entitlement');
            }
            if (Schema::hasColumn('employees', 'sick_leave_entitlement')) {
                $table->dropColumn('sick_leave_entitlement');
            }
            if (Schema::hasColumn('employees', 'vacation_entitlement')) {
                $table->dropColumn('vacation_entitlement');
            }
        });
    }
};
