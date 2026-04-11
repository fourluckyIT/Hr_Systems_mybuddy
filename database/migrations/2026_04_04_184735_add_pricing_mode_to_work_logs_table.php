<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->string('pricing_mode')->default('template')->after('amount');
            $table->decimal('custom_rate', 12, 4)->nullable()->after('pricing_mode');
            $table->string('pricing_template_label')->nullable()->after('custom_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropColumn(['pricing_mode', 'custom_rate', 'pricing_template_label']);
        });
    }
};
