<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_tiers', function (Blueprint $table) {
            $table->unsignedInteger('min_clip_minutes_per_month')->nullable()->after('multiplier');
            $table->unsignedInteger('max_clip_minutes_per_month')->nullable()->after('min_clip_minutes_per_month');
            $table->unsignedSmallInteger('min_qualified_months')->nullable()->after('max_clip_minutes_per_month');
            $table->unsignedSmallInteger('max_qualified_months')->nullable()->after('min_qualified_months');
            $table->boolean('auto_select_enabled')->default(true)->after('is_active');

            $table->index(['is_active', 'auto_select_enabled', 'display_order'], 'performance_tiers_auto_idx');
        });
    }

    public function down(): void
    {
        Schema::table('performance_tiers', function (Blueprint $table) {
            $table->dropIndex('performance_tiers_auto_idx');
            $table->dropColumn([
                'min_clip_minutes_per_month',
                'max_clip_minutes_per_month',
                'min_qualified_months',
                'max_qualified_months',
                'auto_select_enabled',
            ]);
        });
    }
};
