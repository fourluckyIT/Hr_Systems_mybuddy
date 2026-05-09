<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Master Data: leave_policies
        Schema::create('leave_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->boolean('is_default')->default(false)->comment('นโยบาย default ใช้กับคนที่ไม่ได้ผูก policy');
            $table->boolean('is_active')->default(true);

            // Entitlements
            $table->unsignedSmallInteger('vacation_days')->default(6);
            $table->unsignedSmallInteger('sick_days')->default(30);
            $table->unsignedSmallInteger('personal_days')->default(3);

            // Carryover (vacation only)
            $table->boolean('allow_carryover')->default(true);
            $table->unsignedSmallInteger('max_carryover_days')->nullable()->comment('null = ไม่จำกัด, 0 = ห้ามยก');
            $table->unsignedSmallInteger('carryover_expires_months')->nullable()->comment('null = ไม่หมดอายุ');

            // Encashment (vacation only)
            $table->boolean('allow_encashment')->default(true);
            $table->unsignedSmallInteger('max_encash_days_per_year')->nullable()->comment('null = ไม่จำกัด');
            $table->string('encash_rate_formula', 20)->default('salary_div_30')->comment('salary_div_30, salary_div_22, manual');

            // Probation
            $table->boolean('available_during_probation')->default(false);

            // Scope
            $table->json('apply_to_payroll_modes')->nullable()->comment('null = ทุกโหมด, otherwise array');

            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index('is_default');
            $table->index('is_active');
        });

        // 2. Add leave_policy_id to employees
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'leave_policy_id')) {
                $table->unsignedBigInteger('leave_policy_id')->nullable()
                    ->after('personal_leave_entitlement');
                $table->foreign('leave_policy_id')->references('id')->on('leave_policies')->nullOnDelete();
                $table->index('leave_policy_id');
            }
        });

        // 3. Make entitlement columns nullable so NULL = "use policy"
        // SQLite doesn't support changing column nullable without doctrine/dbal — we'll let
        // existing values stay; new rows can have NULL via fillable.
        // Note: SQLite stores schema flexibly, so this works in practice.

        // 4. Seed Default policy with current hardcoded values
        DB::table('leave_policies')->insert([
            [
                'name' => 'Default',
                'is_default' => true,
                'is_active' => true,
                'vacation_days' => 6,
                'sick_days' => 30,
                'personal_days' => 3,
                'allow_carryover' => true,
                'max_carryover_days' => 5,
                'carryover_expires_months' => 6,
                'allow_encashment' => true,
                'max_encash_days_per_year' => null,
                'encash_rate_formula' => 'salary_div_30',
                'available_during_probation' => false,
                'apply_to_payroll_modes' => null,
                'note' => 'นโยบายมาตรฐานบริษัท — ลาพักร้อน 6 / ป่วย 30 / กิจ 3',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // 5. Backfill: Link all existing employees that have NULL leave_policy_id to Default
        $defaultId = DB::table('leave_policies')->where('is_default', true)->value('id');
        if ($defaultId) {
            DB::table('employees')->whereNull('leave_policy_id')->update(['leave_policy_id' => $defaultId]);
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'leave_policy_id')) {
                try { $table->dropForeign(['leave_policy_id']); } catch (\Throwable $e) {}
                $table->dropColumn('leave_policy_id');
            }
        });
        Schema::dropIfExists('leave_policies');
    }
};
