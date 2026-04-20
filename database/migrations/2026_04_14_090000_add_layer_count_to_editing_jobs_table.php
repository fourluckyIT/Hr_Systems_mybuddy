<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editing_jobs', function (Blueprint $table) {
            $table->unsignedInteger('layer_count')->nullable()->after('deadline_days');
        });
    }

    public function down(): void
    {
        Schema::table('editing_jobs', function (Blueprint $table) {
            $table->dropColumn('layer_count');
        });
    }
};
