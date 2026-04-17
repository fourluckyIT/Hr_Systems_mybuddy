<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PayrollBatch;
use App\Models\PayrollItem;
use App\Models\Payslip;
use Illuminate\Http\Request;

class AnnualSummaryController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) ($request->get('year') ?: now()->year);
        $monthNames = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

        $employees = Employee::with('profile')
            ->where('is_active', true)
            ->orderBy('first_name')
            ->get();

        // Pre-load all batches for this year
        $batches = PayrollBatch::where('year', $year)->get()->keyBy('month');

        // Build per-employee annual data
        $employeeData = [];
        foreach ($employees as $emp) {
            $monthlyPay = [];
            $totalIncome = 0;
            $totalDeduction = 0;
            $totalNet = 0;

            for ($m = 1; $m <= 12; $m++) {
                $batch = $batches->get($m);
                if (!$batch) {
                    $monthlyPay[$m] = ['income' => 0, 'deduction' => 0, 'net' => 0, 'finalized' => false];
                    continue;
                }

                $payslip = Payslip::where('employee_id', $emp->id)
                    ->where('month', $m)->where('year', $year)
                    ->where('status', 'finalized')->first();

                if ($payslip) {
                    $income = $payslip->total_income;
                    $deduction = $payslip->total_deduction;
                    $net = $payslip->net_pay;
                    $finalized = true;
                } else {
                    $income = 0;
                    $deduction = 0;
                    $net = 0;
                    $finalized = false;
                }

                $monthlyPay[$m] = [
                    'income' => round((float)$income, 2),
                    'deduction' => round((float)$deduction, 2),
                    'net' => round((float)$net, 2),
                    'finalized' => $finalized,
                ];

                $totalIncome += $income;
                $totalDeduction += $deduction;
                $totalNet += $net;
            }

            $employeeData[] = [
                'employee' => $emp,
                'monthly' => $monthlyPay,
                'total_income' => round($totalIncome, 2),
                'total_deduction' => round($totalDeduction, 2),
                'total_net' => round($totalNet, 2),
            ];
        }

        return view('annual.index', compact('year', 'monthNames', 'employeeData'));
    }
}
