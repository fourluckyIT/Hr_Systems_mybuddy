<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('recording_sessions', function (Blueprint $table) {
            $table->id();
            $table->date('session_date');
            $table->string('title');
            $table->foreignId('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('session_date');
        });

        Schema::create('recording_session_youtuber', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recording_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['recording_session_id', 'employee_id'], 'rec_session_youtuber_unique');
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recording_session_youtuber');
        Schema::dropIfExists('recording_sessions');
    }
};
