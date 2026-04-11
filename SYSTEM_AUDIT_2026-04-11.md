# xHR Payroll & Finance System — Full System Audit Report
**Date:** 2026-04-11  
**Auditor:** GitHub Copilot (Senior System Auditor)  
**Scope:** Full codebase audit against AGENTS.md specification

---

## SECTION 1 — What Has Been Built (Inventory)

### 1.1 Database / Migrations

**Total Migration Files:** 39 (12 core + 27 incremental)  
**Total Tables Created:** 32+

#### Core Tables (from initial migrations)

| Migration File | Table | Key Columns | Foreign Keys | Indexes |
|---|---|---|---|---|
| `000000_create_users_table` | `users` | id, name, email, password | — | email (unique) |
| | `password_reset_tokens` | email (PK), token | — | — |
| | `sessions` | id (PK), user_id, payload, last_activity | — | user_id, last_activity |
| `000001_create_cache_table` | `cache` | key (PK), value, expiration | — | — |
| | `cache_locks` | key (PK), owner, expiration | — | — |
| `000002_create_jobs_table` | `jobs` | id, queue, payload, attempts | — | queue |
| | `job_batches` | id (PK), name, total/pending/failed_jobs | — | — |
| | `failed_jobs` | id, uuid, connection, queue, payload | — | uuid (unique) |
| `000003_create_roles_permissions_tables` | `roles` | id, name, display_name, description | — | name (unique) |
| | `permissions` | id, name, display_name, description | — | name (unique) |
| | `role_user` | role_id, user_id | roles.id, users.id (cascade) | composite PK |
| | `permission_role` | permission_id, role_id | permissions.id, roles.id (cascade) | composite PK |
| `000004_create_departments_positions_tables` | `departments` | id, name, code, is_active | — | code (unique) |
| | `positions` | id, name, code, department_id, is_active | departments.id (set null) | code (unique) |
| `000005_create_employees_tables` | `employees` | id, user_id, employee_code, first_name, last_name, nickname, department_id, position_id, payroll_mode, status, is_active, start_date, end_date | users.id, departments.id, positions.id (set null) | payroll_mode, status |
| | `employee_profiles` | id, employee_id (unique), id_card, address, phone, email, photo, birth_date | employees.id (cascade) | employee_id (unique) |
| | `employee_salary_profiles` | id, employee_id, base_salary decimal(12,2), effective_date, is_current | employees.id (cascade) | (employee_id, is_current) |
| | `employee_bank_accounts` | id, employee_id, bank_name, account_number, account_name, is_primary | employees.id (cascade) | — |
| `000006_create_attendance_worklogs` | `attendance_logs` | id, employee_id, log_date, day_type, check_in, check_out, late_minutes, early_leave_minutes, ot_minutes, ot_enabled, lwop_flag, notes | employees.id (cascade) | log_date, (employee_id, log_date) unique |
| | `work_log_types` | id, name, code (unique), payroll_mode, is_active | — | code (unique) |
| | `work_logs` | id, employee_id, month, year, log_date, work_type, layer, hours, minutes, seconds, quantity, rate decimal(12,4), amount decimal(12,2), sort_order, notes | employees.id (cascade) | (employee_id, month, year) |
| `000007_create_payroll_tables` | `payroll_item_types` | id, code (unique), label_th, label_en, category, is_system, sort_order | — | code (unique) |
| | `payroll_batches` | id, month, year, status, created_by, finalized_at | users.id (set null) | (month, year) unique |
| | `payroll_items` | id, employee_id, payroll_batch_id, item_type_code, category, label, amount decimal(12,2), source_flag, sort_order, notes | employees.id (cascade), payroll_batches.id (cascade) | (employee_id, payroll_batch_id) |
| `000008_create_rules_config_tables` | `rate_rules` | id, employee_id, rate_type, rate decimal(12,2), effective_date, is_active | employees.id (cascade) | — |
| | `layer_rate_rules` | id, employee_id, layer_from, layer_to, rate_per_minute decimal(12,4), effective_date, is_active | employees.id (cascade) | (employee_id, is_active) |
| | `bonus_rules` | id, name, payroll_mode, condition_type, condition_value, amount decimal(12,2), is_active | — | — |
| | `threshold_rules` | id, name, metric, operator, threshold_value, result_action, result_value, is_active | — | — |
| | `social_security_configs` | id, effective_date, employee_rate decimal(5,2), employer_rate decimal(5,2), salary_ceiling decimal(12,2), max_contribution decimal(12,2), is_active | — | — |
| | `attendance_rules` | id, rule_type, config (JSON), effective_date, is_active | — | — |
| | `module_toggles` | id, employee_id, module_name, is_enabled | employees.id (cascade) | (employee_id, module_name) unique |
| `000009_create_payslips_tables` | `payslips` | id, employee_id, payroll_batch_id, month, year, total_income, total_deduction, net_pay decimal(12,2), status, finalized_at, finalized_by, payment_date, meta (JSON) | employees.id (cascade), payroll_batches.id (set null), users.id (set null) | (employee_id, month, year) unique |
| | `payslip_items` | id, payslip_id, category, label, amount decimal(12,2), sort_order | payslips.id (cascade) | — |
| `000010_create_company_finance` | `company_expenses` | id, category, description, amount, month, year | — | (month, year) |
| | `company_revenues` | id, source, description, amount, month, year | — | (month, year) |
| | `subscription_costs` | id, name, amount, is_recurring, month, year | — | (month, year) |
| | `expense_claims` | id, employee_id, description, amount, status, month, year | employees.id (cascade) | — |
| `000011_create_audit_logs` | `audit_logs` | id, user_id, auditable_type, auditable_id, field, old_value, new_value, action, reason, created_at | users.id (set null) | (auditable_type, auditable_id), created_at |

