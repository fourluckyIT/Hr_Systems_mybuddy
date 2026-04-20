<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_log_types', function (Blueprint $table) {
            $table->string('module_key')->default('workspace')->after('code');
            $table->string('footage_size')->nullable()->after('payroll_mode');
            $table->decimal('target_length_minutes', 8, 2)->nullable()->after('footage_size');
            $table->decimal('default_rate_per_minute', 12, 4)->nullable()->after('target_length_minutes');
            $table->unsignedInteger('sort_order')->default(0)->after('default_rate_per_minute');
            $table->text('description')->nullable()->after('sort_order');
            $table->json('config')->nullable()->after('description');

            $table->index(['module_key', 'is_active']);
            $table->index(['payroll_mode', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('work_log_types', function (Blueprint $table) {
            $table->dropIndex(['module_key', 'is_active']);
            $table->dropIndex(['payroll_mode', 'is_active']);
            $table->dropColumn([
                'module_key',
                'footage_size',
                'target_length_minutes',
                'default_rate_per_minute',
                'sort_order',
                'description',
                'config',
            ]);
        });
    }
};
