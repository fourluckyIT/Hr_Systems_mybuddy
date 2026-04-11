# GAP ANALYSIS REPORT — xHR System vs AGENTS.md
**วิเคราะห์สิ่งที่ขาดและยังไม่ได้ทำ ตามแผนงาน AGENTS.md**  
วันที่: 6 เมษายน 2026

---

## สารบัญ

1. [สรุปภาพรวม (Executive Summary)](#1-executive-summary)
2. [WORK Command Center — ขาดทั้งหมด](#2-work-command-center)
3. [Payroll Modes ที่ยังไม่มี](#3-payroll-modes-ที่ยังไม่มี)
4. [Authentication & RBAC](#4-authentication--rbac)
5. [Company Finance & P&L — Placeholder เท่านั้น](#5-company-finance--pl)
6. [Annual Summary — ยังไม่มีเลย](#6-annual-summary)
7. [Tests — แทบไม่มี](#7-tests)
8. [WORK Pipeline Tables ที่ขาด](#8-work-pipeline-tables)
9. [Workspace — สิ่งที่ยังขาด](#9-workspace-gaps)
10. [Payslip — สิ่งที่ยังขาด](#10-payslip-gaps)
11. [Audit — จุดอ่อน](#11-audit-gaps)
12. [Code Quality & Architecture Gaps](#12-code-quality-gaps)
13. [Anti-Patterns ที่ยังมีอยู่](#13-anti-patterns-ที่พบ)
14. [Checklist: Definition of Done](#14-definition-of-done-checklist)
15. [Action Plan แนะนำ](#15-action-plan)

---

## 1) Executive Summary

### สถานะโดยรวม: **~55% ของแผนใน AGENTS.md**

| หมวด | สถานะ | ความสมบูรณ์ |
|------|--------|------------|
| Employee Management | ✅ ใช้ได้จริง | 85% |
| Employee Workspace | ✅ ใช้ได้จริง | 75% |
| Payroll Engine (5 modes) | ✅ ทำงานได้ | 70% |
| Payslip + PDF | ✅ ทำงานได้ | 70% |
| Settings / Rules | ✅ ทำงานได้ | 75% |
| Master Data | ✅ ทำงานได้ | 80% |
| Work Template Manager | ✅ ทำงานได้ | 60% |
| Calendar | ✅ ทำงานได้ | 80% |
| **WORK Command Center** | ❌ ไม่มีเลย | **0%** |
| **Recording Jobs / Queue** | ❌ ไม่มีเลย | **0%** |
| **Media Resources / Library** | ❌ ไม่มีเลย | **0%** |
| **Edit Jobs / Queue** | ❌ ไม่มีเลย | **0%** |
| **Approved Work Outputs** | ❌ ไม่มีเลย | **0%** |
| **Authentication** | ❌ ไม่มี middleware | **5%** |
| **Company Finance / P&L** | ⚠️ Placeholder | **5%** |
| **Annual Summary** | ❌ ไม่มีเลย | **0%** |
| **Tests** | ❌ มีแค่ placeholder | **2%** |
| Payroll Modes ใหม่ (4 modes) | ❌ ไม่มีเลย | **0%** |
| Audit Log | ⚠️ ใช้ได้แต่มี bug | **60%** |

---

## 2) WORK Command Center — ขาดทั้งหมด

### AGENTS.md กำหนดไว้ (Section 4.2, 6, 7.3):

> "WORK Command Center เป็นหน้าคุม pipeline ของงานทั้งหมด"

ต้องเป็น **ศูนย์ควบคุมแจกจ่ายงาน** ที่ประกอบด้วย 3 ส่วน:

### A. Recording Plan / Casting Plan ❌ ไม่มี
AGENTS.md ต้องการ:
- คิวงานถ่าย / งานอัด / งาน creator / งาน casting
- game, map, schedule, planned duration
- assign creators / talent
- recording status (draft → scheduled → shot → cancelled)

**สถานะ: ไม่มี Model, Migration, Controller, View ใดๆ เลย**

### B. Resource Library ❌ ไม่มี
AGENTS.md ต้องการ:
- footage code, clip metadata
- raw length, usable length
- ready for edit state
- usage count
- status (raw → uploaded → ready_for_edit → in_use → archived)

**สถานะ: ไม่มี Model, Migration, Controller, View ใดๆ เลย**

### C. Edit Plan ❌ ไม่มี
AGENTS.md ต้องการ:
- resource → edit job
- assign editor
- due date, finished date
- status (pending_resource → assigned → editing → submitted → approved → done)
- output summary

**สถานะ: ไม่มี Model, Migration, Controller, View ใดๆ เลย**

### D. WORK Page View ❌ ไม่มี
AGENTS.md ต้องการหน้ารวมที่แสดง:
- top: Recording Queue / Casting Plan
- middle: Resource Library / resource tasks
- lower: Edit Plan
- summary: total job / workload / completion

**สิ่งที่มีตอนนี้:** เฉพาะ "จัดการ Work" (`settings/works`) ซึ่งเป็นแค่ หน้าตั้งค่า Work Template + Assignment  
**ไม่ใช่** WORK Command Center ตามแผน

### สิ่งที่ต้องสร้าง:
- [ ] Model: `RecordingJob`
- [ ] Model: `RecordingJobAssignee` (หรือ assignee field)
- [ ] Model: `MediaResource`
- [ ] Model: `EditJob`
- [ ] Model: `EditJobAssignee` (หรือ assignee field)
- [ ] Model: `ApprovedWorkOutput`
- [ ] Migrations สำหรับ 4-6 tables
- [ ] Controller: `WorkCommandController`
- [ ] Views: work/index, work/recording, work/resources, work/editing
- [ ] Routes: /work, /work/recording, /work/resources, /work/editing
- [ ] Nav link: เพิ่มเมนู "WORK" ใน layout

---

## 3) Payroll Modes ที่ยังไม่มี

### AGENTS.md กำหนด 9 modes (Section 5.2):

| Mode | สถานะ | หมายเหตุ |
|------|--------|----------|
| `monthly_staff` | ✅ ทำงานได้ | MonthlyStaffCalculator |
| `freelance_layer` | ✅ ทำงานได้ | FreelanceLayerCalculator |
| `freelance_fixed` | ✅ ทำงานได้ | FreelanceFixedCalculator |
| `youtuber_salary` | ✅ ทำงานได้ | YoutuberSalaryCalculator |
| `youtuber_settlement` | ✅ ทำงานได้ | YoutuberSettlementCalculator |
| `custom_hybrid` | ⚠️ มีใน UI แต่ไม่มี Calculator | ตกไปที่ default ใน match() |
| **`creator_monthly`** | ❌ ไม่มี | ไม่มี Calculator / UI |
| **`creator_per_clip`** | ❌ ไม่มี | ไม่มี Calculator / UI |
| **`editor_monthly`** | ❌ ไม่มี | ไม่มี Calculator / UI |
| **`freelance_minute`** | ❌ ไม่มี | อาจซ้อนกับ freelance_layer? |
| **`freelance_project`** | ❌ ไม่มี | ไม่มี Calculator / UI |

### สิ่งที่ต้องทำ:
- [ ] ตัดสินใจ: mode ไหน map กับ calculator ที่มีอยู่ได้ (เช่น `creator_monthly` → MonthlyStaff)
- [ ] สร้าง Calculator ใหม่สำหรับ `creator_per_clip` (amount = approved_clip_count × rate_per_clip)
- [ ] สร้าง Calculator ใหม่สำหรับ `freelance_project` (project-based amount)
- [ ] เพิ่ม modes ใหม่ใน PayrollCalculationService match()
- [ ] เพิ่ม modes ใหม่ใน validation rules ทุก controller
- [ ] เพิ่ม modes ใหม่ใน UI dropdowns
- [ ] สร้าง workspace grid views สำหรับ modes ใหม่
- [ ] ตัดสินใจเรื่อง `custom_hybrid` — ทำจริงหรือถอดออก

---

## 4) Authentication & RBAC

### AGENTS.md กำหนด (Section 7.1, 2.1):

> "ขั้นต่ำ: login, logout"
> "current phase ยังไม่ต้องบังคับ RBAC ลึก แต่ architecture ต้องเผื่อ policy / middleware ไว้"

### สถานะปัจจุบัน:

| รายการ | สถานะ |
|--------|--------|
| Login page / route | ❌ ไม่มี |
| Logout route | ❌ ไม่มี |
| Auth middleware บน routes | ❌ ไม่มี — ทุก route เปิดเข้าถึงได้เลย |
| `roles` table | ✅ มี migration + model |
| `permissions` table | ✅ มี migration + model |
| `role_user` pivot | ✅ มี migration |
| Auth gate/policy logic | ❌ EmployeePolicy เป็น stub |
| Route protection | ❌ ไม่มี middleware ป้องกันใดๆ |

### สิ่งที่ต้องทำ (ขั้นต่ำ):
- [ ] สร้าง login / logout routes + controller
- [ ] สร้าง login view (Blade)
- [ ] ครอบ auth middleware บนทุก route (ยกเว้น login)
- [ ] Seed admin user
- [ ] แสดงชื่อ user จริงใน nav (ตอนนี้แสดง fallback 'Admin')

---

## 5) Company Finance & P&L — Placeholder เท่านั้น

### AGENTS.md กำหนด (Section 7.10):

> "revenue, expenses, subscriptions, P&L, cumulative / monthly rollup"

### สถานะปัจจุบัน:

| รายการ | สถานะ |
|--------|--------|
| Company expenses page | ⚠️ Placeholder — แสดงข้อความ "ยังไม่มีรายการ" |
| CompanyFinanceController | ⚠️ มีแค่ method `expenses()` ที่ return empty array |
| CompanyExpense model | ✅ มี |
| CompanyRevenue model | ✅ มี |
| SubscriptionCost model | ✅ มี |
| CRUD สำหรับ expenses | ❌ ไม่มี route/controller |
| CRUD สำหรับ revenues | ❌ ไม่มี route/controller |
| CRUD สำหรับ subscriptions | ❌ ไม่มี route/controller |
| P&L summary view | ❌ ไม่มี |
| Monthly rollup | ❌ ไม่มี |
| Quarterly view | ❌ ไม่มี |
| Nav link "ค่าใช้จ่ายบริษัท" | ✅ มี แต่ไปหน้า placeholder |

### สิ่งที่ต้องทำ:
- [ ] สร้าง CRUD routes/methods สำหรับ CompanyExpense
- [ ] สร้าง CRUD routes/methods สำหรับ CompanyRevenue
- [ ] สร้าง CRUD routes/methods สำหรับ SubscriptionCost
- [ ] สร้าง view: company/finance.blade.php (P&L summary)
- [ ] คำนวณ: revenue − expenses − subscriptions = profit/loss
- [ ] แสดง monthly / cumulative / quarterly rollup
- [ ] เชื่อม payroll totals เข้ามาเป็นส่วนหนึ่งของ expenses

---

## 6) Annual Summary — ยังไม่มีเลย

### AGENTS.md กำหนด (Section 7.9):

> "12-month totals, employee annual totals, export"

### สถานะปัจจุบัน:

| รายการ | สถานะ |
|--------|--------|
| Annual summary route | ❌ ไม่มี |
| Annual summary controller | ❌ ไม่มี |
| Annual summary view | ❌ ไม่มี |
| 12-month payroll view per employee | ❌ ไม่มี |
| Annual totals calculation | ❌ ไม่มี |
| Export (PDF/Excel) | ❌ ไม่มี |
| Nav link | ❌ ไม่มี |

### หมายเหตุ:
PayslipController มี method `buildYearToDateSummary()` และ `buildMonthlyStats()` ซึ่งอาจเป็น foundation ได้ แต่ยังไม่มีหน้า dedicated annual summary

### สิ่งที่ต้องทำ:
- [ ] สร้าง AnnualSummaryController
- [ ] สร้าง route: /annual-summary/{employee}/{year}
- [ ] สร้าง view: annual-summary/show.blade.php
- [ ] แสดง 12 เดือนในตารางเดียว (income, deduction, net per month)
- [ ] รวม yearly totals
- [ ] Export PDF / Excel
- [ ] เพิ่ม link ใน nav หรือใน workspace

---

## 7) Tests — แทบไม่มี

### AGENTS.md กำหนด (Section 18):

> ขั้นต่ำ: payroll calculation tests, SSO tests, attendance rule tests, OT calculation tests, creator/editor output calculation tests, payslip snapshot tests, audit logging tests, migration fresh-run test

### สถานะปัจจุบัน:

| Test | สถานะ |
|------|--------|
| Payroll calculation tests | ❌ ไม่มี |
| SSO calculation tests | ❌ ไม่มี |
| Attendance rule tests | ❌ ไม่มี |
| OT calculation tests | ❌ ไม่มี |
| Creator/editor output tests | ❌ ไม่มี |
| Payslip snapshot tests | ❌ ไม่มี |
| Audit logging tests | ❌ ไม่มี |
| Migration fresh-run test | ❌ ไม่มี |
| `php artisan test` ผ่าน? | ❌ Exit code 1 (ล่าสุด) |

**มีเพียง 2 placeholder tests:**
- `test_that_true_is_true()` — trivial
- `test_the_application_returns_a_successful_response()` — basic HTTP

### สิ่งที่ต้องทำ (เรียงตามความสำคัญ):
- [ ] แก้ให้ `php artisan test` ผ่านก่อน
- [ ] Unit Test: MonthlyStaffCalculator (base, OT, diligence, LWOP, late, SSO)
- [ ] Unit Test: FreelanceLayerCalculator (rate resolution, duration calc)
- [ ] Unit Test: FreelanceFixedCalculator (qty × rate)
- [ ] Unit Test: YoutuberSalaryCalculator (delegates + info items)
- [ ] Unit Test: YoutuberSettlementCalculator (income vs deduction)
- [ ] Unit Test: SocialSecurityService (effective date, cap, rate)
- [ ] Unit Test: PayrollCalculationService (dispatcher, claims merge)
- [ ] Feature Test: Payslip finalize → snapshot integrity
- [ ] Feature Test: Attendance save → payroll recalc
- [ ] Feature Test: Audit log creation on key changes
- [ ] Test: `php artisan migrate:fresh` succeeds

---

## 8) WORK Pipeline Tables ที่ขาด

### AGENTS.md กำหนด (Section 8.2):

| Table | สถานะ | หมายเหตุ |
|-------|--------|----------|
| `recording_jobs` | ❌ ไม่มี | คิวงานถ่าย |
| `recording_job_assignees` | ❌ ไม่มี | ผู้รับผิดชอบงานถ่าย |
| `media_resources` | ❌ ไม่มี | คลังทรัพยากร footage/clip |
| `edit_jobs` | ❌ ไม่มี | คิวงานตัด |
| `edit_job_assignees` | ❌ ไม่มี | ผู้รับผิดชอบงานตัด |
| `approved_work_outputs` | ❌ ไม่มี | ผลงานที่ payroll ใช้จ่ายเงินได้ |

### ตารางที่คล้ายแต่ไม่ตรง:
- `work_log_types` — เป็น template, ไม่ใช่ job queue
- `work_assignments` — เป็น assignment tracking, **ใกล้เคียงที่สุด** แต่ไม่มี recording/resource/edit separation
- `performance_records` — เป็น performance metrics, ไม่ใช่ job pipeline

### Critical Flow ที่ขาด (AGENTS.md 6.3):
```
Recording Job → Media Resource → Edit Job 1 / Edit Job 2 / Edit Job 3
```
**ยังไม่มี flow นี้ในระบบเลย** — ระบบปัจจุบันเก็บงานเป็น flat work_logs

---

## 9) Workspace — สิ่งที่ยังขาด

### ตาม AGENTS.md Section 10:

| รายการ | สถานะ |
|--------|--------|
| Header + month selector | ✅ |
| Summary cards | ✅ |
| Attendance grid | ✅ |
| Payroll summary | ✅ |
| Claim section | ✅ |
| Module toggles | ✅ |
| Payment proof upload | ✅ |
| **Monthly Queue Cards** | ❌ ไม่มี |
| **Recording/Casting Queue Card** | ❌ ไม่มี |
| **Editing/Resource Queue Card** | ❌ ไม่มี |
| **Link ไป Casting Plan** | ❌ ไม่มี (ยังไม่มีหน้า Casting Plan) |
| **Link ไป Edit Plan** | ❌ ไม่มี (ยังไม่มีหน้า Edit Plan) |
| Field state indicator | ⚠️ บางส่วน — source_flag ยังไม่แสดงชัดใน UI |
| Detail Inspector (click row) | ❌ ไม่มี |
| Audit timeline per row | ❌ ไม่มี |

### Workspace Grid Issues:
- `custom_hybrid` mode ไม่มี dedicated grid view → ใช้ attendance grid ตก default
- PerformanceRecords section → ตาม AGENTS.md ควรเปลี่ยนชื่อเป็น "Casting Plan" / "Edit Plan"

### สิ่งที่ต้องทำ:
- [ ] เพิ่ม Monthly Queue Cards (recording queue, edit queue summary)
- [ ] เพิ่ม Detail Inspector sidebar (click row → show source, formula, audit)
- [ ] แสดง field state badges ชัดเจน (auto/manual/override/locked)
- [ ] เปลี่ยน "Performance Records" → ชื่อที่สื่อ operation (Casting Plan / Edit Plan)
- [ ] รอ WORK pipeline สร้างเสร็จก่อนจึงเชื่อม queue cards

---

## 10) Payslip — สิ่งที่ยังขาด

| รายการ | สถานะ |
|--------|--------|
| Preview | ✅ |
| Finalize (snapshot) | ✅ |
| Unfinalize | ✅ |
| PDF export | ✅ |
| Thai font support | ✅ (Sarabun) |
| Company header in PDF | ✅ |
| Signature fields | ✅ |
| YTD summary in payslip | ⚠️ มี method แต่ไม่ชัดว่าแสดงใน view |
| **Batch payslip generation** | ❌ ไม่มี — ทำทีละคน |
| **Payslip list / history page** | ❌ ไม่มี — ต้องเข้าผ่าน workspace |
| **Email payslip** | ❌ ไม่มี |
| **Regenerate from snapshot only by permission** | ⚠️ ไม่มี permission check |

### สิ่งที่ต้องทำ:
- [ ] สร้าง Payslip list page (all employees, filter by month)  
- [ ] Batch finalize / batch PDF export
- [ ] Permission-based unfinalize (ตอนนี้ใครก็ unfinalize ได้)

---

## 11) Audit — จุดอ่อน

### สถานะปัจจุบัน:

| รายการ | สถานะ |
|--------|--------|
| AuditLogService | ✅ มี |
| Log creation | ✅ ทำงาน |
| Audit log view/page | ❌ ไม่มี — ไม่มีที่ดู audit logs ใน UI |
| Legacy calling convention bug | ⚠️ field mapping ไม่ตรง |
| Employee status change audit | ⚠️ บางส่วน |
| Salary profile change audit | ❌ ไม่แน่ใจ |
| Payroll item override audit | ✅ มี |
| Payslip finalize audit | ✅ มี |
| Rule change audit | ❌ ไม่มี |
| Module toggle audit | ✅ มี |
| SSO config change audit | ❌ ไม่มี |
| Work assignment change audit | ❌ ไม่มี |

### AuditLogService Bug:
WorkspaceController ใช้ legacy calling convention:
```php
AuditLogService::log($userId, $action, $auditableType, $auditableId, $oldValue, $newValue)
```
ซึ่ง field mapping ไม่ตรงกับ parameter names — `$action` ไปลงใน `field` column

### สิ่งที่ต้องทำ:
- [ ] แก้ AuditLogService legacy mapping bug
- [ ] สร้าง Audit Log viewer page (filter by entity, action, date)
- [ ] เพิ่ม audit coverage: rule changes, SSO config, salary profile
- [ ] Standardize ทุก caller ให้ใช้ model-based calling convention

---

## 12) Code Quality & Architecture Gaps

### ตาม AGENTS.md Section 14-16:

| รายการ | สถานะ |
|--------|--------|
| Controllers บาง | ⚠️ WorkspaceController ใหญ่มาก (~500+ lines) |
| Business logic ใน Service | ✅ ส่วนใหญ่ |
| FormRequest validation | ❌ ไม่มี — validate ใน controller ทั้งหมด |
| Transaction สำหรับ critical ops | ⚠️ บางส่วน |
| Enum-like classes | ❌ ไม่มี — hardcode arrays ใน controllers |
| Custom config files | ❌ ไม่มี |
| Middleware | ❌ ไม่มี custom middleware |
| Custom Enums directory | ❌ ไม่มี |
| Actions directory | ❌ ไม่มี |
| N+1 query prevention | ⚠️ ไม่แน่ใจ |

### สิ่งที่ต้องทำ:
- [ ] Extract WorkspaceController → แยก method groups ออกเป็น dedicated controllers หรือ Actions
- [ ] สร้าง FormRequest classes สำหรับ complex validations
- [ ] สร้าง PayrollMode enum class
- [ ] ย้าย hardcoded arrays (payroll modes, labels) ไปเป็น config/enum
- [ ] ครอบ DB::transaction() ใน critical operations ทั้งหมด

---

## 13) Anti-Patterns ที่พบ

### จาก AGENTS.md Section 17 — ตรวจพบ:

| Anti-Pattern | พบหรือไม่ | รายละเอียด |
|-------------|-----------|-----------|
| ใช้ notes field เป็น business logic | ⚠️ **พบ** | YoutuberSettlementCalculator ใช้ `notes='deduction'` แยก income/deduction |
| Hardcode legal values | ⚠️ **พบบางส่วน** | HolidayService hardcode Thai holidays 2026 |
| ให้ report page เป็น source of truth | ✅ ไม่พบ | |
| คำนวณหลักใน Blade | ✅ ไม่พบ | อยู่ใน Service |
| query DB ใน PDF Blade | ✅ ไม่พบ | ใช้ snapshot |
| เก็บ assignee เป็น comma-separated | ✅ ไม่พบ | |
| ทำ RBAC ก่อน data flow นิ่ง | ✅ ไม่พบ | ยังไม่ทำ RBAC |
| เปิด edit-all แต่ไม่ทำ audit | ⚠️ **พบบางจุด** | Rule changes ไม่มี audit |

### สิ่งที่ต้องแก้:
- [ ] YoutuberSettlementCalculator: เลิกใช้ `notes` field → ใช้ dedicated field เช่น `entry_type`
- [ ] HolidayService: ดึงจาก external source หรือ database แทน hardcode
- [ ] เพิ่ม audit ให้ rules/settings changes

---

## 14) Definition of Done Checklist

### ตาม AGENTS.md Section 20:

| เงื่อนไข | สถานะ | หมายเหตุ |
|---------|--------|----------|
| เพิ่มพนักงานใหม่ได้ | ✅ | |
| ตั้ง payroll mode ได้ | ✅ | |
| WORK page คุม recording / resource / edit pipeline ได้ | ❌ | **ยังไม่มี WORK page** |
| Workspace ใช้งานรายเดือนได้จริง | ✅ | ใช้ได้แต่ยังขาด queue cards |
| attendance ใช้ได้จริง | ✅ | |
| claim / advance ใช้ได้จริง | ✅ | |
| คำนวณ payroll ตาม mode หลักได้ | ✅ | 5/9 modes |
| SSO config ตามเวลาได้ | ✅ | effective_date based |
| payslip finalize / PDF ได้ | ✅ | |
| annual summary / finance summary | ❌ | **ทั้งสองยังไม่มี** |
| audit log เชื่อถือได้ | ⚠️ | มี bug + ไม่มี viewer |
| architecture พร้อม RBAC | ⚠️ | schema มี แต่ไม่มี implementation |

**ผ่าน: 7/12 ข้อ, ผ่านบางส่วน: 2/12, ไม่ผ่าน: 3/12**

---

## 15) Action Plan แนะนำ

### Phase 1: แก้ไขสิ่งที่มี + Foundation (สำคัญสุด)

| # | งาน | ความสำคัญ | เหตุผล |
|---|------|----------|--------|
| 1.1 | แก้ `php artisan test` ให้ผ่าน | 🔴 Critical | Test infrastructure ต้องใช้งานได้ |
| 1.2 | แก้ AuditLog legacy bug | 🔴 Critical | ข้อมูล audit ผิดตอนนี้ |
| 1.3 | แก้ YoutuberSettlement anti-pattern (notes field) | 🟡 High | Anti-pattern ตาม AGENTS.md |
| 1.4 | เพิ่ม Auth login/logout + middleware | 🟡 High | ระบบเปิดเข้าถึงได้โดยไม่ต้อง login |
| 1.5 | เขียน Unit Tests สำหรับ 5 Calculators | 🟡 High | ป้องกัน regression |

### Phase 2: สร้างสิ่งที่ขาด — Finance & Reporting

| # | งาน | ความสำคัญ | เหตุผล |
|---|------|----------|--------|
| 2.1 | สร้าง Company Finance CRUD + P&L page | 🟡 High | Placeholder อยู่นาน |
| 2.2 | สร้าง Annual Summary page | 🟡 High | AGENTS.md DoD requirement |
| 2.3 | สร้าง Audit Log viewer page | 🟡 High | มี log แต่ดูไม่ได้ |
| 2.4 | สร้าง Payslip list / batch page | 🟢 Medium | Convenience feature |

### Phase 3: WORK Command Center (ใหญ่สุด)

| # | งาน | ความสำคัญ | เหตุผล |
|---|------|----------|--------|
| 3.1 | ออกแบบ schema: recording_jobs, media_resources, edit_jobs | 🟡 High | Core of AGENTS.md vision |
| 3.2 | สร้าง migrations + models + relationships | 🟡 High | |
| 3.3 | สร้าง WorkCommandController | 🟡 High | |
| 3.4 | สร้าง WORK page views (3 sections) | 🟡 High | |
| 3.5 | สร้าง ApprovedWorkOutput model + flow | 🟡 High | Bridge ระหว่าง work → payroll |
| 3.6 | เชื่อม Workspace queue cards | 🟢 Medium | |

### Phase 4: เพิ่ม Payroll Modes + Polish

| # | งาน | ความสำคัญ | เหตุผล |
|---|------|----------|--------|
| 4.1 | ตัดสินใจ mode mapping (creator_monthly, editor_monthly, etc.) | 🟢 Medium | |
| 4.2 | สร้าง CreatorPerClipCalculator | 🟢 Medium | |
| 4.3 | สร้าง FreelanceProjectCalculator | 🟢 Medium | |
| 4.4 | ตัดสินใจ custom_hybrid — ทำจริงหรือถอด | 🟢 Medium | |
| 4.5 | สร้าง FormRequest classes | 🟢 Medium | |
| 4.6 | Extract WorkspaceController → smaller controllers | 🟢 Medium | |
| 4.7 | สร้าง PayrollMode enum class | 🟢 Medium | |
| 4.8 | Workspace Detail Inspector | 🟢 Medium | |

---

## สรุปตัวเลข

| หมวด | จำนวนงานที่ต้องทำ |
|------|------------------|
| WORK Command Center | ~15 items (models, migrations, controllers, views) |
| Payroll Modes ใหม่ | ~8 items |
| Company Finance | ~6 items |
| Annual Summary | ~6 items |
| Authentication | ~5 items |
| Tests | ~12 items |
| Audit fixes | ~4 items |
| Code quality | ~5 items |
| Anti-pattern fixes | ~3 items |
| **รวม** | **~64 items** |

---

*สร้างโดย: Gap Analysis Agent*  
*อ้างอิง: AGENTS.md (updated version)*