#### Additional Tables (from incremental migrations)

| Migration | Table/Alteration | Purpose |
|---|---|---|
| `2026_04_04_134609` | CREATE `payment_proofs` | Payslip payment proof uploads |
| | ALTER `payslips` + position_override | Add position override display |
| `2026_04_04_134727` | ALTER `attendance_logs` + is_disabled | Allow disabling individual logs |
| | ALTER `work_logs` + is_disabled | Allow disabling individual logs |
| `2026_04_04_144351` | ALTER `expense_claims` + type, claim_date, approved_at | Expense claim enhancements |
| `2026_04_04_162831` | ALTER `employees` + advance_ceiling_percent | Cash advance ceiling |
| | CREATE `company_holidays` | Company holiday management |
| `2026_04_04_181447` | ALTER `work_logs` + soft deletes | Soft delete support |
| `2026_04_04_181453` | ALTER `expense_claims` + soft deletes | Soft delete support |
| `2026_04_04_184735` | ALTER `work_logs` + pricing_mode, custom_rate, pricing_template_label | Flexible pricing |
| `2026_04_05_000000` | CREATE `company_profiles` | Company branding/payslip config |
| `2026_04_05_000001` | ALTER `company_profiles` + signature fields | Payslip signature support |
| `2026_04_05_000002` | CREATE `performance_records` | Employee performance tracking |
| `2026_04_05_000003` | ALTER `performance_records` + work history fields | Rich performance data |
| `2026_04_05_230000` | ALTER `performance_records` + workflow fields | Approval workflow |
| `2026_04_05_231000` | ALTER `work_log_types` + custom fields | Configurable work types |
| `2026_04_05_232000` | CREATE `work_assignments` | Task assignment tracking |
| `2026_04_06_061627` | ALTER `work_logs` + entry_type | Income/deduction classification |
| `2026_04_06_070000` | CREATE `recording_jobs`, `recording_job_assignees`, `media_resources`, `edit_jobs`, `approved_work_outputs` | Full work command pipeline |
| `2026_04_06_150000` | ALTER `attendance_logs` + swap fields; CREATE `attendance_day_swaps` | Day type swap feature |
| `2026_04_06_160000` | ALTER `recording_jobs` + scheduled_time | Time scheduling |
| `2026_04_06_161000` | ALTER `recording_jobs` + game_type | Game categorization |
| `2026_04_07_120000` | ALTER `recording_jobs` + footage fields | Footage tracking |
| `2026_04_07_140000` | ALTER `media_resources` + footage_count | Media tracking |
| `2026_04_07_200000` | ALTER `positions` + workspace_panel | Panel assignment |
| `2026_04_08_100000` | ALTER `edit_jobs` + pricing fields | Edit job pricing |
| `2026_04_08_100001` | ALTER `work_logs` + source_flag, edit_job_id | Work log provenance |
| `2026_04_09_143746` | CREATE `job_stages` | Configurable workflow stages |
| `2026_04_09_150346` | ALTER `job_stages` + is_active | Stage activation |

#### ❌ MISSING Tables (defined in AGENTS.md Section 7.1 but not found)

| Table | Status |
|---|---|
| `company_monthly_summaries` | ❌ NOT CREATED — referenced in AGENTS.md Section 2.2 as source of truth for company monthly finance |

> All other tables from AGENTS.md Section 7.1 are present.

---

### 1.2 Models

**Total Models:** 41

