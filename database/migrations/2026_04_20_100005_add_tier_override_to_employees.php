<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // When non-null, overrides tier logic for this specific employee
            $table->unsignedBigInteger('tier_override_id')->nullable()->after('position_id');
            $table->string('tier_source', 20)->default('avg'); // avg, monthly_total, manual
            $table->text('tier_override_note')->nullable();

            $table->foreign('tier_override_id')->references('id')->on('performance_tiers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['tier_override_id']);
            $table->dropColumn(['tier_override_id', 'tier_source', 'tier_override_note']);
        });
    }
};
