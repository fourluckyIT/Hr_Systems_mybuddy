<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->string('ot_status', 20)->default('none')->after('ot_enabled');
            $table->text('ot_request_note')->nullable()->after('ot_status');
            $table->unsignedBigInteger('ot_request_id')->nullable()->after('ot_request_note');

            $table->index('ot_status');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropIndex(['ot_status']);
            $table->dropColumn(['ot_status', 'ot_request_note', 'ot_request_id']);
        });
    }
};