| Model | Table | Key Relationships |
|---|---|---|
| ApprovedWorkOutput | approved_work_outputs | belongsTo: EditJob, User |
| AttendanceDaySwap | attendance_day_swaps | belongsTo: Employee, AttendanceLog, User |
| AttendanceLog | attendance_logs | belongsTo: Employee; appends: working_minutes |
| AttendanceRule | attendance_rules | Static: getActiveRule() |
| AuditLog | audit_logs | belongsTo: User; morphTo: auditable |
| BonusRule | bonus_rules | — |
| CompanyExpense | company_expenses | — |
| CompanyHoliday | company_holidays | — |
| CompanyProfile | company_profiles | Static: active() |
| CompanyRevenue | company_revenues | — |
| Department | departments | hasMany: Position, Employee |
| EditJob | edit_jobs | belongsTo: MediaResource, Employee, User; hasMany: ApprovedWorkOutput |
| **Employee** | employees | **20 relationships** — user, department, position, profile, salaryProfile, salaryHistory, bankAccount(s), attendanceLogs, workLogs, payrollItems, payslips, moduleToggles, layerRateRules, rateRules, expenseClaims, performanceRecords, workAssignments |
| EmployeeBankAccount | employee_bank_accounts | belongsTo: Employee |
| EmployeeProfile | employee_profiles | belongsTo: Employee |
| EmployeeSalaryProfile | employee_salary_profiles | belongsTo: Employee |
| ExpenseClaim | expense_claims | belongsTo: Employee; SoftDeletes |
| JobStage | job_stages | — |
| LayerRateRule | layer_rate_rules | belongsTo: Employee |
| MediaResource | media_resources | belongsTo: RecordingJob; hasMany: EditJob |
| ModuleToggle | module_toggles | belongsTo: Employee |
| PaymentProof | payment_proofs | belongsTo: Employee, Payslip |
| PayrollBatch | payroll_batches | belongsTo: User; hasMany: PayrollItem, Payslip |
| PayrollItem | payroll_items | belongsTo: Employee, PayrollBatch |
| PayrollItemType | payroll_item_types | — |
| Payslip | payslips | belongsTo: Employee, PayrollBatch, User; hasMany: PayslipItem (+ scoped incomeItems, deductionItems) |
| PayslipItem | payslip_items | belongsTo: Payslip |
| PerformanceRecord | performance_records | belongsTo: Employee |
| Permission | permissions | belongsToMany: Role |
| Position | positions | belongsTo: Department; hasMany: Employee |
| RateRule | rate_rules | belongsTo: Employee |
| RecordingJob | recording_jobs | hasMany: RecordingJobAssignee, MediaResource; belongsToMany: Employee; belongsTo: User |
| RecordingJobAssignee | recording_job_assignees | belongsTo: RecordingJob, Employee |
| Role | roles | belongsToMany: User, Permission |
| SocialSecurityConfig | social_security_configs | Static: getCurrentConfig() |
| SubscriptionCost | subscription_costs | — |
| ThresholdRule | threshold_rules | — |
| User | users | belongsToMany: Role; hasOne: Employee; hasRole() helper |
| WorkAssignment | work_assignments | belongsTo: Employee, WorkLogType |
| WorkLog | work_logs | belongsTo: Employee, EditJob; SoftDeletes; appends: duration_minutes |
| WorkLogType | work_log_types | hasMany: WorkAssignment |

#### ❌ MISSING Models (from AGENTS.md Section 4.2)

| Entity | Status | Notes |
|---|---|---|
| CompanyMonthlySummary | ❌ NOT CREATED | No table or model |

> All other entities from Section 4.2 are present. The system has MORE models than specified (41 vs ~27 required), covering work command pipeline entities.

---

### 1.3 Services

**Total Services:** 9

| Service | Location | Purpose |
|---|---|---|
| `AuditLogService` | app/Services/AuditLogService.php | Static methods for audit trail logging (log, logCreated, logUpdated, logDeleted, logAction) |
| `HolidayService` | app/Services/HolidayService.php | Returns Thai public holidays by year (hardcoded calendar data for 2025-2027) |
| `SocialSecurityService` | app/Services/SocialSecurityService.php | Calculates SSO contributions from database config (no hardcoded amounts) |
| `PayrollCalculationService` | app/Services/Payroll/PayrollCalculationService.php | **Orchestrator** — routes to mode-specific calculators, handles expense claims, saves payroll items, finalizes payslips |
| `MonthlyStaffCalculator` | app/Services/Payroll/MonthlyStaffCalculator.php | Monthly staff payroll: base salary, OT, diligence, LWOP, late deduction, SSO |
| `FreelanceLayerCalculator` | app/Services/Payroll/FreelanceLayerCalculator.php | Freelance layer: duration × layer rate from LayerRateRule table |
| `FreelanceFixedCalculator` | app/Services/Payroll/FreelanceFixedCalculator.php | Freelance fixed: quantity × rate from WorkLog |
| `YoutuberSalaryCalculator` | app/Services/Payroll/YoutuberSalaryCalculator.php | Delegates to MonthlyStaffCalculator + appends work log notes |
| `YoutuberSettlementCalculator` | app/Services/Payroll/YoutuberSettlementCalculator.php | Income/deduction per work log entry, net settlement |

#### ❌ MISSING Services (from AGENTS.md Section 13)

| Service | Status | Impact |
|---|---|---|
| `EmployeeService` | ❌ | Employee CRUD logic is in controller |
| `AttendanceService` | ❌ | Attendance logic is in WorkspaceController |
| `WorkLogService` | ❌ | Work log logic is in controllers |
| `BonusRuleService` | ❌ | Bonus rules exist in DB but no service reads them |
| `PayslipService` | ❌ | Payslip logic is split between PayrollCalculationService and PayslipController |
| `CompanyFinanceService` | ❌ | P&L calculation is in CompanyFinanceController |
| `ModuleToggleService` | ❌ | Toggle check exists on Employee model but no dedicated service |

---

### 1.4 Controllers

**Total Controllers:** 13 (including base Controller)

