<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('expense_category_id')->nullable()->after('id');
            $table->date('entry_date')->nullable()->after('year');
            $table->boolean('is_recurring')->default(false)->after('entry_date');
            $table->string('status', 20)->default('paid')->after('is_recurring'); // paid, pending_approval, scheduled
            $table->unsignedBigInteger('approved_by')->nullable()->after('status');

            $table->foreign('expense_category_id')->references('id')->on('expense_categories')->nullOnDelete();
            $table->index('expense_category_id');
        });

        Schema::table('company_revenues', function (Blueprint $table) {
            $table->unsignedBigInteger('expense_category_id')->nullable()->after('id');
            $table->date('entry_date')->nullable()->after('year');
            $table->string('status', 20)->default('received')->after('entry_date'); // received, pending

            $table->foreign('expense_category_id')->references('id')->on('expense_categories')->nullOnDelete();
            $table->index('expense_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('company_expenses', function (Blueprint $table) {
            $table->dropForeign(['expense_category_id']);
            $table->dropColumn(['expense_category_id', 'entry_date', 'is_recurring', 'status', 'approved_by']);
        });
        Schema::table('company_revenues', function (Blueprint $table) {
            $table->dropForeign(['expense_category_id']);
            $table->dropColumn(['expense_category_id', 'entry_date', 'status']);
        });
    }
};
