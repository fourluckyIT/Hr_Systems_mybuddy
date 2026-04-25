<?php

namespace App\Http\Controllers;

use App\Models\CompanyExpense;
use App\Models\CompanyRevenue;
use App\Models\SubscriptionCost;
use App\Models\PayrollBatch;
use App\Models\PayrollItem;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class CompanyFinanceController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) ($request->get('year') ?: now()->year);
        $month = $request->get('month') ? (int) $request->get('month') : null;

        $months = range(1, 12);
        $monthNames = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

        // Monthly P&L data
        $monthlyData = [];
        $yearTotals = ['revenue' => 0, 'expense' => 0, 'subscription' => 0, 'payroll' => 0, 'net' => 0];

        foreach ($months as $m) {
            $revenue = CompanyRevenue::where('year', $year)->where('month', $m)->sum('amount');
            $expense = CompanyExpense::where('year', $year)->where('month', $m)->sum('amount');
            $subscription = SubscriptionCost::where('year', $year)->where('month', $m)->sum('amount');

            // Payroll cost = total income from finalized payslips
            $payrollCost = \App\Models\Payslip::where('year', $year)
                ->where('month', $m)
                ->where('status', 'finalized')
                ->sum('total_income');

            $totalExpenses = $expense + $subscription + $payrollCost;
            $net = $revenue - $totalExpenses;

            $monthlyData[$m] = [
                'month_name' => $monthNames[$m],
                'revenue' => round($revenue, 2),
                'expense' => round($expense, 2),
                'subscription' => round($subscription, 2),
                'payroll' => round($payrollCost, 2),
                'total_expense' => round($totalExpenses, 2),
                'net' => round($net, 2),
            ];

            $yearTotals['revenue'] += $revenue;
            $yearTotals['expense'] += $expense;
            $yearTotals['subscription'] += $subscription;
            $yearTotals['payroll'] += $payrollCost;
            $yearTotals['net'] += $net;
        }

        // Detail lists for selected month
        $expenses = collect();
        $revenues = collect();
        $subscriptions = collect();

        if ($month) {
            $expenses = CompanyExpense::where('year', $year)->where('month', $month)->orderByDesc('id')->get();
            $revenues = CompanyRevenue::where('year', $year)->where('month', $month)->orderByDesc('id')->get();
            $subscriptions = SubscriptionCost::where('year', $year)->where('month', $month)->orderByDesc('id')->get();
        }

        return view('company.finance', compact(
            'year', 'month', 'months', 'monthNames', 'monthlyData', 'yearTotals',
            'expenses', 'revenues', 'subscriptions'
        ));
    }

    public function storeRevenue(Request $request)
    {
        $validated = $request->validate([
            'source' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $revenue = CompanyRevenue::create($validated);
        AuditLogService::logCreated($revenue, 'Company revenue created');

        return back()->with('success', 'บันทึกรายรับสำเร็จ');
    }

    public function updateRevenue(Request $request, CompanyRevenue $revenue)
    {
        $validated = $request->validate([
            'source' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $old = $revenue->getAttributes();
        $revenue->update($validated);
        AuditLogService::log($revenue, 'updated', 'revenue', $old, $revenue->getAttributes(), 'Revenue updated');

        return back()->with('success', 'อัปเดตรายรับสำเร็จ');
    }

    public function deleteRevenue(CompanyRevenue $revenue)
    {
        AuditLogService::logDeleted($revenue, 'Revenue deleted: ' . $revenue->source);
        $revenue->delete();
        return back()->with('success', 'ลบรายรับสำเร็จ');
    }

    public function storeExpense(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $expense = CompanyExpense::create($validated);
        AuditLogService::logCreated($expense, 'Company expense created');

        return back()->with('success', 'บันทึกค่าใช้จ่ายสำเร็จ');
    }

    public function updateExpense(Request $request, CompanyExpense $expense)
    {
        $validated = $request->validate([
            'category' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $old = $expense->getAttributes();
        $expense->update($validated);
        AuditLogService::log($expense, 'updated', 'expense', $old, $expense->getAttributes(), 'Expense updated');

        return back()->with('success', 'อัปเดตค่าใช้จ่ายสำเร็จ');
    }

    public function deleteExpense(CompanyExpense $expense)
    {
        AuditLogService::logDeleted($expense, 'Expense deleted: ' . $expense->category);
        $expense->delete();
        return back()->with('success', 'ลบค่าใช้จ่ายสำเร็จ');
    }

    public function storeSubscription(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'is_recurring' => 'nullable|boolean',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $validated['is_recurring'] = $request->boolean('is_recurring');
        $sub = SubscriptionCost::create($validated);
        AuditLogService::logCreated($sub, 'Subscription created');

        return back()->with('success', 'บันทึก Subscription สำเร็จ');
    }

    public function deleteSubscription(SubscriptionCost $subscription)
    {
        AuditLogService::logDeleted($subscription, 'Subscription deleted: ' . $subscription->name);
        $subscription->delete();
        return back()->with('success', 'ลบ Subscription สำเร็จ');
    }

}