| Controller | Routes/Features | Thin-Controller Compliant? |
|---|---|---|
| `AuthController` | Login/logout | ✅ Yes |
| `EmployeeController` | CRUD employees, toggle status, filter/search | ⚠️ No — store/update create multiple related records without transaction or service |
| `WorkspaceController` | Employee workspace (main payroll entry page) | ❌ No — `show()` is 300+ lines; `saveAttendance`, `saveWorkLogs`, `storePerformanceRecord` contain heavy business logic |
| `PayslipController` | Preview, finalize, unfinalize, PDF download | ⚠️ Partial — delegates main calc to service but has business logic in protected helpers |
| `AnnualSummaryController` | 12-month annual summary view | ❌ No — loops 12 months × employees with inline queries/calculations |
| `CompanyFinanceController` | Revenue/expense/subscription CRUD + P&L view | ⚠️ Partial — CRUD is thin but `index()` calculates P&L inline |
| `AuditLogController` | Audit log viewer with filters | ⚠️ Acceptable — complex query building inline |
| `CalendarController` | Calendar view with events | ❌ No — massive event aggregation and date calculations inline |
| `SettingsController` | Rules, holidays, company profile | ⚠️ Partial — `updateRule` has complex SSO/diligence config logic inline |
| `MasterDataController` | Payroll item types, departments, positions, job stages | ✅ Mostly thin — good guards on delete |
| `WorkCommandController` | Recording jobs, media resources, edit jobs pipeline | ❌ No — work log auto-creation, code generation, pricing calculations inline |
| `WorkManagerController` | Work log type config, assignments | ⚠️ Partial — `mapPayload` has business logic |

**Validation:** ALL controllers use inline `validate()` — **zero FormRequest classes** exist.

---

### 1.5 Views / UI Modules

**Total Blade Views:** 27 files

| UI Module | Files | Status |
|---|---|---|
| **Layout** | `layouts/app.blade.php` | ✅ Built |
| **Auth / Login** | `auth/login.blade.php` | ✅ Built |
| **Employee Board** | `employees/index.blade.php`, `create.blade.php`, `edit.blade.php` | ✅ Built |
| **Employee Workspace** | `workspace/show.blade.php` + 7 partials (attendance-grid, claims-grid, freelance-layer-grid, freelance-fixed-grid, youtuber-salary-grid, youtuber-settlement-grid, performance-records) | ✅ Built |
| **Payslip** | `payslip/preview.blade.php`, `payslip/pdf.blade.php` | ✅ Built |
| **Annual Summary** | `annual/index.blade.php` | ✅ Built |
| **Company Finance** | `company/finance.blade.php`, `company/expenses.blade.php` | ✅ Built |
| **Calendar** | `calendar/index.blade.php` | ✅ Built |
| **Audit Logs** | `audit/index.blade.php` | ✅ Built |
| **Settings / Rules** | `settings/rules.blade.php` | ✅ Built |
| **Settings / Company** | `settings/company.blade.php` | ✅ Built |
| **Settings / Master Data** | `settings/master-data.blade.php` | ✅ Built |
| **Settings / Works** | `settings/works/index.blade.php` | ✅ Built |
| **Work Command** | `work/index.blade.php` | ✅ Built |
| **Navigation** | `partials/grid-navigation.blade.php` | ✅ Built |

#### ❌ MISSING UI Modules (from AGENTS.md Section 6)

| Module | Status | Notes |
|---|---|---|
| **Rule Manager (dedicated)** | ⚠️ Partial | Settings/rules exists but no full CRUD for ALL rule types (bonus, threshold, layer rates per employee) |
| **Detail Inspector** | ❌ | No row-click inspector showing source, formula, audit history per payroll item |
| **Forgot Password** | ❌ | Listed as optional — not built |

---

### 1.6 Routes

**Total Routes:** ~70 routes, all in `web.php` (no `api.php`)

#### Routes Grouped by Module

| Module | Route Prefix | Count | Auth? |
|---|---|---|---|
| **Auth** | `/login`, `/logout` | 3 | No (public) |
| **Employee Management** | `/employees/*` | 8 | ✅ Yes |
| **Employee Workspace** | `/workspace/*` | 16 | ✅ Yes |
| **Calendar** | `/calendar/*` | 1 | ✅ Yes |
| **Payslip** | `/payslip/*` | 4 | ✅ Yes |
| **Company Finance** | `/company/*` | 9 | ✅ Yes |
| **Annual Summary** | `/annual` | 1 | ✅ Yes |
| **Audit Logs** | `/audit-logs` | 1 | ✅ Yes |
| **Work Command** | `/work/*` | 12 | ✅ Yes |
| **Settings** | `/settings/*` | 17 | ✅ Yes |

> All authenticated routes use the `auth` middleware. No API routes exist.

---

## SECTION 2 — Data Flow & Relationships

### 2.1 Core Data Relationships

```
employees (payroll_mode) 
   ├── employee_salary_profiles (base_salary, is_current)
   ├── attendance_logs (monthly attendance data)
   ├── work_logs (freelance/youtuber work entries)
   ├── module_toggles (feature flags per employee)
   ├── layer_rate_rules (per-employee layer pricing)
   │
   └── payroll_items ──→ payroll_batches (month/year)
         │
         └── payslips ──→ payslip_items (snapshot)
```

