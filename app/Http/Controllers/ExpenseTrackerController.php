<?php

namespace App\Http\Controllers;

use App\Models\CompanyExpense;
use App\Models\CompanyRevenue;
use App\Models\ExpenseCategory;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class ExpenseTrackerController extends Controller
{
    public function index(Request $request)
    {
        $year      = (int) ($request->get('year')  ?: now()->year);
        $month     = (int) ($request->get('month') ?: now()->month);
        $typeFilter = $request->get('type');      // income | expense | null=all
        $catFilter  = $request->get('category_id');
        $viewMode   = $request->get('view', 'table'); // table | card

        $categories = ExpenseCategory::active()->orderBy('type')->orderBy('sort_order')->get();

        $expenseQuery = CompanyExpense::with('categoryRef')
            ->where('year', $year)->where('month', $month);
        $revenueQuery = CompanyRevenue::with('categoryRef')
            ->where('year', $year)->where('month', $month);

        if ($catFilter) {
            $expenseQuery->where('expense_category_id', $catFilter);
            $revenueQuery->where('expense_category_id', $catFilter);
        }

        $expenses = ($typeFilter === 'income')  ? collect() : $expenseQuery->orderByDesc('entry_date')->orderByDesc('id')->get();
        $revenues = ($typeFilter === 'expense') ? collect() : $revenueQuery->orderByDesc('entry_date')->orderByDesc('id')->get();

        // Unified timeline for table view
        $entries = collect();
        foreach ($revenues as $r) {
            $entries->push([
                'id'       => $r->id,
                'type'     => 'income',
                'date'     => $r->entry_date ?? now()->setDate($r->year, $r->month, 1)->toDateString(),
                'category' => $r->categoryRef?->name ?? $r->source,
                'category_color' => $r->categoryRef?->color ?? 'gray',
                'description' => $r->description ?? $r->source,
                'amount'   => (float) $r->amount,
                'status'   => $r->status ?? 'received',
                'is_recurring' => false,
                'model'    => 'revenue',
            ]);
        }
        foreach ($expenses as $e) {
            $entries->push([
                'id'       => $e->id,
                'type'     => 'expense',
                'date'     => $e->entry_date ?? now()->setDate($e->year, $e->month, 1)->toDateString(),
                'category' => $e->categoryRef?->name ?? $e->category,
                'category_color' => $e->categoryRef?->color ?? 'gray',
                'description' => $e->description ?? $e->category,
                'amount'   => (float) $e->amount,
                'status'   => $e->status ?? 'paid',
                'is_recurring' => (bool) $e->is_recurring,
                'model'    => 'expense',
            ]);
        }
        $entries = $entries->sortByDesc('date')->values();

        // Totals
        $totalIncome  = $revenues->sum('amount');
        $totalExpense = $expenses->sum('amount');

        // Card view: group by category
        $groupedIncome = $revenues->groupBy(fn ($r) => $r->categoryRef?->name ?? $r->source);
        $groupedExpense = $expenses->groupBy(fn ($e) => $e->categoryRef?->name ?? $e->category);

        return view('expense-tracker.index', compact(
            'year', 'month', 'categories', 'entries',
            'totalIncome', 'totalExpense',
            'groupedIncome', 'groupedExpense',
            'typeFilter', 'catFilter', 'viewMode'
        ));
    }

    public function storeEntry(Request $request)
    {
        $data = $request->validate([
            'type'                => 'required|in:income,expense',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'description'         => 'required|string|max:500',
            'amount'              => 'required|numeric|min:0.01',
            'entry_date'          => 'required|date',
            'is_recurring'        => 'nullable|boolean',
            'status'              => 'nullable|string|max:30',
        ]);

        $date = \Carbon\Carbon::parse($data['entry_date']);

        if ($data['type'] === 'income') {
            $category = ExpenseCategory::find($data['expense_category_id']);
            $rev = CompanyRevenue::create([
                'source'      => $category?->name ?? 'Other',
                'description' => $data['description'],
                'amount'      => $data['amount'],
                'month'       => $date->month,
                'year'        => $date->year,
                'expense_category_id' => $data['expense_category_id'],
                'entry_date'  => $data['entry_date'],
                'status'      => $data['status'] ?? 'received',
            ]);
            AuditLogService::logCreated($rev, 'Revenue (tracker)');
        } else {
            $category = ExpenseCategory::find($data['expense_category_id']);
            $exp = CompanyExpense::create([
                'category'    => $category?->name ?? 'Other',
                'description' => $data['description'],
                'amount'      => $data['amount'],
                'month'       => $date->month,
                'year'        => $date->year,
                'expense_category_id' => $data['expense_category_id'],
                'entry_date'  => $data['entry_date'],
                'is_recurring' => $request->boolean('is_recurring'),
                'status'      => $data['status'] ?? 'paid',
            ]);
            AuditLogService::logCreated($exp, 'Expense (tracker)');
        }

        return back()->with('success', 'บันทึกรายการแล้ว');
    }

    public function destroyEntry(Request $request, string $model, int $id)
    {
        if ($model === 'income') {
            $row = CompanyRevenue::findOrFail($id);
        } else {
            $row = CompanyExpense::findOrFail($id);
        }
        AuditLogService::logDeleted($row, 'Tracker entry deleted');
        $row->delete();
        return back()->with('success', 'ลบรายการแล้ว');
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:120',
            'type'  => 'required|in:income,expense',
            'color' => 'nullable|string|max:20',
        ]);
        ExpenseCategory::create($data + ['is_active' => true]);
        return back()->with('success', 'เพิ่มหมวดแล้ว');
    }

    public function destroyCategory(ExpenseCategory $category)
    {
        $category->update(['is_active' => false]);
        return back()->with('success', 'ซ่อนหมวดแล้ว');
    }
}
