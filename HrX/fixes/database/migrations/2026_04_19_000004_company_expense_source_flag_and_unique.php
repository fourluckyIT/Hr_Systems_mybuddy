<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Fix BUG-11. Adds source_flag column on company_expenses (manual / agent_draft)
 * and a unique key that prevents A6 from creating two payroll drafts in the same
 * month when the Payslip-finalized trigger and the monthly cron run back-to-back.
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::table('company_expenses', function (Blueprint $t) {
            if (!Schema::hasColumn('company_expenses', 'source_flag')) {
                $t->string('source_flag', 24)->default('manual')->after('amount');
            }
            if (!Schema::hasColumn('company_expenses', 'year')) {
                $t->smallInteger('year')->unsigned()->after('source_flag');
                $t->tinyInteger('month')->unsigned()->after('year');
            }
        });

        DB::statement("
            ALTER TABLE company_expenses
              ADD UNIQUE KEY uq_expense_month_cat (year, month, category, source_flag)
        ");

        DB::statement("
            ALTER TABLE company_expenses
              ADD CONSTRAINT chk_expense_source
              CHECK (source_flag IN ('manual','agent_draft','agent_approved'))
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE company_expenses DROP CONSTRAINT chk_expense_source");
        DB::statement("ALTER TABLE company_expenses DROP INDEX uq_expense_month_cat");
    }
};
