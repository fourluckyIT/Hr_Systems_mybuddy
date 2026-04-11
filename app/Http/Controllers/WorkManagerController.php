<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\WorkAssignment;
use App\Models\WorkLogType;
use App\Services\AuditLogService;
use App\Support\DurationInput;
use Illuminate\Http\Request;

class WorkManagerController extends Controller
{
    public function index(Request $request)
    {
        $module = $request->string('module')->toString();
        $payrollMode = $request->string('payroll_mode')->toString();

        $query = WorkLogType::query();

        if ($module !== '') {
            $query->where('module_key', $module);
        }

        if ($payrollMode !== '') {
            $query->where('payroll_mode', $payrollMode);
        }

        $workTypes = $query->orderBy('sort_order')->orderBy('name')->get();
        $employees = Employee::where('is_active', true)->orderBy('first_name')->get();
        $assignments = WorkAssignment::with(['employee', 'workType'])
            ->latest()
            ->limit(100)
            ->get();

        $moduleOptions = [
            'workspace' => 'Workspace',
            'performance' => 'Performance',
            'payslip' => 'Payslip',
            'reporting' => 'Reporting',
            'global' => 'Global',
        ];

        $payrollModeOptions = [
            'monthly_staff' => 'พนักงานรายเดือน',
            'freelance_layer' => 'ฟรีแลนซ์เรทเลเยอร์',
            'freelance_fixed' => 'ฟรีแลนซ์ฟิกเรท',
            'youtuber_salary' => 'YouTuber เงินเดือน',
            'youtuber_settlement' => 'YouTuber Settlement',
            'custom_hybrid' => 'รูปแบบผสม',
        ];

        return view('settings.works.index', compact('workTypes', 'moduleOptions', 'payrollModeOptions', 'module', 'payrollMode', 'employees', 'assignments'));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $wlt = WorkLogType::create($this->mapPayload($validated));

        AuditLogService::logCreated($wlt, 'Work template created');

        return back()->with('success', 'เพิ่ม Work Template สำเร็จ');
    }

    public function update(Request $request, WorkLogType $workLogType)
    {
        $validated = $this->validatePayload($request, $workLogType->id);

        $oldData = $workLogType->getAttributes();
        $workLogType->update($this->mapPayload($validated));

        AuditLogService::log($workLogType, 'updated', 'work_template', $oldData, $workLogType->getAttributes(), 'Work template updated');

        return back()->with('success', 'อัปเดต Work Template สำเร็จ');
    }

    public function toggle(WorkLogType $workLogType)
    {
        $old = $workLogType->is_active;
        $workLogType->update(['is_active' => !$workLogType->is_active]);

        AuditLogService::log($workLogType, 'toggled', 'is_active', $old, $workLogType->is_active, 'Work template toggled');

        return back()->with('success', 'ปรับสถานะ Work Template สำเร็จ');
    }

    public function destroy(WorkLogType $workLogType)
    {
        AuditLogService::logDeleted($workLogType, 'Work template deleted: ' . $workLogType->name);
        $workLogType->delete();

        return back()->with('success', 'ลบ Work Template สำเร็จ');
    }

    public function storeAssignment(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'work_log_type_id' => 'required|exists:work_log_types,id',
            'assigned_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:assigned_date',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'notes' => 'nullable|string|max:2000',
        ]);

        $assignment = WorkAssignment::create([
            'employee_id' => $validated['employee_id'],
            'work_log_type_id' => $validated['work_log_type_id'],
            'assigned_date' => $validated['assigned_date'],
            'due_date' => $validated['due_date'] ?? null,
            'priority' => $validated['priority'] ?? 'normal',
            'notes' => $validated['notes'] ?? null,
            'status' => 'action_select',
            'assigned_by' => auth()->id(),
        ]);

        AuditLogService::logCreated($assignment, 'Work assignment created');

        return back()->with('success', 'Assign งานให้พนักงานสำเร็จ');
    }

    public function updateAssignment(Request $request, WorkAssignment $workAssignment)
    {
        $validated = $request->validate([
            'status' => 'required|in:action_select,in_process,finished,rejected',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'notes' => 'nullable|string|max:2000',
        ]);

        $oldData = $workAssignment->getAttributes();

        $workAssignment->update([
            'status' => $validated['status'],
            'due_date' => $validated['due_date'] ?? $workAssignment->due_date,
            'priority' => $validated['priority'] ?? $workAssignment->priority,
            'notes' => $validated['notes'] ?? $workAssignment->notes,
            'completed_at' => $validated['status'] === 'finished' ? now() : null,
        ]);

        AuditLogService::log($workAssignment, 'updated', 'assignment', $oldData, $workAssignment->getAttributes(), 'Work assignment updated');

        return back()->with('success', 'อัปเดต Assignment สำเร็จ');
    }

    public function deleteAssignment(WorkAssignment $workAssignment)
    {
        AuditLogService::logDeleted($workAssignment, 'Work assignment deleted');
        $workAssignment->delete();

        return back()->with('success', 'ลบ Assignment สำเร็จ');
    }

    protected function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:work_log_types,code,' . ($ignoreId ?? 'NULL') . ',id',
            'module_key' => 'required|string|max:100',
            'payroll_mode' => 'nullable|in:monthly_staff,freelance_layer,freelance_fixed,youtuber_salary,youtuber_settlement,custom_hybrid',
            'footage_size' => 'nullable|string|max:100',
            'target_length_hms' => ['nullable', 'regex:/^\d{1,2}:\d{2}(?::\d{2})?$/'],
            'target_length_minutes' => 'nullable|numeric|min:0',
            'default_rate_per_minute' => 'nullable|numeric|min:0',
            'sort_order' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:2000',
            'config_json' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
    }

    protected function mapPayload(array $validated): array
    {
        $config = null;

        if (!empty($validated['config_json'])) {
            $decoded = json_decode($validated['config_json'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $config = $decoded;
            }
        }

        $targetLengthMinutes = $validated['target_length_minutes'] ?? null;
        if (!empty($validated['target_length_hms'])) {
            $targetLengthMinutes = DurationInput::minutesFromHms($validated['target_length_hms']);
        }

        return [
            'name' => $validated['name'],
            'code' => $validated['code'],
            'module_key' => $validated['module_key'],
            'payroll_mode' => $validated['payroll_mode'] ?? null,
            'footage_size' => $validated['footage_size'] ?? null,
            'target_length_minutes' => $targetLengthMinutes,
            'default_rate_per_minute' => $validated['default_rate_per_minute'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'description' => $validated['description'] ?? null,
            'config' => $config,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];
    }
}
