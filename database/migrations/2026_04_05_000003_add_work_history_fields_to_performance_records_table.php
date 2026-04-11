<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_records', function (Blueprint $table) {
            $table->string('work_title')->nullable()->after('year');
            $table->string('video_title')->nullable()->after('work_title');
            $table->unsignedInteger('layer')->nullable()->after('video_title');
            $table->unsignedInteger('hours')->default(0)->after('layer');
            $table->unsignedInteger('minutes')->default(0)->after('hours');
            $table->unsignedInteger('seconds')->default(0)->after('minutes');
            $table->unsignedInteger('quantity')->default(0)->after('seconds');
            $table->decimal('rate_snapshot', 12, 4)->default(0)->after('quantity');
            $table->decimal('amount_snapshot', 12, 2)->default(0)->after('rate_snapshot');
            $table->string('status')->default('draft')->after('amount_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('performance_records', function (Blueprint $table) {
            $table->dropColumn([
                'work_title',
                'video_title',
                'layer',
                'hours',
                'minutes',
                'seconds',
                'quantity',
                'rate_snapshot',
                'amount_snapshot',
                'status',
            ]);
        });
    }
};
