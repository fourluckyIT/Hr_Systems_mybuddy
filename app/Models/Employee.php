<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'employee_code', 'first_name', 'last_name', 'nickname',
        'department_id', 'position_id', 'payroll_mode', 'advance_ceiling_percent', 'status', 'is_active',
        'start_date', 'probation_end_date', 'end_date',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'start_date' => 'date',
            'probation_end_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->nickname ?: $this->first_name;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function profile()
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function salaryProfile()
    {
        return $this->hasOne(EmployeeSalaryProfile::class)->where('is_current', true);
    }

    public function salaryHistory()
    {
        return $this->hasMany(EmployeeSalaryProfile::class)->orderBy('effective_date', 'desc');
    }

    public function bankAccount()
    {
        return $this->hasOne(EmployeeBankAccount::class)->where('is_primary', true);
    }

    public function bankAccounts()
    {
        return $this->hasMany(EmployeeBankAccount::class);
    }

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function workLogs()
    {
        return $this->hasMany(WorkLog::class);
    }

    public function payrollItems()
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function payslips()
    {
        return $this->hasMany(Payslip::class);
    }

    public function moduleToggles()
    {
        return $this->hasMany(ModuleToggle::class);
    }

    public function layerRateRules()
    {
        return $this->hasMany(LayerRateRule::class);
    }

    public function rateRules()
    {
        return $this->hasMany(RateRule::class);
    }

    public function expenseClaims()
    {
        return $this->hasMany(ExpenseClaim::class);
    }

    public function performanceRecords()
    {
        return $this->hasMany(PerformanceRecord::class);
    }

    public function workAssignments()
    {
        return $this->hasMany(WorkAssignment::class);
    }

    public function editingJobs()
    {
        return $this->hasMany(EditingJob::class, 'assigned_to');
    }

    public function isModuleEnabled(string $moduleName): bool
    {
        $toggle = $this->moduleToggles()->where('module_name', $moduleName)->first();
        return $toggle ? $toggle->is_enabled : false;
    }

    public function getAverageMinutesLast3MonthsAttribute(): float
    {
        $threeMonthsAgo = now()->subMonths(3)->startOfMonth();
        
        $totalMinutes = $this->workLogs()
            ->where('is_disabled', false)
            ->where(function ($q) use ($threeMonthsAgo) {
                $q->where('year', '>', $threeMonthsAgo->year)
                  ->orWhere(function ($sq) use ($threeMonthsAgo) {
                      $sq->where('year', $threeMonthsAgo->year)
                        ->where('month', '>=', $threeMonthsAgo->month);
                  });
            })
            ->get()
            ->sum(function ($log) {
                return ($log->hours * 60) + $log->minutes + ($log->seconds / 60);
            });

        return round($totalMinutes / 3, 2);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
