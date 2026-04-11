<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_resources', function (Blueprint $table) {
            $table->unsignedInteger('footage_count')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('media_resources', function (Blueprint $table) {
            $table->dropColumn('footage_count');
        });
    }
};