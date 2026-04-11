<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recording_jobs', function (Blueprint $table) {
            $table->time('scheduled_time')->nullable()->after('scheduled_date');
            $table->index(['scheduled_date', 'scheduled_time']);
        });
    }

    public function down(): void
    {
        Schema::table('recording_jobs', function (Blueprint $table) {
            $table->dropIndex('recording_jobs_scheduled_date_scheduled_time_index');
            $table->dropColumn('scheduled_time');
        });
    }
};
