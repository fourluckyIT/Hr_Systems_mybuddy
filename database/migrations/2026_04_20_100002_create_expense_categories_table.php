<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20); // income, expense
            $table->string('color', 20)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        // Seed default categories
        $defaults = [
            ['name' => 'YouTube AdSense', 'type' => 'income', 'color' => 'red', 'sort_order' => 1],
            ['name' => 'Brand Deal', 'type' => 'income', 'color' => 'purple', 'sort_order' => 2],
            ['name' => 'Service Revenue', 'type' => 'income', 'color' => 'green', 'sort_order' => 3],
            ['name' => 'Other Income', 'type' => 'income', 'color' => 'gray', 'sort_order' => 99],
            ['name' => 'Software/Subscription', 'type' => 'expense', 'color' => 'indigo', 'sort_order' => 1],
            ['name' => 'อุปกรณ์/Hardware', 'type' => 'expense', 'color' => 'amber', 'sort_order' => 2],
            ['name' => 'ค่า Dubbing/VA', 'type' => 'expense', 'color' => 'pink', 'sort_order' => 3],
            ['name' => 'ค่าสถานที่', 'type' => 'expense', 'color' => 'teal', 'sort_order' => 4],
            ['name' => 'Marketing', 'type' => 'expense', 'color' => 'orange', 'sort_order' => 5],
            ['name' => 'Other Expense', 'type' => 'expense', 'color' => 'gray', 'sort_order' => 99],
        ];

        $now = now();
        foreach ($defaults as &$row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }
        \DB::table('expense_categories')->insert($defaults);
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
