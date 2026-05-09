<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\CompanyHoliday;
use App\Models\DaySwapRequest;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class WelcomeController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');

        // === Holiday counter ===
        $today = Carbon::today();
        $upcomingHolidays = CompanyHoliday::with('holidayType')
            ->where('is_active', true)
            ->whereDate('holiday_date', '>=', $today)
            ->orderBy('holiday_date')
            ->limit(5)
            ->get()
            ->map(function ($h) use ($today) {
                $daysUntil = $today->diffInDays(Carbon::parse($h->holiday_date), false);
                return [
                    'date' => Carbon::parse($h->holiday_date),
                    'name' => $h->name,
                    'icon' => $h->effective_icon,
                    'color' => $h->effective_color,
                    'type_name' => $h->holidayType?->name ?? 'วันหยุด',
                    'days_until' => max(0, (int) $daysUntil),
                ];
            });

        $holidaysThisMonth = CompanyHoliday::where('is_active', true)
            ->whereMonth('holiday_date', $today->month)
            ->whereYear('holiday_date', $today->year)
            ->count();

        // === Warnings ===
        $warnings = collect();

        if ($isAdmin) {
            $pendingLeaves = LeaveRequest::where('status', 'pending')->count();
            if ($pendingLeaves > 0) {
                $warnings->push([
                    'icon' => '🏖️',
                    'message' => "มีคำขอลา <strong>{$pendingLeaves}</strong> รายการรออนุมัติ",
                    'link' => route('leave.index'),
                    'cta' => 'ดูทั้งหมด',
                ]);
            }

            $pendingSwaps = DaySwapRequest::where('status', 'pending')->count();
            if ($pendingSwaps > 0) {
                $warnings->push([
                    'icon' => '⇄',
                    'message' => "มีคำขอสลับวัน <strong>{$pendingSwaps}</strong> รายการรออนุมัติ",
                    'link' => route('leave.index'),
                    'cta' => 'ดูทั้งหมด',
                ]);
            }
        } else {
            $myEmployeeId = $user->employee?->id;
            if ($myEmployeeId) {
                // (Removed) "missing attendance" warning — employees don't clock in;
                // admin records attendance at end of month, so this would mislead users.

                $myPendingLeaves = LeaveRequest::where('employee_id', $myEmployeeId)
                    ->where('status', 'pending')->count();
                if ($myPendingLeaves > 0) {
                    $warnings->push([
                        'icon' => '⏳',
                        'message' => "คำขอลาของคุณ <strong>{$myPendingLeaves}</strong> รายการกำลังรออนุมัติ",
                        'link' => route('leave.index'),
                        'cta' => 'ดู',
                    ]);
                }
            }
        }

        return view('welcome', compact('upcomingHolidays', 'holidaysThisMonth', 'warnings', 'isAdmin'));
    }
}
