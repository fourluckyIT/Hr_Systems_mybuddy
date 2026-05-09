<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Remove duplicates by holiday_date — keep the OLDEST id per date.
        //    Doing it as a single delete keeps the migration database-agnostic.
        $duplicateIds = DB::table('company_holidays as h1')
            ->join('company_holidays as h2', function ($join) {
                $join->on('h1.holiday_date', '=', 'h2.holiday_date')
                     ->whereColumn('h1.id', '>', 'h2.id');
            })
            ->pluck('h1.id')
            ->unique()
            ->values()
            ->all();

        if (!empty($duplicateIds)) {
            DB::table('company_holidays')->whereIn('id', $duplicateIds)->delete();
        }

        // 2) Add a unique index so duplicates cannot return.
        Schema::table('company_holidays', function (Blueprint $table) {
            $table->unique('holiday_date', 'company_holidays_holiday_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('company_holidays', function (Blueprint $table) {
            $table->dropUnique('company_holidays_holiday_date_unique');
        });
    }
};
