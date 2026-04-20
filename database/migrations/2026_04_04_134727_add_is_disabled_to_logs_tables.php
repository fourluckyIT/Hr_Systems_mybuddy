<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->boolean('is_disabled')->default(false)->after('lwop_flag');
        });

        Schema::table('work_logs', function (Blueprint $table) {
            $table->boolean('is_disabled')->default(false)->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropColumn('is_disabled');
        });

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn('is_disabled');
        });
    }
};
