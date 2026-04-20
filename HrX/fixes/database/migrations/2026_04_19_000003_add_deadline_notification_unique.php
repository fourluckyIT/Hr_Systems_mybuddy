<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Fix BUG-09. Idempotency key so A2's cron run and A2's on-state-change
 * call can't both insert the same deadline notification.
 */
return new class extends Migration {

    public function up(): void
    {
        if (!Schema::hasTable('deadline_notifications')) {
            Schema::create('deadline_notifications', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('editing_job_id');
                $t->string('notification_type', 48);
                $t->date('notification_date');
                $t->json('payload')->nullable();
                $t->timestamps();
                $t->index('editing_job_id');
            });
        }

        DB::statement("
            ALTER TABLE deadline_notifications
              ADD UNIQUE KEY uq_dln_job_type_date
              (editing_job_id, notification_type, notification_date)
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE deadline_notifications DROP INDEX uq_dln_job_type_date");
    }
};
