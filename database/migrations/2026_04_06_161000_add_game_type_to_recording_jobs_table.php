<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recording_jobs', function (Blueprint $table) {
            $table->string('game_type', 100)->nullable()->after('title');
            $table->index('game_type');
        });
    }

    public function down(): void
    {
        Schema::table('recording_jobs', function (Blueprint $table) {
            $table->dropIndex('recording_jobs_game_type_index');
            $table->dropColumn('game_type');
        });
    }
};
