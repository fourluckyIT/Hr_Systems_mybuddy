<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NotificationDispatchAgent (A7) audit trail.
 * Used by priority-aware dedupe in NotificationDispatchAgent.
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $t) {
            $t->id();
            $t->string('event_key', 64);
            $t->string('priority', 16)->default('Medium');
            $t->string('dedupe_key', 128);       // event_key + entity_id + state hash
            $t->unsignedBigInteger('recipient_id');
            $t->string('recipient_role', 24);
            $t->string('channel', 24)->default('in_app'); // in_app | email
            $t->timestamp('sent_at')->useCurrent();
            $t->json('payload')->nullable();
            $t->timestamps();

            $t->index(['dedupe_key', 'sent_at'], 'idx_notif_dedupe');
            $t->index(['recipient_id', 'sent_at'], 'idx_notif_recipient');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