**Foreign Key Enforcement:** ✅ Yes — All core relationships use proper foreign keys with cascade/set-null behavior:
- `payroll_items.employee_id` → `employees.id` (cascade)
- `payroll_items.payroll_batch_id` → `payroll_batches.id` (cascade)
- `payslips.employee_id` → `employees.id` (cascade)
- `payslips.payroll_batch_id` → `payroll_batches.id` (set null)
- `payslip_items.payslip_id` → `payslips.id` (cascade)

**Data Flow:**
1. Employee has a `payroll_mode` and `employee_salary_profiles` (master data)
2. Monthly inputs: `attendance_logs` / `work_logs` / `expense_claims`
3. `PayrollCalculationService.calculateForEmployee()` reads inputs → produces result items
4. `savePayrollItems()` writes to `payroll_items` (transaction-based)
5. `finalizePayslip()` snapshots `payroll_items` → `payslip_items` and creates/updates `payslips`

### 2.2 Payroll Calculation Flow

| Mode | Data Entry | Calculator Service | Result Storage | Payslip Snapshot |
|---|---|---|---|---|
| `monthly_staff` | attendance_logs via workspace | `MonthlyStaffCalculator.calculate()` | payroll_items via savePayrollItems() | ✅ via finalizePayslip() |
| `freelance_layer` | work_logs via workspace (duration × layer rate) | `FreelanceLayerCalculator.calculate()` | payroll_items | ✅ |
| `freelance_fixed` | work_logs via workspace (qty × rate) | `FreelanceFixedCalculator.calculate()` | payroll_items | ✅ |
| `youtuber_salary` | attendance_logs + work_logs for notes | `YoutuberSalaryCalculator.calculate()` (delegates to MonthlyStaffCalculator) | payroll_items | ✅ |
| `youtuber_settlement` | work_logs with entry_type (income/deduction) | `YoutuberSettlementCalculator.calculate()` | payroll_items | ✅ |
| `custom_hybrid` | — | ❌ **NOT IMPLEMENTED** — falls back to MonthlyStaffCalculator | — | — |

### 2.3 Rule Pipeline

| Rule Table | Created? | Read by Service? | Notes |
|---|---|---|---|
| `bonus_rules` | ✅ Table exists | ❌ **NOT READ** | Table/model exist but no service/calculator reads bonus_rules during payroll |
| `threshold_rules` | ✅ Table exists | ❌ **NOT READ** | Table/model exist but no service references them |
| `layer_rate_rules` | ✅ Table exists | ✅ Read by FreelanceLayerCalculator | Working correctly |
| `social_security_configs` | ✅ Table exists | ✅ Read by SocialSecurityService | Working — config-driven, not hardcoded |
| `attendance_rules` | ✅ Table exists | ✅ Read by MonthlyStaffCalculator | Used for working_hours, diligence, late_deduction, ot_rate |
| `rate_rules` | ✅ Table exists | ❌ **NOT READ** | Table/model exist but no calculator reads rate_rules |
| `module_toggles` | ✅ Table exists | ✅ Read by MonthlyStaffCalculator (SSO check) | Used for feature toggling |

**Conclusion:** 3 out of 7 rule tables (`bonus_rules`, `threshold_rules`, `rate_rules`) are **dead tables** — created but never consumed by any service.

### 2.4 Audit Trail

- **`audit_logs` table:** ✅ Exists with polymorphic relationship
- **`AuditLogService`:** ✅ Exists with log/logCreated/logUpdated/logDeleted/logAction methods
- **AuditLog viewer UI:** ✅ Exists at `/audit-logs`

#### Audit Coverage

| Event | Logged? | Where |
|---|---|---|
| Employee salary profile change | ⚠️ Partial | AuditLogService called in some controllers |
| Payroll item change | ⚠️ Partial | PayrollCalculationService logs batch actions |
| Payslip finalize/unfinalize | ✅ | PayslipController |
| SSO config change | ✅ | SettingsController.updateRule |
| Bonus rule change | ❌ | No CRUD UI for bonus rules, no logging |
| Module toggle change | ✅ | WorkspaceController.toggleModule |
| Employee status change | ✅ | EmployeeController.toggleStatus |
| Rule changes | ⚠️ Partial | SettingsController logs some rule updates |
| Company finance changes | ✅ | CompanyFinanceController logs revenue/expense changes |
| Work log changes | ❌ | WorkspaceController.saveWorkLogs does not log individual changes |
| Attendance changes | ❌ | WorkspaceController.saveAttendance does not audit field-level changes |

---

## SECTION 3 — What Is Working vs Not Working

