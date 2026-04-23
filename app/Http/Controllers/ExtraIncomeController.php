<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ExtraIncomeEntry;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class ExtraIncomeController extends Controller
{
    public function store(Request $request, Employee $employee, int $month, int $year)
    {
        $data = $request->validate([
            'label'    => 'required|string|max:200',
            'category' => 'nullable|string|max:80',
            'amount'   => 'required|numeric|min:0.01',
            'include_in_payslip' => 'nullable|boolean',
            'notes'    => 'nullable|string|max:500',
        ]);

        $entry = ExtraIncomeEntry::create([
            'employee_id' => $employee->id,
            'month'       => $month,
            'year'        => $year,
            'label'       => $data['label'],
            'category'    => $data['category'] ?? null,
            'amount'      => $data['amount'],
            'include_in_payslip' => $request->boolean('include_in_payslip', true),
            'notes'       => $data['notes'] ?? null,
            'created_by'  => $request->user()->id,
        ]);

        AuditLogService::logCreated($entry, 'Extra income added');

        return back()->with('success', 'เพิ่มรายรับพิเศษแล้ว');
    }

    public function destroy(ExtraIncomeEntry $entry)
    {
        AuditLogService::logDeleted($entry, 'Extra income removed');
        $entry->delete();
        return back()->with('success', 'ลบรายรับพิเศษแล้ว');
    }
}
