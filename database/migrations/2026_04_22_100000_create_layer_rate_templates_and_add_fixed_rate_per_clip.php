<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('layer_rate_templates', function (Blueprint $table) {
            $table->id();
            $table->string('label')->nullable();
            $table->unsignedInteger('layer_from');
            $table->unsignedInteger('layer_to');
            $table->decimal('rate_per_minute', 12, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'layer_from']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('fixed_rate_per_clip', 12, 2)->nullable()->after('advance_ceiling_percent');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('fixed_rate_per_clip');
        });
        Schema::dropIfExists('layer_rate_templates');
    }
};
