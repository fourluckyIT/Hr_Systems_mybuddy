<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix BUG-08. Guarantees one payslip per (batch, employee) even if two admins
 * race the finalize button. The service layer also wraps finalize in a
 * transaction with lockForUpdate, but this is the DB-level safety net.
 */
return new class extends Migration {

    public function up(): void
    {
        // Clean out any accidental duplicates before adding the unique index.
        DB::statement("
            DELETE p1 FROM payslips p1
            INNER JOIN payslips p2
              ON p1.id > p2.id
             AND p1.payroll_batch_id = p2.payroll_batch_id
             AND p1.employee_id     = p2.employee_id
        ");

        DB::statement("
            ALTER TABLE payslips
              ADD UNIQUE KEY uq_payslip_month (payroll_batch_id, employee_id)
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payslips DROP INDEX uq_payslip_month");
    }
};
