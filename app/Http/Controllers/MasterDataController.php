<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PayrollItemType;
use App\Models\Department;
use App\Models\Position;
use App\Models\Employee;
use App\Models\ModuleToggle;
use App\Models\LayerRateRule;
use App\Models\LayerRateTemplate;
use App\Models\Game;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;

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
        $employees = Employee::with(['department', 'position', 'moduleToggles'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
        $freelanceLayerEmployees = Employee::where('is_active', true)
            ->where('payroll_mode', 'freelance_layer')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
        $layerRateRules = LayerRateRule::with('employee')
            ->orderBy('employee_id')
            ->orderBy('layer_from')
            ->orderByDesc('effective_date')
            ->get();

        $layerRateTemplates = LayerRateTemplate::orderBy('layer_from')->get();

        $games = Game::orderBy('game_name')->get();

        return view('settings.master-data', compact(
            'payrollItemTypes',
            'departments',
            'positions',
            'jobStages',
            'employees',
            'freelanceLayerEmployees',
            'layerRateRules',
            'layerRateTemplates',
            'games'
        ));
    }

    protected function requireAdmin(): void
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403, 'เฉพาะ admin เท่านั้น');
    }

    public function updateWorkspaceAccess(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'is_enabled' => 'required|boolean',
        ]);

        $toggle = ModuleToggle::firstOrNew([
            'employee_id' => $employee->id,
            'module_name' => 'workspace_editing',
        ]);

        $oldValue = $toggle->exists ? (bool) $toggle->is_enabled : true;
        $toggle->is_enabled = (bool) $validated['is_enabled'];
        $toggle->save();

        $this->audit->log(
            $toggle,
            'workspace_access_updated',
            'is_enabled',
            $oldValue,
            $toggle->is_enabled,
            'Workspace edit access updated from Master Data'
        );

        return back()->with('success', 'อัปเดตสิทธิ์แก้ไข Workspace สำเร็จ');
    }

    // === FL Layer Rate — Global Templates (admin only) ===

    public function storeLayerRateTemplate(Request $request)
    {
        $this->requireAdmin();
        $validated = $request->validate([
            'label' => 'nullable|string|max:100',
            'layer_from' => 'required|integer|min:1',
            'layer_to' => 'required|integer|gte:layer_from',
            'rate_per_minute' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active', true);
        $tpl = LayerRateTemplate::create($validated);
        $this->audit->logCreated($tpl, 'เพิ่มเทมเพลตราคาเลเยอร์ (global)');
        return back()->with('success', 'เพิ่มเทมเพลตราคาเลเยอร์สำเร็จ');
    }

    public function updateLayerRateTemplate(Request $request, LayerRateTemplate $layerRateTemplate)
    {
        $this->requireAdmin();
        $validated = $request->validate([
            'label' => 'nullable|string|max:100',
            'layer_from' => 'required|integer|min:1',
            'layer_to' => 'required|integer|gte:layer_from',
            'rate_per_minute' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active');
        $old = $layerRateTemplate->toArray();
        $layerRateTemplate->update($validated);
        $this->audit->logUpdated($layerRateTemplate, collect($old)->only(array_keys($validated))->toArray(), 'แก้ไขเทมเพลตราคาเลเยอร์ (global)');
        return back()->with('success', 'อัปเดตเทมเพลตราคาเลเยอร์สำเร็จ');
    }

    public function deleteLayerRateTemplate(LayerRateTemplate $layerRateTemplate)
    {
        $this->requireAdmin();
        $this->audit->logDeleted($layerRateTemplate, 'ลบเทมเพลตราคาเลเยอร์ (global)');
        $layerRateTemplate->delete();
        return back()->with('success', 'ลบเทมเพลตราคาเลเยอร์สำเร็จ');
    }

    // === FL Layer Rate Templates (Per Employee) ===

    public function storeLayerRateRule(Request $request)
    {
        $this->requireAdmin();
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'layer_from' => 'required|integer|min:1',
            'layer_to' => 'required|integer|gte:layer_from',
            'rate_per_minute' => 'required|numeric|min:0',
            'effective_date' => 'required|date',
            'is_active' => 'nullable|boolean',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        if ($employee->payroll_mode !== 'freelance_layer') {
            return back()->with('error', 'ตั้งค่า Layer Rate ได้เฉพาะพนักงาน payroll mode = freelance_layer เท่านั้น');
        }

        $validated['is_active'] = $request->boolean('is_active', true);
        $rule = LayerRateRule::create($validated);

        $this->audit->logCreated($rule, 'เพิ่มเทมเพลตราคาเลเยอร์รายคน');

        return back()->with('success', 'เพิ่มเทมเพลตราคาเลเยอร์สำเร็จ');
    }

    public function updateLayerRateRule(Request $request, LayerRateRule $layerRateRule)
    {
        $this->requireAdmin();
        $validated = $request->validate([
            'layer_from' => 'required|integer|min:1',
            'layer_to' => 'required|integer|gte:layer_from',
            'rate_per_minute' => 'required|numeric|min:0',
            'effective_date' => 'required|date',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $old = $layerRateRule->toArray();
        $layerRateRule->update($validated);

        $this->audit->logUpdated(
            $layerRateRule,
            collect($old)->only(array_keys($validated))->toArray(),
            'แก้ไขเทมเพลตราคาเลเยอร์รายคน'
        );

        return back()->with('success', 'อัปเดตเทมเพลตราคาเลเยอร์สำเร็จ');
    }

    public function deleteLayerRateRule(LayerRateRule $layerRateRule)
    {
        $this->requireAdmin();
        $this->audit->logDeleted($layerRateRule, 'ลบเทมเพลตราคาเลเยอร์รายคน');
        $layerRateRule->delete();

        return back()->with('success', 'ลบเทมเพลตราคาเลเยอร์สำเร็จ');
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
            'workspace_panel' => 'nullable|string|in:edit_jobs,youtuber,none',
            'department_id' => 'required|exists:departments,id',
        ]);

        $validated['is_active'] = true;
        $validated['workspace_panel'] = $validated['workspace_panel'] ?? 'edit_jobs';
        $pos = Position::create($validated);
        $this->audit->logCreated($pos, 'เพิ่มตำแหน่งใหม่');

        return back()->with('success', "เพิ่มตำแหน่ง \"{$pos->name}\" สำเร็จ");
    }

    public function updatePosition(Request $request, Position $position)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:20',
            'workspace_panel' => 'nullable|string|in:edit_jobs,youtuber,none',
            'department_id' => 'required|exists:departments,id',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['workspace_panel'] = $validated['workspace_panel'] ?? 'edit_jobs';
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

    // === Games ===

    public function storeGame(Request $request)
    {
        $validated = $request->validate([
            'game_name' => 'required|string|max:255',
            'game_slug' => 'nullable|string|max:255',
        ]);

        $validated['game_slug'] = $validated['game_slug']
            ?: \Illuminate\Support\Str::slug($validated['game_name']);
        $validated['is_active'] = true;

        $game = Game::create($validated);
        $this->audit->logCreated($game, 'เพิ่มเกม: ' . $game->game_name);

        return back()->with('success', 'เพิ่มเกม "' . $game->game_name . '" สำเร็จ');
    }

    public function updateGame(Request $request, Game $game)
    {
        $validated = $request->validate([
            'game_name' => 'required|string|max:255',
            'game_slug' => 'nullable|string|max:255',
            'is_active' => 'nullable',
        ]);

        $validated['is_active'] = $request->has('is_active');
        if (!empty($validated['game_slug'])) {
            $validated['game_slug'] = \Illuminate\Support\Str::slug($validated['game_slug']);
        }

        $old = $game->toArray();
        $game->update($validated);
        $this->audit->logUpdated($game, collect($old)->only(array_keys($validated))->toArray(), 'อัปเดตเกม: ' . $game->game_name);

        return back()->with('success', 'อัปเดตเกม "' . $game->game_name . '" สำเร็จ');
    }

    public function deleteGame(Game $game)
    {
        $jobCount = $game->editingJobs()->where('is_deleted', false)->count();
        if ($jobCount > 0) {
            return back()->with('error', 'ไม่สามารถลบเกมที่มีงานอยู่ (' . $jobCount . ' งาน)');
        }

        $name = $game->game_name;
        $this->audit->logDeleted($game, 'ลบเกม: ' . $name);
        $game->delete();

        return back()->with('success', 'ลบเกม "' . $name . '" สำเร็จ');
    }
}