| Feature | Status | Notes |
|---|---|---|
| Add new employee | ✅ | Create form + store in EmployeeController; related records created |
| Set payroll mode | ✅ | payroll_mode field on employee; set during create/edit |
| Enter payroll in single workspace | ✅ | WorkspaceController.show provides unified workspace with mode-specific grids |
| Calculate monthly_staff correctly | ✅ | MonthlyStaffCalculator handles base_salary, OT, diligence, LWOP, late, SSO |
| Calculate freelance_layer correctly | ✅ | FreelanceLayerCalculator reads LayerRateRules; resolves rate priority correctly |
| Calculate freelance_fixed correctly | ✅ | FreelanceFixedCalculator: qty × rate per WorkLog |
| Calculate youtuber salary/settlement | ✅ | YoutuberSalaryCalculator delegates to monthly; YoutuberSettlementCalculator handles income/deduction work logs |
| SSO calculation from config | ✅ | SocialSecurityService reads social_security_configs table; no hardcoded 750 |
| Generate payslip PDF | ✅ | PayslipController.downloadPdf using DomPDF; pdf.blade.php template exists |
| View annual summary | ⚠️ | UI exists but calculations are done in controller (no service); footer sums computed in Blade |
| View company P&L | ⚠️ | UI exists but P&L calculations inline in controller; no CompanyFinanceService |
| Audit logs working | ⚠️ | Audit infrastructure exists and viewer works, but coverage is incomplete (work logs, attendance not logged) |
| System extendable | ⚠️ | Architecture supports extension (payroll modes, rules), but 3 rule tables unused; lacking dedicated services for key domains |
| custom_hybrid mode | ❌ | Falls back to MonthlyStaffCalculator; no dedicated implementation |
| Bonus rules applied | ❌ | Table exists but never read by any calculator; performance_bonus is hardcoded as 0 |
| Threshold rules applied | ❌ | Table exists but never consumed |
| Rate rules applied | ❌ | Table exists but never consumed |
| Detail Inspector | ❌ | Not built — AGENTS.md Section 9.5 requires row-click source/formula/audit inspector |
| FormRequest validation | ❌ | Zero FormRequest classes; all validation is inline |
| Tests | ❌ | No payroll calculation tests, SSO tests, or payslip snapshot tests found |

---

## SECTION 4 — Limits & Constraints

### 4.1 Hard Limits Found

| Hardcoded Value | Location | AGENTS.md Requirement |
|---|---|---|
| `540` (target minutes/day = 9 hours) | MonthlyStaffCalculator line ~33 | Should be fully from attendance_rules config (it is a **fallback** if config missing) |
| `22` (working days/month) | MonthlyStaffCalculator line ~34 | Same — fallback default |
| `40` (max OT hours) | MonthlyStaffCalculator line ~64 | Same — fallback default |
| `1.0` (OT rate multiplier) | MonthlyStaffCalculator line ~71 | Same — fallback default |
| `500` (diligence allowance default) | MonthlyStaffCalculator line ~90 | Should be ONLY from config; 500 THB default violates rule-driven principle |
| `0` (performance_bonus) | MonthlyStaffCalculator | Always 0; bonus_rules table exists but is never queried |
| `99` (sort_order for claims) | PayrollCalculationService | Minor magic number |
| `50` (sort_order for work notes) | YoutuberSalaryCalculator | Minor magic number |
| Holiday dates 2025-2027 | HolidayService | Hardcoded but by nature (Buddhist calendar dates vary); acceptable as seed data |
| `'Pro One IT Co., Ltd.'` | company_profiles migration default | Acceptable — seed data with UI for updates |

**Key Finding:** SSO is NOT hardcoded — `SocialSecurityService` correctly reads from `social_security_configs`. Layer rates are NOT hardcoded — read from `layer_rate_rules`. The main risk area is MonthlyStaffCalculator's fallback defaults.

### 4.2 Performance Risks

| Risk | Severity | Location |
|---|---|---|
| **N+1 in AnnualSummaryController** | 🔴 High | Loops 12 months × N employees, each performing 3 queries (income sum, deduction sum, finalized check) |
| **N+1 in CompanyFinanceController.index** | 🟡 Medium | Loops 12 months with multiple queries per month |
| **N+1 in WorkspaceController.show** | 🟡 Medium | Loads attendance, work_logs, payroll items, payslip, claims separately without batch loading |
| **N+1 in CalendarController** | 🟡 Medium | Multiple queries for holidays, logs, recordings, edits, stages |
| **Employee.average_minutes_last_3_months appended** | 🟡 Medium | Computed attribute with DB query; loaded on every Employee serialization |
| **Indexes missing on frequently queried columns** | 🟡 Medium | `expense_claims` has FK on employee_id but no index on (month, year, type); `payslip_items` has no index on payslip_id (relies on FK only) |

### 4.3 Data Integrity Risks

| Risk | Severity | Details |
|---|---|---|
| **Payslip items editable after finalize** | 🔴 High | No database-level protection prevents editing payslip_items after payslip status='finalized'. Protection depends on application logic only. |
| **source_flag stored per payroll_item** | ✅ | Yes — `payroll_items.source_flag` column exists (default 'auto') and MonthlyStaffCalculator sets 'manual'/'override' from existing items |
| **source_flag NOT copied to payslip_items** | ⚠️ | `payslip_items` table lacks `source_flag` column — snapshot loses provenance information |
| **Employee create without transaction** | 🟡 Medium | EmployeeController.store creates employee + profile + salary + bank without DB transaction |
| **PayrollBatch has unique (month,year)** | ✅ | Prevents duplicate batches per month |
| **Payslip has unique (employee_id, month, year)** | ✅ | Prevents duplicate payslips |
| **No version/lock on payslip_items** | 🟡 | No optimistic locking mechanism |

