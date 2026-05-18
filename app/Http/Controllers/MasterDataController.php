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
use App\Models\LeavePolicy;
use App\Models\HolidayType;
use App\Models\CompanyProfile;
use App\Models\CompanyHoliday;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
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

        $leavePolicies = LeavePolicy::withCount('employees')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $holidayTypes = HolidayType::withCount('companyHolidays')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $colorPresets = HolidayType::COLOR_PRESETS;

        $company = CompanyProfile::active();
        $holidays = CompanyHoliday::with('holidayType')->orderBy('holiday_date')->get();

        return view('settings.master-data', compact(
            'payrollItemTypes',
            'departments',
            'positions',
            'jobStages',
            'employees',
            'freelanceLayerEmployees',
            'layerRateRules',
            'layerRateTemplates',
            'games',
            'leavePolicies',
            'holidayTypes',
            'colorPresets',
            'company',
            'holidays'
        ));
    }

    // ─── Leave Policies CRUD ─────────────────────────────────────────────
    public function storeLeavePolicy(Request $request)
    {
        $this->requireAdmin();
        $data = $this->validateLeavePolicy($request);

        DB::transaction(function () use ($data) {
            if (!empty($data['is_default'])) {
                LeavePolicy::query()->update(['is_default' => false]);
            }
            $p = LeavePolicy::create($data);
            $this->audit->logCreated($p, 'Leave policy created');
        });

        return back()->with('success', 'เพิ่มนโยบายวันลาสำเร็จ');
    }

    public function updateLeavePolicy(Request $request, LeavePolicy $leavePolicy)
    {
        $this->requireAdmin();
        $data = $this->validateLeavePolicy($request);

        DB::transaction(function () use ($data, $leavePolicy) {
            if (!empty($data['is_default'])) {
                LeavePolicy::where('id', '!=', $leavePolicy->id)->update(['is_default' => false]);
            }
            $old = $leavePolicy->getAttributes();
            $leavePolicy->update($data);
            $this->audit->log($leavePolicy, 'updated', 'leave_policy', $old, $leavePolicy->getAttributes(), 'Leave policy updated');
        });

        return back()->with('success', 'อัปเดตนโยบายวันลาสำเร็จ');
    }

    public function destroyLeavePolicy(LeavePolicy $leavePolicy)
    {
        $this->requireAdmin();

        if ($leavePolicy->is_default) {
            return back()->withErrors(['policy' => 'ลบนโยบาย default ไม่ได้ — กรุณาตั้งนโยบายอื่นเป็น default ก่อน']);
        }

        // employees ที่ผูกอยู่ — เปลี่ยนเป็น default ก่อนลบ
        $defaultId = LeavePolicy::where('is_default', true)->value('id');
        Employee::where('leave_policy_id', $leavePolicy->id)->update(['leave_policy_id' => $defaultId]);

        $this->audit->logDeleted($leavePolicy, 'Leave policy deleted');
        $leavePolicy->delete();

        return back()->with('success', 'ลบนโยบายวันลาสำเร็จ');
    }

    protected function validateLeavePolicy(Request $request): array
    {
        $rules = [
            'name' => 'required|string|max:100',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'vacation_days' => 'required|integer|min:0|max:365',
            'sick_days' => 'required|integer|min:0|max:365',
            'personal_days' => 'required|integer|min:0|max:365',
            'allow_carryover' => 'sometimes|boolean',
            'max_carryover_days' => 'nullable|integer|min:0|max:365',
            'carryover_expires_months' => 'nullable|integer|min:1|max:24',
            'allow_encashment' => 'sometimes|boolean',
            'max_encash_days_per_year' => 'nullable|integer|min:0|max:365',
            'encash_rate_formula' => 'required|in:salary_div_30,salary_div_22,manual',
            'available_during_probation' => 'sometimes|boolean',
            // Per-type probation gates (Thai labor law: ม.30 / ม.32 / ม.34)
            'sick_during_probation'     => 'sometimes|boolean',
            'personal_during_probation' => 'sometimes|boolean',
            'vacation_during_probation' => 'sometimes|boolean',
            'vacation_eligibility_months' => 'nullable|integer|min:0|max:60',
            'apply_to_payroll_modes' => 'nullable|array',
            'apply_to_payroll_modes.*' => 'string|in:monthly_staff,office_staff,freelance_layer,youtuber_salary,youtuber_settlement,custom_hybrid',
            'note' => 'nullable|string|max:500',
        ];

        $data = $request->validate($rules);
        // Default booleans — unticked checkboxes from <input type="hidden" name=… value="0"> already deliver "0",
        // but if the field wasn't present at all (admin-edited from API/curl etc.) we still need a defined value.
        foreach ([
            'is_default','is_active','allow_carryover','allow_encashment',
            'available_during_probation',
            'sick_during_probation','personal_during_probation','vacation_during_probation',
        ] as $b) {
            $data[$b] = (bool) ($data[$b] ?? false);
        }
        // Vacation eligibility default — per ม.30
        $data['vacation_eligibility_months'] = (int) ($data['vacation_eligibility_months'] ?? 12);
        return $data;
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
        ]);

        $validated['is_system'] = false;
        $validated['sort_order'] = (int) PayrollItemType::where('category', $validated['category'])->max('sort_order') + 10;

        $item = PayrollItemType::create($validated);

        $this->audit->logCreated($item, 'เพิ่มประเภทรายการเงินเดือนใหม่');

        return back()->with('success', "เพิ่มรายการ \"{$item->label_th}\" สำเร็จ");
    }

    public function movePayrollItemType(Request $request, PayrollItemType $payrollItemType)
    {
        $dir = $request->validate(['direction' => 'required|in:up,down'])['direction'];
        $neighbor = PayrollItemType::where('category', $payrollItemType->category)
            ->where('sort_order', $dir === 'up' ? '<' : '>', $payrollItemType->sort_order)
            ->orderBy('sort_order', $dir === 'up' ? 'desc' : 'asc')
            ->orderBy('id', $dir === 'up' ? 'desc' : 'asc')
            ->first();
        if ($neighbor) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($payrollItemType, $neighbor) {
                [$a, $b] = [$payrollItemType->sort_order, $neighbor->sort_order];
                if ($a === $b) {
                    $neighbor->sort_order = $a + ($payrollItemType->id > $neighbor->id ? -1 : 1);
                } else {
                    $payrollItemType->sort_order = $b;
                    $neighbor->sort_order = $a;
                }
                $payrollItemType->save();
                $neighbor->save();
            });
        }
        return back();
    }

    public function updatePayrollItemType(Request $request, PayrollItemType $payrollItemType)
    {
        $validated = $request->validate([
            'label_th' => 'required|string|max:100',
            'label_en' => 'nullable|string|max:100',
            'category' => 'required|in:income,deduction',
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
        ]);

        $validated['is_core'] = false;
        $validated['sort_order'] = (int) \App\Models\JobStage::where('type', $validated['type'])->max('sort_order') + 10;

        $stage = \App\Models\JobStage::create($validated);
        $this->audit->logCreated($stage, 'เพิ่มสถานะงาน (Job Stage) ใหม่');

        return back()->with('success', "เพิ่มสถานะ \"{$stage->name}\" สำเร็จ");
    }

    public function moveJobStage(Request $request, \App\Models\JobStage $jobStage)
    {
        $dir = $request->validate(['direction' => 'required|in:up,down'])['direction'];
        $neighbor = \App\Models\JobStage::where('type', $jobStage->type)
            ->where('sort_order', $dir === 'up' ? '<' : '>', $jobStage->sort_order)
            ->orderBy('sort_order', $dir === 'up' ? 'desc' : 'asc')
            ->orderBy('id', $dir === 'up' ? 'desc' : 'asc')
            ->first();
        if ($neighbor) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($jobStage, $neighbor) {
                [$a, $b] = [$jobStage->sort_order, $neighbor->sort_order];
                if ($a === $b) {
                    $neighbor->sort_order = $a + ($jobStage->id > $neighbor->id ? -1 : 1);
                } else {
                    $jobStage->sort_order = $b;
                    $neighbor->sort_order = $a;
                }
                $jobStage->save();
                $neighbor->save();
            });
        }
        return back();
    }

    public function updateJobStage(Request $request, \App\Models\JobStage $jobStage)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'required|string|max:20',
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

    // === Holiday Types ===

    public function storeHolidayType(Request $request)
    {
        $this->requireAdmin();
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:holiday_types,code|alpha_dash',
            'name' => 'required|string|max:100',
            'default_color' => ['required', 'string', 'max:30', \Illuminate\Validation\Rule::in(array_keys(HolidayType::COLOR_PRESETS))],
            'icon' => 'nullable|string|max:10',
        ]);

        $validated['is_system'] = false;
        $validated['is_active'] = true;
        $validated['sort_order'] = (int) HolidayType::max('sort_order') + 10;

        $type = HolidayType::create($validated);
        $this->audit->logCreated($type, 'เพิ่มประเภทวันหยุด: ' . $type->name);

        return back()->with('success', "เพิ่มประเภทวันหยุด \"{$type->name}\" สำเร็จ");
    }

    public function moveHolidayType(Request $request, HolidayType $holidayType)
    {
        $this->requireAdmin();
        $dir = $request->validate(['direction' => 'required|in:up,down'])['direction'];

        $neighbor = HolidayType::where('sort_order', $dir === 'up' ? '<' : '>', $holidayType->sort_order)
            ->orderBy('sort_order', $dir === 'up' ? 'desc' : 'asc')
            ->orderBy('id', $dir === 'up' ? 'desc' : 'asc')
            ->first();

        if (!$neighbor) {
            return back();
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($holidayType, $neighbor) {
            [$a, $b] = [$holidayType->sort_order, $neighbor->sort_order];
            if ($a === $b) {
                // Resolve tie-breaker by spreading
                $holidayType->sort_order = $a;
                $neighbor->sort_order = $a + ($holidayType->id > $neighbor->id ? -1 : 1);
            } else {
                $holidayType->sort_order = $b;
                $neighbor->sort_order = $a;
            }
            $holidayType->save();
            $neighbor->save();
        });

        return back();
    }

    public function updateHolidayType(Request $request, HolidayType $holidayType)
    {
        $this->requireAdmin();
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'default_color' => ['required', 'string', 'max:30', \Illuminate\Validation\Rule::in(array_keys(HolidayType::COLOR_PRESETS))],
            'icon' => 'nullable|string|max:10',
            'is_active' => 'nullable|boolean',
        ]);

        // System types: code is locked.
        if (!$holidayType->is_system && $request->filled('code')) {
            $request->validate([
                'code' => 'required|string|max:50|alpha_dash|unique:holiday_types,code,' . $holidayType->id,
            ]);
            $validated['code'] = $request->input('code');
        }

        $validated['is_active'] = $request->boolean('is_active');

        $old = $holidayType->toArray();
        $holidayType->update($validated);
        $this->audit->logUpdated($holidayType, collect($old)->only(array_keys($validated))->toArray(), 'แก้ไขประเภทวันหยุด');

        return back()->with('success', "อัปเดตประเภทวันหยุด \"{$holidayType->name}\" สำเร็จ");
    }

    public function deleteHolidayType(HolidayType $holidayType)
    {
        $this->requireAdmin();

        if ($holidayType->is_system) {
            return back()->with('error', "ไม่สามารถลบประเภทวันหยุดระบบ \"{$holidayType->name}\" ได้");
        }

        $usage = $holidayType->companyHolidays()->count();
        if ($usage > 0) {
            return back()->with('error', "ไม่สามารถลบ \"{$holidayType->name}\" ได้ — มีวันหยุดใช้งานอยู่ {$usage} รายการ");
        }

        $name = $holidayType->name;
        $this->audit->logDeleted($holidayType, 'ลบประเภทวันหยุด: ' . $name);
        $holidayType->delete();

        return back()->with('success', "ลบประเภทวันหยุด \"{$name}\" สำเร็จ");
    }
}
