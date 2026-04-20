<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editing_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_name');
            $table->unsignedBigInteger('game_id');
            $table->string('game_link', 500)->nullable();
            
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by');
            $table->timestamp('assigned_at')->nullable();
            
            $table->integer('deadline_days')->default(7);
            $table->date('deadline_date')->nullable();
            
            $table->string('status')->default('assigned'); // assigned, in_progress, review_ready, final
            
            $table->timestamp('started_at')->nullable();
            $table->timestamp('review_ready_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            
            $table->text('notes')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->foreign('game_id')->references('id')->on('games');
            $table->foreign('assigned_to')->references('id')->on('employees');
            $table->foreign('assigned_by')->references('id')->on('employees');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editing_jobs');
    }
};
