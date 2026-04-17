<?php

namespace App\Http\Controllers;

use App\Models\PayrollBatch;
use App\Models\Payslip;
use Illuminate\Http\Request;

class PayrollBatchController extends Controller
{
    public function index()
    {
        // View all months that have any payslips (draft or finalized)
        // Group by year and month
        $months = Payslip::select('year', 'month')
            ->selectRaw('COUNT(id) as total_slips')
            ->selectRaw('SUM(CASE WHEN status = "finalized" THEN 1 ELSE 0 END) as finalized_slips')
            ->selectRaw('SUM(CASE WHEN status = "finalized" THEN total_income ELSE 0 END) as total_income')
            ->selectRaw('SUM(CASE WHEN status = "finalized" THEN total_deduction ELSE 0 END) as total_deduction')
            ->selectRaw('SUM(CASE WHEN status = "finalized" THEN net_pay ELSE 0 END) as net_pay')
            ->groupBy('year', 'month')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();
            
        $monthNames = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];

        return view('payroll-batches.index', compact('months', 'monthNames'));
    }
    
    public function show($year, $month)
    {
        $year = (int) $year;
        $month = (int) $month;
        
        $payslips = Payslip::with(['employee.department', 'employee.position', 'employee'])
            ->where('year', $year)
            ->where('month', $month)
            ->get();
            
        if ($payslips->isEmpty()) {
            return redirect()->route('payroll-batches.index')->withErrors(['error' => 'ไม่พบข้อมูลรอบบิลนี้']);
        }
        
        $monthNames = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        $monthName = $monthNames[$month] ?? '';
        
        $summary = [
            'total_slips' => $payslips->count(),
            'finalized_slips' => $payslips->where('status', 'finalized')->count(),
            'total_income' => $payslips->where('status', 'finalized')->sum('total_income'),
            'total_deduction' => $payslips->where('status', 'finalized')->sum('total_deduction'),
            'net_pay' => $payslips->where('status', 'finalized')->sum('net_pay'),
        ];
        
        return view('payroll-batches.show', compact('payslips', 'year', 'month', 'monthName', 'summary'));
    }
}
