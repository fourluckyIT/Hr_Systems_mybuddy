<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recording_jobs', function (Blueprint $table) {
            $table->unsignedInteger('footage_count')->nullable()->after('planned_duration_minutes');
            $table->unsignedInteger('longest_footage_seconds')->nullable()->after('footage_count');
        });
    }

    public function down(): void
    {
        Schema::table('recording_jobs', function (Blueprint $table) {
            $table->dropColumn(['footage_count', 'longest_footage_seconds']);
        });
    }
};
