<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_carryovers', function (Blueprint $table) {
            $table->string('status', 20)->default('approved')->after('note')->index();
            $table->unsignedBigInteger('approved_by')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->string('rejection_reason', 500)->nullable()->after('approved_at');
        });

        Schema::table('leave_encashments', function (Blueprint $table) {
            $table->string('status', 20)->default('approved')->after('note')->index();
            $table->unsignedBigInteger('approved_by')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->string('rejection_reason', 500)->nullable()->after('approved_at');
        });

        // Existing rows are admin-created → mark as approved with creator as approver
        \DB::statement("UPDATE leave_carryovers SET status='approved', approved_by=created_by, approved_at=created_at WHERE status='approved' AND approved_at IS NULL");
        \DB::statement("UPDATE leave_encashments SET status='approved', approved_by=created_by, approved_at=created_at WHERE status='approved' AND approved_at IS NULL");
    }

    public function down(): void
    {
        Schema::table('leave_carryovers', function (Blueprint $table) {
            $table->dropColumn(['status', 'approved_by', 'approved_at', 'rejection_reason']);
        });
        Schema::table('leave_encashments', function (Blueprint $table) {
            $table->dropColumn(['status', 'approved_by', 'approved_at', 'rejection_reason']);
        });
    }
};
