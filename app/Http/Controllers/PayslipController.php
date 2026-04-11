<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Payslip;
use App\Models\PayrollItem;
use App\Models\PayrollBatch;
use App\Models\CompanyProfile;
use App\Models\LayerRateRule;
use App\Models\PaymentProof;
use App\Services\Payroll\PayrollCalculationService;
use App\Services\AuditLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    public function __construct(
        protected PayrollCalculationService $payrollService
    ) {}

    public function preview(Employee $employee, int $month, int $year)
    {
        try {
            // Validate month and year
            if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
                return back()->withErrors(['error' => 'เดือนหรือปีไม่ถูกต้อง']);
            }

            $employee->load(['department', 'position', 'salaryProfile', 'bankAccount', 'profile']);

            // Get payslip (finalized) or calculate live
            $payslip = Payslip::where('employee_id', $employee->id)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            $result = null;

            if ($payslip && $payslip->status === 'finalized') {
                $payslip->load(['incomeItems', 'deductionItems']);
                $incomeItems = $payslip->incomeItems;
                $deductionItems = $payslip->deductionItems;
                $totalIncome = (float) $payslip->total_income;
                $totalDeduction = (float) $payslip->total_deduction;
                $netPay = (float) $payslip->net_pay;
            } else {
                // Calculate live
                $result = $this->payrollService->calculateForEmployee($employee, $month, $year);
                // Ensure items are arrays for consistent view rendering
                $items = collect($result['items'] ?? []);
                $incomeItems = $items->where('category', 'income')->map(function($item) {
                    return is_array($item) ? $item : (array)$item;
                })->values();
                $deductionItems = $items->where('category', 'deduction')->map(function($item) {
                    return is_array($item) ? $item : (array)$item;
                })->values();
                $totalIncome = $result['summary']['total_income'] ?? 0;
                $totalDeduction = $result['summary']['total_deduction'] ?? 0;
                $netPay = $result['summary']['net_pay'] ?? 0;
            }

            $yearToDate = $this->buildYearToDateSummary($employee, $month, $year);
            $monthlyStats = $this->buildMonthlyStats($employee, $month, $year, $result);
            
            // Get company profile (active)
            $company = CompanyProfile::active();

            // Layer rates for freelance_layer mode
            $layerRates = collect();
            if ($employee->payroll_mode === 'freelance_layer') {
                $layerRates = LayerRateRule::where('employee_id', $employee->id)
                    ->where('is_active', true)
                    ->orderBy('layer_from')
                    ->get();
            }

            // Payment proofs for this month
            $proofs = PaymentProof::where('employee_id', $employee->id)
                ->whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->orderByDesc('created_at')
                ->get();

            return view('payslip.preview', compact(
                'employee', 'month', 'year',
                'incomeItems', 'deductionItems',
                'totalIncome', 'totalDeduction', 'netPay',
                'payslip', 'yearToDate', 'company', 'monthlyStats',
                'layerRates', 'proofs'
            ));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('payslip preview error', [
                'employee_id' => $employee->id,
                'month' => $month,
                'year' => $year,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการโหลด Payslip: ' . $e->getMessage()]);
        }
    }

    public function finalize(Employee $employee, int $month, int $year)
    {
        try {
            // Validate month and year
            if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
                return back()->withErrors(['error' => 'เดือนหรือปีไม่ถูกต้อง']);
            }

            // Always guarantee PayrollItems are up-to-date before snapshot
            $result = $this->payrollService->calculateForEmployee($employee, $month, $year);
            $this->payrollService->savePayrollItems($employee, $month, $year, $result);

            $payslip = $this->payrollService->finalizePayslip($employee, $month, $year);

            AuditLogService::log($payslip, 'finalized', 'status', 'draft', 'finalized', 'Payslip finalized');

            return redirect()
                ->route('payslip.preview', ['employee' => $employee->id, 'month' => $month, 'year' => $year])
                ->with('success', 'Finalize payslip สำเร็จ');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('finalize error', [
                'employee_id' => $employee->id,
                'month'       => $month,
                'year'        => $year,
                'error'       => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการ Finalize: ' . $e->getMessage()]);
        }
    }

    public function downloadPdf(Employee $employee, int $month, int $year)
    {
        try {
            // Validate month and year
            if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
                return back()->withErrors(['error' => 'เดือนหรือปีไม่ถูกต้อง']);
            }

            $employee->load(['department', 'position', 'salaryProfile', 'bankAccount', 'profile']);

            $payslip = Payslip::where('employee_id', $employee->id)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            if (!$payslip) {
                // Create snapshot first
                $result = $this->payrollService->calculateForEmployee($employee, $month, $year);
                $this->payrollService->savePayrollItems($employee, $month, $year, $result);
                $payslip = $this->payrollService->finalizePayslip($employee, $month, $year);
            }

            $payslip->load(['incomeItems', 'deductionItems']);

            $yearToDate = $this->buildYearToDateSummary($employee, $month, $year);
            $monthlyStats = $this->buildMonthlyStats($employee, $month, $year);

            $monthNames = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];

            $pdf = Pdf::loadView('payslip.pdf', [
                'employee' => $employee,
                'payslip' => $payslip,
                'month' => $month,
                'year' => $year,
                'monthName' => $monthNames[$month] ?? '',
                'yearToDate' => $yearToDate,
                'monthlyStats' => $monthlyStats,
            ])->setOption([
                'defaultFont' => 'NotoSansThai',
                'isFontSubsettingEnabled' => true,
                'isRemoteEnabled' => false,
            ]);

            $pdf->setPaper('a4');

            $filename = "payslip_{$employee->employee_code}_{$year}_{$month}.pdf";
            return $pdf->download($filename);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('downloadPdf error', [
                'employee_id' => $employee->id,
                'month' => $month,
                'year' => $year,
                'error' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการสร้าง PDF: ' . $e->getMessage()]);
        }
    }

    public function unfinalize(Employee $employee, int $month, int $year)
    {
        try {
            $payslip = Payslip::where('employee_id', $employee->id)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            if ($payslip && $payslip->status === 'finalized') {
                $payslip->update([
                    'status'       => 'draft',
                    'finalized_at' => null,
                ]);

                AuditLogService::log($payslip, 'unfinalized', 'status', 'finalized', 'draft', 'Payslip unfinalized');
            }

            return redirect()
                ->route('payslip.preview', ['employee' => $employee->id, 'month' => $month, 'year' => $year])
                ->with('success', 'ยกเลิก Finalize payslip สำเร็จ');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('unfinalize error', [
                'employee_id' => $employee->id,
                'month'       => $month,
                'year'        => $year,
                'error'       => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'เกิดข้อผิดพลาดในการยกเลิก Finalize: ' . $e->getMessage()]);
        }
    }

    protected function buildYearToDateSummary(Employee $employee, int $month, int $year): array
    {
        $totalIncome = 0.0;
        $totalDeduction = 0.0;
        $netPay = 0.0;

        for ($runningMonth = 1; $runningMonth <= $month; $runningMonth++) {
            $monthlyPayslip = Payslip::where('employee_id', $employee->id)
                ->where('month', $runningMonth)
                ->where('year', $year)
                ->first();

            if ($monthlyPayslip && $monthlyPayslip->status === 'finalized') {
                $monthlyIncome = (float) $monthlyPayslip->total_income;
                $monthlyDeduction = (float) $monthlyPayslip->total_deduction;
                $monthlyNet = (float) $monthlyPayslip->net_pay;
            } else {
                $result = $this->payrollService->calculateForEmployee($employee, $runningMonth, $year);
                $monthlyIncome = (float) ($result['summary']['total_income'] ?? 0);
                $monthlyDeduction = (float) ($result['summary']['total_deduction'] ?? 0);
                $monthlyNet = (float) ($result['summary']['net_pay'] ?? ($monthlyIncome - $monthlyDeduction));
            }

            $totalIncome += $monthlyIncome;
            $totalDeduction += $monthlyDeduction;
            $netPay += $monthlyNet;
        }

        return [
            'total_income' => $totalIncome,
            'total_deduction' => $totalDeduction,
            'net_pay' => $netPay,
        ];
    }

    protected function buildMonthlyStats(Employee $employee, int $month, int $year, ?array $result = null): array
    {
        $calculated = $result ?? $this->payrollService->calculateForEmployee($employee, $month, $year);
        $summary = $calculated['summary'] ?? [];

        return [
            'total_work_hours' => (float) ($summary['total_work_hours'] ?? 0),
            'total_ot_hours' => (float) ($summary['total_ot_hours'] ?? 0),
            'late_count' => (int) ($summary['late_count'] ?? 0),
            'late_minutes' => (int) ($summary['late_minutes'] ?? 0),
            'lwop_days' => (int) ($summary['lwop_days'] ?? 0),
        ];
    }
}
