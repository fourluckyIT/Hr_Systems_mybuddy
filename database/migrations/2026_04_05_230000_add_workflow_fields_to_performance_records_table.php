<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_records', function (Blueprint $table) {
            $table->string('action_select')->nullable()->after('status');
            $table->decimal('quality_score', 3, 1)->nullable()->after('action_select');
            $table->text('reject_reason')->nullable()->after('quality_score');
            $table->timestamp('confirmed_finished_at')->nullable()->after('reject_reason');
        });
    }

    public function down(): void
    {
        Schema::table('performance_records', function (Blueprint $table) {
            $table->dropColumn([
                'action_select',
                'quality_score',
                'reject_reason',
                'confirmed_finished_at',
            ]);
        });
    }
};
