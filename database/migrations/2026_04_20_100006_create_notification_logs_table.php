<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recipient_user_id');
            $table->string('type', 40); // ot.requested, ot.approved, job.assigned, etc.
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('link_url')->nullable();
            $table->json('context')->nullable();
            $table->string('channel', 20)->default('in_app'); // in_app, email
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('recipient_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['recipient_user_id', 'read_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
