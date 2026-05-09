<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_policies', function (Blueprint $table) {
            // Per-type probation gates per Thai labor law
            // Sick (ม.32) + Personal (ม.34) = allowed from day 1
            // Vacation (ม.30) = only after 1 year of service
            $table->boolean('sick_during_probation')->default(true)->after('available_during_probation');
            $table->boolean('personal_during_probation')->default(true)->after('sick_during_probation');
            $table->boolean('vacation_during_probation')->default(false)->after('personal_during_probation');
            // Vacation requires service of N months before becoming usable (default 12 per ม.30)
            $table->unsignedSmallInteger('vacation_eligibility_months')->default(12)->after('vacation_during_probation');
        });

        // Migrate existing flag → use as default for sick/personal (vacation always false during probation)
        \DB::statement("UPDATE leave_policies SET sick_during_probation = available_during_probation, personal_during_probation = available_during_probation, vacation_during_probation = 0");
    }

    public function down(): void
    {
        Schema::table('leave_policies', function (Blueprint $table) {
            $table->dropColumn(['sick_during_probation', 'personal_during_probation', 'vacation_during_probation', 'vacation_eligibility_months']);
        });
    }
};
