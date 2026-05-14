<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('doc_type', 32);              // 'leave','ot','swap','expense','carryover','encash'
            $table->string('variant_key', 64)->nullable(); // e.g. 'vacation_leave','cancelled','default'
            $table->string('name', 120);
            $table->string('image_path');                // disk: public
            $table->unsignedInteger('image_width');      // px in the source image
            $table->unsignedInteger('image_height');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['doc_type', 'variant_key', 'is_active']);
        });

        Schema::create('document_template_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('document_templates')->cascadeOnDelete();
            $table->string('field_key', 64);             // catalog key, e.g. 'employee_name'
            $table->integer('x');                        // px from left of source image
            $table->integer('y');                        // px from top
            $table->unsignedInteger('width')->nullable(); // optional wrap width
            $table->unsignedSmallInteger('font_size')->default(16); // px in source image
            $table->string('font_weight', 16)->default('normal');   // 'normal'|'bold'
            $table->string('align', 8)->default('left');            // 'left'|'center'|'right'
            $table->boolean('is_checkbox')->default(false);         // if true, render '✓' only when checkbox_when matches
            $table->string('checkbox_when', 64)->nullable();        // value to compare against resolved field value
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_template_fields');
        Schema::dropIfExists('document_templates');
    }
};
