<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Fix BUG-02, BUG-03, BUG-25.
 *
 * - Adds source_flag CHECK so only 'auto','manual','override' are allowed.
 * - Adds source_ref_type / source_ref_id columns for unambiguous de-duplication
 *   (replaces the label+amount dedupe that produced false positives).
 * - Adds composite indexes that every payroll recalc and guard check hits.
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $t) {
            if (!Schema::hasColumn('payroll_items', 'source_ref_type')) {
                $t->string('source_ref_type', 64)->nullable()->after('source_flag');
            }
            if (!Schema::hasColumn('payroll_items', 'source_ref_id')) {
                $t->unsignedBigInteger('source_ref_id')->nullable()->after('source_ref_type');
            }
            $t->index(['payroll_batch_id', 'employee_id'], 'idx_pi_batch_emp');
            $t->index(['payroll_batch_id', 'source_flag'], 'idx_pi_source');
        });

        // Unique index by real source reference. NULL source_ref_id rows (truly manual
        // rows with no upstream record) are permitted to duplicate — that is intentional.
        DB::statement("
            ALTER TABLE payroll_items
              ADD UNIQUE KEY uq_payroll_item_source
              (payroll_batch_id, employee_id, source_ref_type, source_ref_id)
        ");

        DB::statement("
            ALTER TABLE payroll_items
              ADD CONSTRAINT chk_source_flag
              CHECK (source_flag IN ('auto','manual','override'))
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payroll_items DROP CONSTRAINT chk_source_flag");
        DB::statement("ALTER TABLE payroll_items DROP INDEX uq_payroll_item_source");

        Schema::table('payroll_items', function (Blueprint $t) {
            $t->dropIndex('idx_pi_batch_emp');
            $t->dropIndex('idx_pi_source');
            if (Schema::hasColumn('payroll_items', 'source_ref_id')) {
                $t->dropColumn('source_ref_id');
            }
            if (Schema::hasColumn('payroll_items', 'source_ref_type')) {
                $t->dropColumn('source_ref_type');
            }
        });
    }
};
