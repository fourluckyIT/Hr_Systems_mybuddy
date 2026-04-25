<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editing_jobs', function (Blueprint $table) {
            $table->unsignedBigInteger('youtuber_id')->nullable()->after('game_id');
            $table->foreign('youtuber_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('editing_jobs', function (Blueprint $table) {
            $table->dropForeign(['youtuber_id']);
            $table->dropColumn('youtuber_id');
        });
    }
};
