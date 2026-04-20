<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->string('entry_type', 20)->default('income')->after('notes');
        });

        // Migrate existing data: if notes='deduction', set entry_type='deduction'
        DB::table('work_logs')->where('notes', 'deduction')->update(['entry_type' => 'deduction']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropColumn('entry_type');
        });
    }
};