### 4.4 Security Risks

| Risk | Severity | Details |
|---|---|---|
| **EmployeePolicy exists but may not be registered** | 🟡 | Policy file exists but `viewAny()` returns false unconditionally — would block employee listing if enforced |
| **No policy checks on most routes** | 🔴 High | Controllers do not call `$this->authorize()` or use policy middleware; any authenticated user can access all routes |
| **All routes behind auth middleware** | ✅ | All non-auth routes require login |
| **No role-based route protection** | 🔴 High | Despite roles table existing (admin/hr/viewer), no route middleware checks roles; a "viewer" can edit payroll |
| **No CSRF concern** | ✅ | Laravel handles CSRF by default for web routes |
| **File upload validation** | ⚠️ | WorkspaceController.uploadProof validates file type but no virus scanning |
| **No rate limiting on login** | 🟡 | AuthController.login has no throttle middleware |

---

## SECTION 5 — Anti-Pattern Violations

| Anti-Pattern (AGENTS.md §15) | Violation Found? | Location | Severity |
|---|---|---|---|
| **Business logic in Blade** | ✅ YES | `claims-grid.blade.php` — advance ceiling calculations; `annual/index.blade.php` — footer sums; `company/finance.blade.php` — inline arithmetic | 🔴 |
| **Payroll calculation in Blade** | ⚠️ PARTIAL | `attendance-grid.blade.php` — client-side late/OT calculation in JavaScript (server recalculates on save) | 🟡 |
| **Hardcoded legal values** | ⚠️ PARTIAL | MonthlyStaffCalculator — fallback defaults (540, 22, 40, 500) exist alongside config reads | 🟡 |
| **Copy-pasted logic across services** | ✅ NO | Calculators are properly separated; YoutuberSalaryCalculator delegates to MonthlyStaffCalculator | ✅ |
| **Employee name used as key** | ✅ NO | All queries use employee_id | ✅ |
| **Report page as source of truth** | ✅ NO | Reports read from payroll_items/payslips | ✅ |
| **PDF calculates at render time** | ✅ YES | `payslip/pdf.blade.php` — queries `WorkLog` model directly in template instead of reading from snapshot | 🔴 |
| **Manual override hidden from user** | ✅ NO | source_flag field exists and resolveItem() respects manual/override flags | ✅ |
| **DB query in view** | ✅ YES | `payslip/pdf.blade.php` — `WorkLog::where(...)` query in Blade template | 🔴 |

---

## SECTION 6 — Gap Analysis vs AGENTS.md Deliverables (Section 16)

| # | Deliverable | Complete? | Gap Description |
|---|---|---|---|
| 1 | Project structure | ✅ | Standard Laravel structure; Models, Services, Controllers, Views all properly organized |
| 2 | Database schema | ⚠️ 90% | All core tables exist; `company_monthly_summaries` missing; 3 rule tables created but never consumed |
| 3 | Migrations | ✅ | 39 migration files; well-structured; proper FKs and indexes |
| 4 | Seed data | ⚠️ 70% | DatabaseSeeder creates roles, departments, positions, payroll item types, SSO config, attendance rules, sample employees; but only 1 dedicated seeder (JobStageSeeder); no seed for bonus_rules/threshold_rules |
| 5 | Model relationships | ✅ | 41 models with comprehensive relationships; Employee model has 20 relationships |
| 6 | Payroll services | ⚠️ 80% | 5 of 6 payroll modes implemented; `custom_hybrid` not built; bonus/threshold/rate rules not integrated |
| 7 | Rule manager | ⚠️ 40% | Settings/rules UI exists for attendance_rules, SSO, holidays; but no UI for bonus_rules, threshold_rules, or per-employee layer_rate_rules management |
| 8 | Employee workspace UI | ✅ | Full workspace with mode-specific grids, attendance, claims, performance records |
| 9 | Payslip builder + PDF | ⚠️ 85% | Preview + PDF + finalize all work; but PDF queries DB in template (anti-pattern); no payslip_items.source_flag |
| 10 | Audit logs | ⚠️ 60% | Infrastructure complete; viewer works; but coverage gaps in attendance, work logs, and some rule changes |
| 11 | Annual summary | ⚠️ 70% | 12-month view exists; but calculations done in controller not service; footer sums in Blade |
| 12 | Company finance summary | ⚠️ 70% | Revenue/expense/subscription CRUD + P&L view exist; but P&L calculated in controller; no CompanyMonthlySummary persistence |

---

## SECTION 7 — Recommendations

### 7.1 Critical Fixes (must fix before production)

1. **🔴 Remove DB query from payslip/pdf.blade.php** — The `WorkLog::where(...)` query in the PDF template violates the snapshot rule. PDF must render ONLY from `payslips` + `payslip_items`. Move work log data to payslip `meta` JSON during finalization.

2. **🔴 Implement role-based authorization on routes** — Currently any authenticated user (even "viewer" role) can create employees, edit payroll, finalize payslips. Add middleware or policy checks on all mutation routes.

