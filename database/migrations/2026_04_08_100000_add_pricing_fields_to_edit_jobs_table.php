<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edit_jobs', function (Blueprint $table) {
            // pricing_group: 'template' (use layer_rate_rules) or 'isolated' (custom rate per job)
            $table->string('pricing_group', 30)->nullable()->after('notes');
            // pricing_template_label: e.g. "L1-5", "L6-10" — only for pricing_group = 'template'
            $table->string('pricing_template_label', 50)->nullable()->after('pricing_group');
            // assigned_rate: locked rate at time of assignment (rate_per_minute for layer, or custom rate for isolated)
            $table->decimal('assigned_rate', 12, 4)->nullable()->after('pricing_template_label');
            // For freelance_fixed: quantity and fixed_rate per piece
            $table->integer('assigned_quantity')->nullable()->after('assigned_rate');
            $table->decimal('assigned_fixed_rate', 12, 2)->nullable()->after('assigned_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('edit_jobs', function (Blueprint $table) {
            $table->dropColumn(['pricing_group', 'pricing_template_label', 'assigned_rate', 'assigned_quantity', 'assigned_fixed_rate']);
        });
    }
};
