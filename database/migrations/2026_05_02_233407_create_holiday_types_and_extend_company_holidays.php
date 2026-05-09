<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holiday_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->string('default_color', 30)->default('purple');
            $table->string('icon', 10)->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed system defaults so company_holidays can FK safely.
        // Color palette is intentionally limited (purple/sky/amber/emerald) for visual clarity.
        DB::table('holiday_types')->insert([
            ['code' => 'public',    'name' => 'วันหยุดราชการ',  'default_color' => 'purple',  'icon' => '🇹🇭', 'is_system' => true,  'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'company',   'name' => 'วันหยุดบริษัท',  'default_color' => 'sky',     'icon' => '🏢', 'is_system' => true,  'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'event',     'name' => 'อีเวนท์/พิเศษ',   'default_color' => 'amber',   'icon' => '🎉', 'is_system' => false, 'is_active' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'religious', 'name' => 'วันหยุดศาสนา',   'default_color' => 'emerald', 'icon' => '🙏', 'is_system' => false, 'is_active' => true, 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $companyTypeId = DB::table('holiday_types')->where('code', 'company')->value('id');

        Schema::table('company_holidays', function (Blueprint $table) use ($companyTypeId) {
            $table->foreignId('holiday_type_id')->nullable()->after('name')->constrained('holiday_types')->nullOnDelete();
            $table->string('color', 30)->nullable()->after('holiday_type_id');
        });

        // Backfill existing holidays as 'company' type
        DB::table('company_holidays')->whereNull('holiday_type_id')->update(['holiday_type_id' => $companyTypeId]);
    }

    public function down(): void
    {
        Schema::table('company_holidays', function (Blueprint $table) {
            $table->dropForeign(['holiday_type_id']);
            $table->dropColumn(['holiday_type_id', 'color']);
        });

        Schema::dropIfExists('holiday_types');
    }
};