3. **🔴 Wrap employee creation in a DB transaction** — `EmployeeController.store` creates employee + profile + salary_profile + bank_account without a transaction. A failure midway creates orphaned records.

4. **🔴 Add payslip finalization protection** — No database-level protection prevents editing `payslip_items` after `payslips.status = 'finalized'`. Add either a database trigger or mandatory application-level check before any payslip_items modification.

5. **🔴 Add source_flag to payslip_items** — When payroll_items are snapshotted to payslip_items, the `source_flag` (auto/manual/override) is lost. This violates the audit requirement from AGENTS.md Section 8.11.

### 7.2 High Priority (fix before next feature)

6. **Extract business logic from controllers to services** — WorkspaceController.show (300+ lines), saveAttendance, saveWorkLogs, storePerformanceRecord all contain business logic that should be in dedicated services (AttendanceService, WorkLogService, PerformanceService).

7. **Move calculations out of Blade views** — claims-grid.blade.php advance ceiling calculations, annual/index.blade.php footer sums, and company/finance.blade.php arithmetic must be pre-calculated in the controller/service.

8. **Integrate bonus_rules and threshold_rules into payroll** — These tables exist with data but are dead code. MonthlyStaffCalculator hardcodes `performance_bonus = 0`. Build a BonusRuleService that reads and applies these rules.

9. **Remove hardcoded fallback defaults from MonthlyStaffCalculator** — Values like 540, 22, 40, 500 should throw an error or use required config rather than silently falling back. Silent fallbacks mask missing configuration.

10. **Add FormRequest classes** — Zero FormRequest classes exist across 13 controllers. Create at least: StoreEmployeeRequest, UpdateEmployeeRequest, SaveAttendanceRequest, SaveWorkLogsRequest, StoreClaimRequest.

### 7.3 Medium Priority (next sprint)

11. **Create CompanyMonthlySummary table and service** — AGENTS.md specifies this as a source of truth. Currently P&L is recalculated from raw data on every page load.

12. **Build Detail Inspector UI** — AGENTS.md Section 9.5 requires a click-to-inspect panel showing source, formula, audit history per payroll item row.

13. **Improve audit coverage** — Add field-level audit logging for attendance changes (saveAttendance) and work log changes (saveWorkLogs).

14. **Fix N+1 query in AnnualSummaryController** — Extract to a service using eager loading and batched queries instead of per-employee-per-month individual queries.

15. **Implement custom_hybrid payroll mode** — Currently falls back to MonthlyStaffCalculator silently.

16. **Add rate limiting to login** — AuthController has no throttle protection.

17. **Write tests** — AGENTS.md Section 12.3 requires at minimum: payroll mode calculation tests, SSO calculation tests, layer rate tests, payslip snapshot tests, audit logging tests. Currently **zero tests** exist.

### 7.4 Suggested Next Implementation Steps

**Recommended sequence (by dependency and risk):**

1. **Authorization & Security** (1st) — Role-based access control is the highest-risk gap. Without it, any authenticated user has full system access. Add middleware checks for admin/hr/viewer roles.

2. **Extract Controller Logic → Services** (2nd) — This unblocks testability and reduces bug risk. Create AttendanceService, WorkLogService, EmployeeService. This is the most impactful refactor.

3. **Fix PDF Snapshot** (3rd) — Remove the DB query from pdf.blade.php and enrich the payslip snapshot with work log data during finalization. Quick fix, high impact.

4. **Integrate Dead Rule Tables** (4th) — Build BonusRuleService and ThresholdRuleService to consume the existing bonus_rules and threshold_rules tables. This completes the rule-driven architecture.

5. **FormRequest + Validation** (5th) — Extract inline validation to FormRequest classes. Improves code quality and enables validation testing.

6. **Tests** (6th) — With services extracted, write unit tests for all 5 payroll calculators, SSO, payslip snapshot, and audit logging.

7. **Detail Inspector + UI Polish** (7th) — Build the row-click inspector panel for the workspace.

8. **CompanyMonthlySummary + Annual Service** (8th) — Extract P&L and annual calculations to services; persist monthly summaries.

---

## Executive Summary

The xHR Payroll & Finance System has a **solid foundation** with 39 migrations creating 32+ tables, 41 Eloquent models with comprehensive relationships, and 5 working payroll mode calculators. The core payroll calculation flow (monthly_staff, freelance_layer, freelance_fixed, youtuber_salary, youtuber_settlement) is functional and properly service-based, with SSO correctly reading from database configuration rather than hardcoded values. However, the system has **critical gaps in authorization** (any authenticated user can access all features regardless of role), **anti-pattern violations** (database query in PDF template, business logic in Blade views, 300+ line controller methods), and **incomplete rule integration** (3 of 7 rule tables are created but never consumed by any service). The system has **zero automated tests** and **zero FormRequest classes**. Before production use, the top priorities are: implementing role-based route protection, fixing the PDF snapshot violation, wrapping multi-model operations in transactions, and extracting business logic from controllers into dedicated services to enable testing and maintainability. Overall system completion is estimated at **~70-75%** of the AGENTS.md specification.
