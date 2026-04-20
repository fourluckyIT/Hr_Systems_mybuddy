<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonus_cycle_selected_months', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cycle_id');
            $table->unsignedSmallInteger('selected_year');
            $table->unsignedTinyInteger('selected_month');
            $table->string('selected_by', 100)->nullable();
            $table->timestamp('selected_at')->useCurrent();
            $table->timestamps();

            $table->foreign('cycle_id')->references('id')->on('bonus_cycles')->onDelete('cascade');
            $table->unique(['cycle_id', 'selected_year', 'selected_month'], 'bonus_cycle_month_unique');
            $table->index(['selected_year', 'selected_month'], 'bonus_cycle_month_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_cycle_selected_months');
    }
};
