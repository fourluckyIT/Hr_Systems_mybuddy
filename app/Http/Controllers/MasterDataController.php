<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PayrollItemType;
use App\Models\Department;
use App\Models\Position;
use App\Services\AuditLogService;

class MasterDataController extends Controller
{
    public function __construct(private AuditLogService $audit)
    {
    }

    public function index()
    {
        $payrollItemTypes = PayrollItemType::orderBy('category')->orderBy('sort_order')->get();
        $departments = Department::withCount('employees')->orderBy('name')->get();
        $positions = Position::with('department')->withCount('employees')->orderBy('name')->get();
        $jobStages = \App\Models\JobStage::orderBy('type')->orderBy('sort_order')->get();

        return view('settings.master-data', compact('payrollItemTypes', 'departments', 'positions', 'jobStages'));
    }

    // === Payroll Item Types ===

    public function storePayrollItemType(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:payroll_item_types,code',
            'label_th' => 'required|string|max:100',
            'label_en' => 'nullable|string|max:100',
            'category' => 'required|in:income,deduction',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['is_system'] = false;
        $validated['sort_order'] = $validated['sort_order'] ?? 99;

        $item = PayrollItemType::create($validated);

        $this->audit->logCreated($item, 'เพิ่มประเภทรายการเงินเดือนใหม่');

        return back()->with('success', "เพิ่มรายการ \"{$item->label_th}\" สำเร็จ");
    }

    public function updatePayrollItemType(Request $request, PayrollItemType $payrollItemType)
    {
        $validated = $request->validate([
            'label_th' => 'required|string|max:100',
            'label_en' => 'nullable|string|max:100',
            'category' => 'required|in:income,deduction',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $old = $payrollItemType->toArray();
        $payrollItemType->update($validated);
        $this->audit->logUpdated($payrollItemType, collect($old)->only(array_keys($validated))->toArray(), 'แก้ไขประเภทรายการเงินเดือน');

        return back()->with('success', "อัปเดต \"{$payrollItemType->label_th}\" สำเร็จ");
    }

    public function deletePayrollItemType(PayrollItemType $payrollItemType)
    {
        if ($payrollItemType->is_system) {
            return back()->with('error', 'ไม่สามารถลบรายการที่เป็นของระบบได้');
        }

        $name = $payrollItemType->label_th;
        $this->audit->logDeleted($payrollItemType, 'ลบประเภทรายการเงินเดือน');
        $payrollItemType->delete();

        return back()->with('success', "ลบ \"{$name}\" สำเร็จ");
    }

    // === Departments ===

    public function storeDepartment(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20|unique:departments,code',
        ]);

        $validated['is_active'] = true;
        $dept = Department::create($validated);
        $this->audit->logCreated($dept, 'เพิ่มแผนกใหม่');

        return back()->with('success', "เพิ่มแผนก \"{$dept->name}\" สำเร็จ");
    }

    public function updateDepartment(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $old = $department->toArray();
        $department->update($validated);
        $this->audit->logUpdated($department, collect($old)->only(array_keys($validated))->toArray(), 'แก้ไขแผนก');

        return back()->with('success', "อัปเดตแผนก \"{$department->name}\" สำเร็จ");
    }

    public function deleteDepartment(Department $department)
    {
        if ($department->employees()->count() > 0) {
            return back()->with('error', "ไม่สามารถลบแผนก \"{$department->name}\" ได้ เพราะยังมีพนักงานอยู่ ({$department->employees()->count()} คน)");
        }

        $name = $department->name;
        $this->audit->logDeleted($department, 'ลบแผนก');
        $department->delete();

        return back()->with('success', "ลบแผนก \"{$name}\" สำเร็จ");
    }

    // === Positions ===

    public function storePosition(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:20',
            'workspace_panel' => 'nullable|string|in:recording_queue,edit_jobs,youtuber,none',
            'department_id' => 'required|exists:departments,id',
        ]);

        $validated['is_active'] = true;
        $validated['workspace_panel'] = $validated['workspace_panel'] ?? 'recording_queue';
        $pos = Position::create($validated);
        $this->audit->logCreated($pos, 'เพิ่มตำแหน่งใหม่');

        return back()->with('success', "เพิ่มตำแหน่ง \"{$pos->name}\" สำเร็จ");
    }

    public function updatePosition(Request $request, Position $position)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:20',
            'workspace_panel' => 'nullable|string|in:recording_queue,edit_jobs,youtuber,none',
            'department_id' => 'required|exists:departments,id',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['workspace_panel'] = $validated['workspace_panel'] ?? 'recording_queue';
        $old = $position->toArray();
        $position->update($validated);
        $this->audit->logUpdated($position, collect($old)->only(array_keys($validated))->toArray(), 'แก้ไขตำแหน่ง');

        return back()->with('success', "อัปเดตตำแหน่ง \"{$position->name}\" สำเร็จ");
    }

    public function deletePosition(Position $position)
    {
        if ($position->employees()->count() > 0) {
            return back()->with('error', "ไม่สามารถลบตำแหน่ง \"{$position->name}\" ได้ เพราะยังมีพนักงานอยู่ ({$position->employees()->count()} คน)");
        }

        $name = $position->name;
        $this->audit->logDeleted($position, 'ลบตำแหน่ง');
        $position->delete();

        return back()->with('success', "ลบตำแหน่ง \"{$name}\" สำเร็จ");
    }
    // === Job Stages ===

    public function storeJobStage(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:recording,edit',
            'code' => 'required|string|max:50|unique:job_stages,code',
            'name' => 'required|string|max:100',
            'color' => 'required|string|max:20',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['is_core'] = false;
        $validated['sort_order'] = $validated['sort_order'] ?? 99;

        $stage = \App\Models\JobStage::create($validated);
        $this->audit->logCreated($stage, 'เพิ่มสถานะงาน (Job Stage) ใหม่');

        return back()->with('success', "เพิ่มสถานะ \"{$stage->name}\" สำเร็จ");
    }

    public function updateJobStage(Request $request, \App\Models\JobStage $jobStage)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'required|string|max:20',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        if (!$jobStage->is_core && $request->has('code')) {
             $request->validate(['code' => 'required|string|max:50|unique:job_stages,code,'.$jobStage->id]);
             $validated['code'] = $request->input('code');
        }

        $old = $jobStage->toArray();
        $jobStage->update($validated);
        $this->audit->logUpdated($jobStage, collect($old)->only(array_keys($validated))->toArray(), 'แก้ไขสถานะงาน');

        return back()->with('success', "อัปเดตสถานะ \"{$jobStage->name}\" สำเร็จ");
    }

    public function deleteJobStage(\App\Models\JobStage $jobStage)
    {
        if ($jobStage->is_core) {
            return back()->with('error', 'ไม่สามารถลบสถานะที่เป็นของระบบ (Core Stage) ได้');
        }

        $name = $jobStage->name;
        $this->audit->logDeleted($jobStage, 'ลบสถานะงาน');
        $jobStage->delete();

        return back()->with('success', "ลบสถานะ \"{$name}\" สำเร็จ");
    }
}
