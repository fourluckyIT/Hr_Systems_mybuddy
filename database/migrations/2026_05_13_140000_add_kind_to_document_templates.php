<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            // 'image' = legacy image-overlay flow (PNG/JPG + coordinate fields)
            // 'docx'  = Word template with ${placeholder} syntax, filled via PHPWord + converted to PDF
            $table->string('kind', 16)->default('image')->after('doc_type');
        });

        // image_width/height only meaningful for 'image' templates — make them nullable for 'docx'
        // SQLite doesn't support change() without doctrine/dbal; the table is small so we leave as-is
        // (existing rows already have values; new docx rows will store 0)
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
