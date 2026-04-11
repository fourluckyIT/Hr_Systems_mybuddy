<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            // source_flag: 'auto' (from edit job), 'manual' (hand-entered in workspace)
            $table->string('source_flag', 20)->default('manual')->after('entry_type');
            // edit_job_id: links work log back to the edit job that generated it
            $table->unsignedBigInteger('edit_job_id')->nullable()->after('source_flag');

            $table->index('edit_job_id');
        });
    }

    public function down(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropIndex(['edit_job_id']);
            $table->dropColumn(['source_flag', 'edit_job_id']);
        });
    }
};
