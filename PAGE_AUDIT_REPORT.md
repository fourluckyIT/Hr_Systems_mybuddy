# PAGE-BY-PAGE UI AUDIT REPORT
## xHR Payroll & Finance System — รายงานตรวจสอบทุกหน้า ปุ่ม เส้นทาง และฐานข้อมูล

> วันที่ตรวจสอบ: 6 เมษายน 2026  
> ตรวจจาก: Source code (Blade templates, routes/web.php, Controllers, Models)

---

## สารบัญ

1. [Navigation Bar (Global)](#1-navigation-bar-global)
2. [หน้า Employee Board](#2-หน้า-employee-board)
3. [หน้า Create Employee](#3-หน้า-create-employee)
4. [หน้า Edit Employee](#4-หน้า-edit-employee)
5. [หน้า Workspace (Main)](#5-หน้า-workspace-main)
6. [Workspace Partial: Attendance Grid](#6-workspace-partial-attendance-grid)
7. [Workspace Partial: Freelance Layer Grid](#7-workspace-partial-freelance-layer-grid)
8. [Workspace Partial: Freelance Fixed Grid](#8-workspace-partial-freelance-fixed-grid)
9. [Workspace Partial: YouTuber Salary Grid](#9-workspace-partial-youtuber-salary-grid)
10. [Workspace Partial: YouTuber Settlement Grid](#10-workspace-partial-youtuber-settlement-grid)
11. [Workspace Partial: Claims Grid](#11-workspace-partial-claims-grid)
12. [Workspace Partial: Performance Records](#12-workspace-partial-performance-records)
13. [หน้า Payslip Preview](#13-หน้า-payslip-preview)
14. [หน้า Payslip PDF](#14-หน้า-payslip-pdf)
15. [หน้า Settings — Rules](#15-หน้า-settings--rules)
16. [หน้า Settings — Company](#16-หน้า-settings--company)
17. [หน้า Settings — Master Data](#17-หน้า-settings--master-data)
18. [หน้า Work Manager](#18-หน้า-work-manager)
19. [หน้า Calendar](#19-หน้า-calendar)
20. [หน้า Company Expenses](#20-หน้า-company-expenses)
21. [Global Keyboard Navigation (grid-navigation.blade.php)](#21-global-keyboard-navigation)

---

## 1. Navigation Bar (Global)

**ไฟล์:** `resources/views/layouts/app.blade.php`

| # | ข้อความ/ปุ่ม | Link / Route | Controller Method |
|---|---|---|---|
| 1 | **xHR Payroll** (logo) | `route('employees.index')` → `/employees` | `EmployeeController@index` |
| 2 | พนักงาน | `route('employees.index')` → `/employees` | `EmployeeController@index` |
| 3 | ปฏิทินหลัก | `route('calendar.index')` → `/calendar` | `CalendarController@index` |
| 4 | ค่าใช้จ่ายบริษัท | `route('company.expenses')` → `/company/expenses` | `CompanyFinanceController@expenses` |
| 5 | จัดการ Work | `route('settings.works.index')` → `/settings/works` | `WorkManagerController@index` |
| 6 | Master Data | `route('settings.master-data')` → `/settings/master-data` | `MasterDataController@index` |
| 7 | ตั้งค่า | `route('settings.rules')` → `/settings/rules` | `SettingsController@rules` |

**Auth แสดง:** `auth()->user()?->name ?? 'Admin'` (ไม่มีปุ่ม logout ใน nav)

---

## 2. หน้า Employee Board

**ไฟล์:** `resources/views/employees/index.blade.php`  
**URL:** `/employees`  
**Route Name:** `employees.index`  
**Controller:** `EmployeeController@index`  
**HTTP Method:** `GET`

### DB Tables ที่ใช้

| Table | การใช้งาน | PK | FK ที่เกี่ยวข้อง |
|---|---|---|---|
| `employees` | ดึงรายชื่อพนักงาน | `id` | `department_id` → `departments.id` |
| `departments` | Filter dropdown + แสดงชื่อแผนก | `id` | — |
| `employee_salary_profiles` | แสดง base_salary | `id` | `employee_id` → `employees.id` |
| `positions` | แสดงตำแหน่ง | `id` | FK via `employees.position_id` |

### ปุ่มและ Actions ทั้งหมด

| # | ปุ่ม/Action | ประเภท | Target Route | HTTP Method | ผลลัพธ์ |
|---|---|---|---|---|---|
| 1 | **"+ เพิ่มพนักงาน"** | Button (Alpine modal toggle) | เปิด modal form | — | เปิด form เพิ่มพนักงานบนหน้าเดิม |
| 2 | **Search input** | Form GET | `route('employees.index')` | GET | filter `?search=xxx` |
| 3 | **Payroll Mode dropdown** | Form GET | `route('employees.index')` | GET | filter `?payroll_mode=xxx` |
| 4 | **Department dropdown** | Form GET | `route('employees.index')` | GET | filter `?department=xxx` |
| 5 | **"แสดงพนักงานที่ปิดสถานะ" checkbox** | Form GET | `route('employees.index')` | GET | filter `?show_inactive=1` |
| 6 | **Employee Card (คลิกทั้ง card)** | Link `<a>` | `route('workspace.show', [id, month, year])` | GET | ไปหน้า Workspace |
| 7 | **"EDIT" (บน card)** | Link `<a>` | `route('employees.edit', id)` | GET | ไปหน้า Edit Employee |
| 8 | **"เปิดสถานะ" / "ปิดสถานะ"** | Form (PATCH) | `route('employees.toggle-status', id)` | PATCH | toggle `is_active` |
| 9 | **"+" (Dashed card)** | Button (Alpine) | เปิด Add Employee modal | — | เปิด form เดียวกับปุ่ม 1 |
| 10 | **Modal Form Submit ("บันทึก")** | Form POST | `route('employees.store')` | POST | สร้างพนักงานใหม่ |

### Modal Form Fields

| Field | Name Attribute | Type | Required | DB Column |
|---|---|---|---|---|
| ชื่อ | `first_name` | text | ✅ | `employees.first_name` |
| นามสกุล | `last_name` | text | ✅ | `employees.last_name` |
| ชื่อเล่น | `nickname` | text | ❌ | `employees.nickname` |
| รหัสพนักงาน | `employee_code` | text | ❌ | `employees.employee_code` |
| Payroll Mode | `payroll_mode` | select | ✅ | `employees.payroll_mode` |
| แผนก | `department_id` | select | ❌ | `employees.department_id` |
| เงินเดือน | `base_salary` | number | ❌ | `employee_salary_profiles.base_salary` |
| วันเริ่มงาน | `start_date` | date | ❌ | `employees.start_date` |
| ธนาคาร | `bank_name` | text | ❌ | `employee_bank_accounts.bank_name` |
| เลขที่บัญชี | `account_number` | text | ❌ | `employee_bank_accounts.account_number` |

---

## 3. หน้า Create Employee

**ไฟล์:** `resources/views/employees/create.blade.php`  
**URL:** `/employees/create`  
**Route Name:** `employees.create`  
**Controller:** `EmployeeController@create`

### DB Tables ที่ใช้

| Table | PK | FK |
|---|---|---|
| `departments` | `id` | — |
| `positions` | `id` | `department_id` |

### ปุ่ม

| # | ปุ่ม | Target Route | HTTP Method |
|---|---|---|---|
| 1 | **"กลับไปหน้ารายชื่อ"** | `route('employees.index')` | GET (link) |
| 2 | **"ยกเลิก"** | `route('employees.index')` | GET (link) |
| 3 | **"บันทึกข้อมูลพนักงาน"** | `route('employees.store')` | POST |

### Form Fields

| Section | Field | Name | Type | DB Table.Column |
|---|---|---|---|---|
| ส่วนตัว | ชื่อ | `first_name` | text | `employees.first_name` |
| ส่วนตัว | นามสกุล | `last_name` | text | `employees.last_name` |
| ส่วนตัว | ชื่อเล่น | `nickname` | text | `employees.nickname` |
| ส่วนตัว | รหัสพนักงาน | `employee_code` | text | `employees.employee_code` |
| ข้อมูลงาน | payroll_mode | `payroll_mode` | select | `employees.payroll_mode` |
| ข้อมูลงาน | แผนก | `department_id` | select | `employees.department_id` |
| ข้อมูลงาน | ตำแหน่ง | `position_id` | select | `employees.position_id` |
| ข้อมูลงาน | เงินเดือน | `base_salary` | number | `employee_salary_profiles.base_salary` |
| ข้อมูลงาน | วันเริ่มงาน | `start_date` | date | `employees.start_date` |
| ธนาคาร | ชื่อธนาคาร | `bank_name` | text | `employee_bank_accounts.bank_name` |
| ธนาคาร | เลขที่บัญชี | `account_number` | text | `employee_bank_accounts.account_number` |

---

## 4. หน้า Edit Employee

**ไฟล์:** `resources/views/employees/edit.blade.php`  
**URL:** `/employees/{id}/edit`  
**Route Name:** `employees.edit`  
**Controller:** `EmployeeController@edit`

### DB Tables ที่ใช้

| Table | PK | FK |
|---|---|---|
| `employees` | `id` | — |
| `employee_salary_profiles` | `id` | `employee_id` |
| `employee_bank_accounts` | `id` | `employee_id` |
| `departments` | `id` | — |
| `positions` | `id` | `department_id` |

### ปุ่ม

| # | ปุ่ม | Target Route | HTTP Method |
|---|---|---|---|
| 1 | **"กลับไปหน้า Employee Board"** | `route('employees.index')` | GET (link) |
| 2 | **"ยกเลิก"** | `route('employees.index')` | GET (link) |
| 3 | **"บันทึกการแก้ไข"** | `route('employees.update', id)` | PUT |

### Form Fields — เหมือน Create แต่ pre-filled ด้วยข้อมูลเดิม

---

## 5. หน้า Workspace (Main)

**ไฟล์:** `resources/views/workspace/show.blade.php`  
**URL:** `/workspace/{employee}/{month}/{year}`  
**Route Name:** `workspace.show`  
**Controller:** `WorkspaceController@show`

### DB Tables ที่ใช้ (ในหน้าหลัก)

| Table | การใช้งาน | PK | FK |
|---|---|---|---|
| `employees` | Header ข้อมูลพนักงาน | `id` | `department_id`, `position_id` |
| `employee_salary_profiles` | base_salary | `id` | `employee_id` |
| `positions` | แสดงตำแหน่ง | `id` | — |
| `payroll_batches` | result snapshot | `id` | `employee_id` |
| `payroll_items` | รายการเงินเดือน (right panel) | `id` | `payroll_batch_id`, `payroll_item_type_id` |
| `payroll_item_types` | label/code ของรายการ | `id` | — |
| `module_toggles` | เช็คว่าเปิด SSO หรือไม่ | `id` | `employee_id` |
| `attendance_logs` | ตาราง attendance | `id` | `employee_id` |
| `work_logs` | ตาราง work logs | `id` | `employee_id` |
| `expense_claims` | ตาราง claims | `id` | `employee_id` |
| `performance_records` | ตาราง performance | `id` | `employee_id` |
| `work_assignments` | assigned works | `id` | `employee_id`, `work_log_type_id` |
| `attendance_rules` | สำหรับ meta (เวลาเข้างาน) | `id` | — |
| `layer_rate_rules` | เรทราคาเลเยอร์ | `id` | `employee_id` |

### ปุ่มและ Actions บนหน้าหลัก (show.blade.php)

| # | ปุ่ม/Action | Target Route | HTTP | ผลลัพธ์ |
|---|---|---|---|---|
| 1 | **← (กลับ)** | `route('employees.index')` | GET | กลับ Employee Board |
| 2 | **เดือนก่อน (←)** | `route('workspace.show', [id, prevMonth, prevYear])` | GET | เปลี่ยนเดือน |
| 3 | **เดือนถัดไป (→)** | `route('workspace.show', [id, nextMonth, nextYear])` | GET | เปลี่ยนเดือน |
| 4 | **"Slip"** | `route('payslip.preview', [id, month, year])` | GET | ไปหน้า Payslip Preview |
| 5 | **"ประกันสังคม" toggle** | `route('workspace.module.toggle', id)` | POST | toggle SSO module |
| 6 | **"คำนวณใหม่"** | `route('workspace.recalculate', [id, month, year])` | POST | recalculate payroll |
| 7 | **Inline amount edit (income items)** | `route('workspace.payroll.update', [id, month, year])` | PATCH | update payroll item amount |
| 8 | **Inline amount edit (deduction items)** | `route('workspace.payroll.update', [id, month, year])` | PATCH | update payroll item amount |

### Layout

```
┌──────────────────────────────────────────────────────┐
│ Header: ← | ชื่อ | ตำแหน่ง | payroll_mode | SSO btn │
│                              Month Selector | Slip   │
├──────────────────────────────────────────────────────┤
│ [Summary Cards] รายรับ | รายหัก | รายได้สุทธิ         │
├────────────────────────┬─────────────────────────────┤
│ Mode-specific grid     │ สรุปเงินเดือน (Right Panel) │
│ (2/3 width)            │ - income items (editable)   │
│                        │ - deduction items (editable)│
│                        │ - net pay                   │
│                        ├─────────────────────────────┤
│                        │ [คำนวณใหม่] button          │
│ Performance Records    ├─────────────────────────────┤
│                        │ Claims Grid                 │
└────────────────────────┴─────────────────────────────┘
```

### Mode Dispatch Logic (Blade @include)
- `monthly_staff` → `attendance-grid`
- `youtuber_salary` → `youtuber-salary-grid`
- `freelance_layer` → `freelance-layer-grid`
- `freelance_fixed` → `freelance-fixed-grid`
- `youtuber_settlement` → `youtuber-settlement-grid`

---

## 6. Workspace Partial: Attendance Grid

**ไฟล์:** `resources/views/workspace/partials/attendance-grid.blade.php`  
**ใช้กับ Mode:** `monthly_staff`, `youtuber_salary` (ผ่านหลัก attendance-grid)

### DB Tables

| Table | PK | FK |
|---|---|---|
| `attendance_logs` | `id` | `employee_id` |
| `attendance_rules` | `id` | — (ใช้ config JSON) |

### ปุ่มและ Actions

| # | ปุ่ม/Action | Target Route | HTTP | ผลลัพธ์ |
|---|---|---|---|---|
| 1 | **Day type dropdown (onchange submit)** | `route('workspace.saveAttendance', [id, month, year])` | POST | เปลี่ยน day_type → auto submit form |
| 2 | **"บันทึกข้อมูลเข้างาน"** | `route('workspace.saveAttendance', [id, month, year])` | POST | บันทึก attendance ทั้งหมด |

### Form Fields (per row = 1 วัน)

| Field | Name Pattern | Type | DB Column |
|---|---|---|---|
| ประเภทวัน | `attendance[{id}][day_type]` | select | `attendance_logs.day_type` |
| เวลาเข้า | `attendance[{id}][check_in]` | time | `attendance_logs.check_in` |
| เวลาออก | `attendance[{id}][check_out]` | time | `attendance_logs.check_out` |
| สาย(นาที) | `attendance[{id}][late_minutes]` | number | `attendance_logs.late_minutes` |
| OT(นาที) | `attendance[{id}][ot_minutes]` | number | `attendance_logs.ot_minutes` |
| OT checkbox | `attendance[{id}][ot_enabled]` | checkbox | `attendance_logs.ot_enabled` |

### JavaScript
- Auto-calculate late minutes = check_in - target_check_in
- Auto-calculate OT = (check_out - check_in) - target_minutes_per_day (if OT enabled)

---

## 7. Workspace Partial: Freelance Layer Grid

**ไฟล์:** `resources/views/workspace/partials/freelance-layer-grid.blade.php`  
**ใช้กับ Mode:** `freelance_layer`

### DB Tables

| Table | PK | FK |
|---|---|---|
| `work_logs` | `id` | `employee_id` |
| `layer_rate_rules` | `id` | `employee_id` |

### ปุ่ม

| # | ปุ่ม | Target Route | HTTP |
|---|---|---|---|
| 1 | **"บันทึก Work Log"** | `route('workspace.saveWorkLogs', [id, month, year])` | POST |

### Form Fields — Template Rows

| Field | Name Pattern | Type | DB Column |
|---|---|---|---|
| ข้าม | `worklogs[{i}][is_disabled]` | checkbox | `work_logs.is_disabled` |
| วงราคา | `worklogs[{i}][pricing_template_label]` | select | `work_logs.pricing_template_label` |
| Layer | `worklogs[{i}][layer]` | number | `work_logs.layer` |
| นาที | `worklogs[{i}][minutes]` | number | `work_logs.minutes` |
| วินาที | `worklogs[{i}][seconds]` | number | `work_logs.seconds` |
| เรท/นาที | `worklogs[{i}][rate]` | number | `work_logs.rate` |
| รายได้ | `worklogs[{i}][amount]` | number (readonly) | `work_logs.amount` |
| Hidden: work_type | `worklogs[{i}][work_type]` | hidden="layer" | `work_logs.work_type` |
| Hidden: pricing_mode | `worklogs[{i}][pricing_mode]` | hidden="template" | `work_logs.pricing_mode` |

### Form Fields — Custom Rows (เหมือนกันแต่ pricing_mode = "custom")

| Field | Name Pattern | Type | DB Column |
|---|---|---|---|
| Custom/นาที | `worklogs[{i}][custom_rate]` | number | `work_logs.custom_rate` |

### JavaScript
- เวลาเลือก template → sync rate + layer_from
- amount = (minutes + seconds/60) × rate → คำนวณสด client-side

---

## 8. Workspace Partial: Freelance Fixed Grid

**ไฟล์:** `resources/views/workspace/partials/freelance-fixed-grid.blade.php`  
**ใช้กับ Mode:** `freelance_fixed`

### DB Tables

| Table | PK | FK |
|---|---|---|
| `work_logs` | `id` | `employee_id` |

### ปุ่ม

| # | ปุ่ม | Target Route | HTTP |
|---|---|---|---|
| 1 | **"บันทึก"** | `route('workspace.saveWorkLogs', [id, month, year])` | POST |

### Form Fields (per row)

| Field | Name Pattern | Type | DB Column |
|---|---|---|---|
| ข้าม | `worklogs[{i}][is_disabled]` | checkbox | `work_logs.is_disabled` |
| ประเภทงาน | `worklogs[{i}][work_type]` | text | `work_logs.work_type` |
| จำนวน (ชิ้น) | `worklogs[{i}][quantity]` | number | `work_logs.quantity` |
| เรท/ชิ้น | `worklogs[{i}][rate]` | number | `work_logs.rate` |
| ยอดเงิน | `worklogs[{i}][amount]` | hidden | `work_logs.amount` |

### หมายเหตุ
- ถ้าไม่มี work_logs → แสดง 10 blank rows
- Footer แสดง net_pay

---

## 9. Workspace Partial: YouTuber Salary Grid

**ไฟล์:** `resources/views/workspace/partials/youtuber-salary-grid.blade.php`  
**ใช้กับ Mode:** `youtuber_salary`

### DB Tables

| Table | PK | FK |
|---|---|---|
| `employee_salary_profiles` | `id` | `employee_id` |
| `work_logs` | `id` | `employee_id` |

### ปุ่ม

| # | ปุ่ม | Target Route | HTTP |
|---|---|---|---|
| 1 | **"+ เพิ่มรายการงาน"** | JavaScript `addYoutuberRow()` | — (client-side) |
| 2 | **"บันทึกรายการ"** | `route('workspace.saveWorkLogs', [id, month, year])` | POST |
| 3 | **× (ลบ row)** | JavaScript `this.closest('tr').remove()` | — (client-side) |

### Form Fields (Work Records — informational only, ไม่กระทบเงินเดือน)

| Field | Name Pattern | Type | DB Column |
|---|---|---|---|
| รายละเอียดงาน | `worklogs[{i}][work_type]` | text | `work_logs.work_type` |
| วันที่/สถานะ | `worklogs[{i}][notes]` | text | `work_logs.notes` |
| Hidden amount | `worklogs[{i}][amount]` | hidden=0 | `work_logs.amount` |

### หมายเหตุ
- แสดง base_salary จาก `employee_salary_profiles.base_salary`
- Work records เป็นเชิง informational เท่านั้น (amount = 0)

---

## 10. Workspace Partial: YouTuber Settlement Grid

**ไฟล์:** `resources/views/workspace/partials/youtuber-settlement-grid.blade.php`  
**ใช้กับ Mode:** `youtuber_settlement`

### DB Tables

| Table | PK | FK |
|---|---|---|
| `work_logs` | `id` | `employee_id` |

### ปุ่ม

| # | ปุ่ม | Target Route | HTTP |
|---|---|---|---|
| 1 | **"+ เพิ่มรายการ (Add Item)"** | JavaScript `addRow()` | — |
| 2 | **"บันทึก & คำนวณยอดสุทธิ"** | `route('workspace.saveWorkLogs', [id, month, year])` | POST |
| 3 | **× (ลบ row)** | JavaScript `this.closest('tr').remove()` | — |

### Form Fields (per row)

| Field | Name Pattern | Type | DB Column |
|---|---|---|---|
| รายการ | `worklogs[{i}][work_type]` | text | `work_logs.work_type` |
| ประเภท (income/deduction) | `worklogs[{i}][notes]` | select | `work_logs.notes` |
| จำนวนเงิน | `worklogs[{i}][amount]` | number | `work_logs.amount` |

### หมายเหตุ
- `notes` field ใช้เก็บ "income" หรือ "deduction" (fragile design — ใช้ notes field เก็บ business logic)

---

## 11. Workspace Partial: Claims Grid

**ไฟล์:** `resources/views/workspace/partials/claims-grid.blade.php`  
**ใช้ทุก Mode**

### DB Tables

| Table | PK | FK |
|---|---|---|
| `expense_claims` | `id` | `employee_id` |
| `employee_salary_profiles` | `id` | `employee_id` |
| `employees` | `id` | — (`advance_ceiling_percent` column) |

### ปุ่ม

| # | ปุ่ม | Target Route | HTTP | ผลลัพธ์ |
|---|---|---|---|---|
| 1 | **"จัดการ" (expand/collapse)** | Alpine `openManage` toggle | — | เปิด/ปิดส่วนเพิ่มรายการ |
| 2 | **ดูรายละเอียด (ตาไอคอน)** | Alpine modal `detailText` | — | แสดง description |
| 3 | **"Approve"** | `route('workspace.claims.approve', claim_id)` | PATCH | อนุมัติ claim |
| 4 | **"ลบ"** | `route('workspace.claims.delete', claim_id)` | DELETE | ลบ claim (confirm) |
| 5 | **"Update" เพดาน** | `route('workspace.updateAdvanceCeiling', employee_id)` | PATCH | อัปเดต advance_ceiling_percent |
| 6 | **"+ เพิ่มรายการ"** | `route('workspace.claims.store', [id, month, year])` | POST | เพิ่ม claim ใหม่ |

### Form Fields — ตั้งเพดาน

| Field | Name | Type | DB Column |
|---|---|---|---|
| เพดาน % | `advance_ceiling_percent` | range + number | `employees.advance_ceiling_percent` |

### Form Fields — เพิ่มรายการ

| Field | Name | Type | DB Column |
|---|---|---|---|
| ประเภท | `type` | select (advance/reimbursement) | `expense_claims.type` |
| วันที่ | `claim_date` | date | `expense_claims.claim_date` |
| จำนวนเงิน | `amount` | number | `expense_claims.amount` |
| รายละเอียด | `description` | text | `expense_claims.description` |

---

## 12. Workspace Partial: Performance Records

**ไฟล์:** `resources/views/workspace/partials/performance-records.blade.php`  
**ใช้ทุก Mode**

### DB Tables

| Table | PK | FK |
|---|---|---|
| `performance_records` | `id` | `employee_id`, `work_assignment_id` |
| `work_assignments` | `id` | `employee_id`, `work_log_type_id` |
| `work_log_types` | `id` | — |

### ปุ่ม

| # | ปุ่ม | Target Route | HTTP | ผลลัพธ์ |
|---|---|---|---|---|
| 1 | **"บันทึก" (per assignment row)** | `route('workspace.performance.store', [id, month, year])` | POST | สร้าง performance record |
| 2 | **"ลบ" (per record)** | `route('workspace.performance.delete', record_id)` | DELETE | ลบ record (confirm) |

### Form Fields (per assigned work row)

| Field | Name | Type | DB Column |
|---|---|---|---|
| Hidden: work_assignment_id | `work_assignment_id` | hidden | `performance_records.work_assignment_id` |
| Hidden: work_type_code | `work_type_code` | hidden | `performance_records.work_type_code` |
| Hidden: work_title | `work_title` | hidden | `performance_records.work_title` |
| Hidden: video_title | `video_title` | hidden | `performance_records.video_title` |
| Hidden: rate_snapshot | `rate_snapshot` | hidden | `performance_records.rate_snapshot` |
| Hidden: quantity | `quantity` | hidden | `performance_records.quantity` |
| ระยะเวลา (MM:SS) | `duration_mmss` | text | parsed → `hours`, `minutes`, `seconds` |
| วันที่เริ่ม | `record_date` | date | `performance_records.record_date` |
| วันที่เสร็จ | `finish_date` | date | `performance_records.finish_date` |
| Status | `status` | select | `performance_records.status` |
| คุณภาพงาน (if finished) | `quality_score` | number(1-5) | `performance_records.quality_score` |
| เหตุผล reject (if rejected) | `reject_reason` | text | `performance_records.reject_reason` |
| โน้ต | `notes` | text | `performance_records.notes` |

---

## 13. หน้า Payslip Preview

**ไฟล์:** `resources/views/payslip/preview.blade.php`  
**URL:** `/payslip/{employee}/{month}/{year}/preview`  
**Route Name:** `payslip.preview`  
**Controller:** `PayslipController@preview`

### DB Tables

| Table | PK | FK |
|---|---|---|
| `employees` | `id` | `position_id`, `department_id` |
| `employee_bank_accounts` | `id` | `employee_id` |
| `payslips` | `id` | `employee_id` |
| `payslip_items` | `id` | `payslip_id` |
| `payroll_batches` | `id` | `employee_id` |
| `payroll_items` | `id` | `payroll_batch_id` |
| `company_profiles` | `id` | — |
| `layer_rate_rules` | `id` | `employee_id` |
| `payment_proofs` | `id` | `employee_id` |
| `attendance_logs` | `id` | `employee_id` |

### ปุ่ม (print-hide area)

| # | ปุ่ม | Target Route | HTTP | ผลลัพธ์ |
|---|---|---|---|---|
| 1 | **"← กลับ Workspace"** | `route('workspace.show', [id, month, year])` | GET | กลับ workspace |
| 2 | **"Finalize"** | `route('payslip.finalize', [id, month, year])` | POST | Finalize payslip (snapshot) |
| 3 | **"Export PDF"** | `route('payslip.pdf', [id, month, year])` | GET | Download PDF |
| 4 | **"Print A5"** | `window.print()` | — | Browser print dialog |
| 5 | **"ยกเลิก Finalize"** (if finalized) | `route('payslip.unfinalize', [id, month, year])` | POST | Unfinalize (confirm) |
| 6 | **"ดูรูป" (per proof)** | `asset('storage/' + path)` | GET (link) | เปิดรูปในแท็บใหม่ |
| 7 | **"อัปโหลดสลิปการโอน"** | `route('workspace.proof.upload', [id, month, year])` | POST (multipart) | Upload proof image |

### Payslip Layout Structure

```
┌─────────────────────────────────────────┐
│ Company Header + Tagline + Descriptor   │
├─────────────────────────────────────────┤
│ Info Row: Tax ID | Month | Print | Pay  │
├─────────────────────────────────────────┤
│ Employee: Name | Position | Bank | Acct │
├─────────────────────────────────────────┤
│ Metrics: Hours | OT | Late | LWOP      │
├────────────────────┬────────────────────┤
│ Income Table       │ Deduction Table    │
│ - items            │ - items            │
│ - total            │ - total            │
├────────────────────┴────────────────────┤
│ Net Pay Box                             │
├─────────────────────────────────────────┤
│ YTD Summary: Income | Deduction | Net   │
├─────────────────────────────────────────┤
│ Signatures: ผู้จ่าย | ผู้รับ              │
├─────────────────────────────────────────┤
│ Footer disclaimer                       │
└─────────────────────────────────────────┘
```

### Below Payslip (print-hide):
- **Layer Rate Reference Table** — แสดงเรท/นาที (if freelance_layer)
- **Payment Proofs** — รูปสลิปโอนเงิน + upload form

---

## 14. หน้า Payslip PDF

**ไฟล์:** `resources/views/payslip/pdf.blade.php`  
**URL:** `/payslip/{employee}/{month}/{year}/pdf`  
**Route Name:** `payslip.pdf`  
**Controller:** `PayslipController@downloadPdf`  
**HTTP:** GET → DomPDF render → download

### DB Tables

| Table | PK | FK |
|---|---|---|
| `payslips` | `id` | `employee_id` |
| `payslip_items` | `id` | `payslip_id` |
| `employees` | `id` | — |
| `employee_bank_accounts` | `id` | `employee_id` |
| `positions` | `id` | — |
| `work_logs` | `id` | `employee_id` (ดึงเพิ่มเติมใน Blade!) |

### หมายเหตุ
- ⚠️ **Business logic ใน Blade:** PDF template มี query `WorkLog::where(...)` ดึง work_logs ตรง ๆ ใน Blade view
- ไม่มีปุ่ม — เป็นหน้า PDF render อย่างเดียว
- ใช้ font THSarabunNew.ttf จาก storage/fonts
- รองรับ freelance_layer, freelance_fixed, youtuber_settlement, youtuber_salary ในส่วน Details

---

## 15. หน้า Settings — Rules

**ไฟล์:** `resources/views/settings/rules.blade.php`  
**URL:** `/settings/rules`  
**Route Name:** `settings.rules`  
**Controller:** `SettingsController@rules`

### DB Tables

| Table | PK | FK |
|---|---|---|
| `attendance_rules` | `id` | — (config JSON) |
| `social_security_configs` | `id` | — |
| `company_holidays` | `id` | — |

### ปุ่มและ Forms (5 sections)

#### Section 1: เวลาทำงาน (working_hours)

| # | ปุ่ม | Target Route | HTTP |
|---|---|---|---|
| 1 | **"บันทึกเวลาทำงาน"** | `route('settings.rules.update', 'working_hours')` | PATCH |

| Field | Name | Type | DB |
|---|---|---|---|
| เวลาเข้างาน | `target_check_in` | time | `attendance_rules.config->target_check_in` |
| เวลาออกงาน | `target_check_out` | time | `attendance_rules.config->target_check_out` |
| นาทีทำงาน/วัน | `target_minutes_per_day` | number | `attendance_rules.config->target_minutes_per_day` |
| วันทำงาน/เดือน | `working_days_per_month` | number | `attendance_rules.config->working_days_per_month` |

#### Section 2: ประกันสังคม (social_security)

| # | ปุ่ม | Target Route | HTTP |
|---|---|---|---|
| 1 | **"บันทึกตั้งค่าประกันสังคม"** | `route('settings.rules.update', 'social_security')` | PATCH |

| Field | Name | Type | DB |
|---|---|---|---|
| เพดานเงินเดือน | `salary_ceiling` | number | `social_security_configs.salary_ceiling` |
| อัตราหักพนักงาน | `employee_contribution_rate` | number | `social_security_configs.employee_rate` |
| อัตราสมทบนายจ้าง | `employer_contribution_rate` | number | `social_security_configs.employer_rate` |

#### Section 3: เบี้ยขยัน Tiered (diligence)

| # | ปุ่ม | Target Route | HTTP |
|---|---|---|---|
| 1 | **"+ เพิ่มขั้นบันไดเบี้ยขยัน"** | Alpine `addTier()` | — (client) |
| 2 | **ลบขั้น (trash icon)** | Alpine `removeTier(index)` | — (client) |
| 3 | **"บันทึกกฎเบี้ยขยันทั้งหมด"** | `route('settings.rules.update', 'diligence')` | PATCH |

| Field | Name Pattern | Type | DB |
|---|---|---|---|
| สายไม่เกิน | `tiers[{i}][late_count_max]` | number | `attendance_rules.config->tiers[i].late_count_max` |
| ลา/ขาดไม่เกิน | `tiers[{i}][lwop_days_max]` | number | `attendance_rules.config->tiers[i].lwop_days_max` |
| จำนวนเงิน | `tiers[{i}][amount]` | number | `attendance_rules.config->tiers[i].amount` |

#### Section 4: OT & Late Penalty

| # | ปุ่ม | Target Route | HTTP |
|---|---|---|---|
| 1 | **"อัปเดตกฎ OT"** | `route('settings.rules.update', 'ot_rate')` | PATCH |
| 2 | **"อัปเดตอัตราหักมาสาย"** | `route('settings.rules.update', 'late_deduction')` | PATCH |

| Field | Name | Type | DB |
|---|---|---|---|
| ตัวคูณ OT | `rate_multiplier` | number | `attendance_rules.config->rate_multiplier` |
| เพดาน OT/เดือน | `max_ot_hours` | number | `attendance_rules.config->max_ot_hours` |
| หักต่อนาที | `rate_per_minute` | number | `attendance_rules.config->rate_per_minute` |
| อนุโลม (นาที) | `grace_period_minutes` | number | `attendance_rules.config->grace_period_minutes` |

#### Section 5: วันหยุด (Holidays)

| # | ปุ่ม | Target Route | HTTP |
|---|---|---|---|
| 1 | **"ดึงข้อมูลวันหยุดราชการ 2026"** | `route('settings.holidays.load-legal')` | POST |
| 2 | **"เพิ่มรายการ"** (add holiday) | `route('settings.holidays.add')` | POST |
| 3 | **ลบวันหยุด (trash icon)** | `route('settings.holidays.delete', id)` | DELETE |

| Field | Name | Type | DB |
|---|---|---|---|
| ชื่อวันหยุด | `name` | text | `company_holidays.name` |
| วันที่ | `holiday_date` | date | `company_holidays.holiday_date` |

---

## 16. หน้า Settings — Company

**ไฟล์:** `resources/views/settings/company.blade.php`  
**URL:** `/settings/company`  
**Route Name:** `settings.company`  
**Controller:** `SettingsController@company`

### DB Tables

| Table | PK | FK |
|---|---|---|
| `company_profiles` | `id` | — |

### ปุ่ม

| # | ปุ่ม | Target Route | HTTP |
|---|---|---|---|
| 1 | **"← ย้อนกลับ"** | `route('settings.rules')` | GET |
| 2 | **"ยกเลิก"** | `route('settings.rules')` | GET |
| 3 | **"บันทึก"** | `route('settings.company.update')` | POST (multipart) |

### Form Fields

| Section | Field | Name | Type | DB Column |
|---|---|---|---|---|
| ข้อมูลบริษัท | ชื่อบริษัท | `name` | text | `company_profiles.name` |
| ข้อมูลบริษัท | เลขประจำตัวประเมิน | `tax_id` | text | `company_profiles.tax_id` |
| ข้อมูลบริษัท | Tagline | `tagline` | text | `company_profiles.tagline` |
| ข้อมูลบริษัท | Subtitle | `payslip_header_subtitle` | text | `company_profiles.payslip_header_subtitle` |
| ข้อมูลบริษัท | ที่อยู่ | `address` | textarea | `company_profiles.address` |
| ข้อมูลบริษัท | โทรศัพท์ | `phone` | tel | `company_profiles.phone` |
| ข้อมูลบริษัท | อีเมล | `email` | email | `company_profiles.email` |
| สี CI | Primary Color | `primary_color` | color | `company_profiles.primary_color` |
| สี CI | Secondary Color | `secondary_color` | color | `company_profiles.secondary_color` |
| ลายเซ็น | ชื่อผู้จ่าย | `signature_approver_name` | text | `company_profiles.signature_approver_name` |
| ลายเซ็น | PNG ผู้จ่าย | `signature_approver_image` | file | `company_profiles.signature_approver_image_path` |
| ลายเซ็น | ชื่อผู้รับ | `signature_receiver_name` | text | `company_profiles.signature_receiver_name` |
| ลายเซ็น | PNG ผู้รับ | `signature_receiver_image` | file | `company_profiles.signature_receiver_image_path` |
| Footer | ข้อความท้ายสลิป | `payslip_footer_text` | textarea | `company_profiles.payslip_footer_text` |

---

## 17. หน้า Settings — Master Data

**ไฟล์:** `resources/views/settings/master-data.blade.php`  
**URL:** `/settings/master-data`  
**Route Name:** `settings.master-data`  
**Controller:** `MasterDataController@index`

### DB Tables

| Table | PK | FK |
|---|---|---|
| `payroll_item_types` | `id` | — |
| `departments` | `id` | — |
| `positions` | `id` | `department_id` |

### 3 Tabs (Alpine.js)

#### Tab 1: รายการเงินเดือน (payroll_items)

| # | ปุ่ม | Target Route | HTTP |
|---|---|---|---|
| 1 | **"เพิ่มรายการ"** (form) | `route('settings.master-data.payroll-item-types.store')` | POST |
| 2 | **"แก้ไข"** (inline toggle) | Alpine `editing = true` | — |
| 3 | **"บันทึก"** (inline edit) | `route('settings.master-data.payroll-item-types.update', id)` | PATCH |
| 4 | **"ลบ"** | `route('settings.master-data.payroll-item-types.delete', id)` | DELETE |

| Field (Add) | Name | Type | DB Column |
|---|---|---|---|
| Code | `code` | text | `payroll_item_types.code` |
| ชื่อไทย | `label_th` | text | `payroll_item_types.label_th` |
| ชื่ออังกฤษ | `label_en` | text | `payroll_item_types.label_en` |
| หมวด | `category` | select | `payroll_item_types.category` |
| ลำดับ | `sort_order` | number | `payroll_item_types.sort_order` |

#### Tab 2: แผนก (departments)

| # | ปุ่ม | Target Route | HTTP |
|---|---|---|---|
| 1 | **"เพิ่มแผนก"** | `route('settings.master-data.departments.store')` | POST |
| 2 | **"แก้ไข"** / **"บันทึก"** | `route('settings.master-data.departments.update', id)` | PATCH |
| 3 | **"ลบ"** | `route('settings.master-data.departments.delete', id)` | DELETE |

| Field | Name | Type | DB Column |
|---|---|---|---|
| ชื่อแผนก | `name` | text | `departments.name` |
| Code | `code` | text | `departments.code` |
| Active (edit only) | `is_active` | checkbox | `departments.is_active` |

#### Tab 3: ตำแหน่ง (positions)

| # | ปุ่ม | Target Route | HTTP |
|---|---|---|---|
| 1 | **"เพิ่มตำแหน่ง"** | `route('settings.master-data.positions.store')` | POST |
| 2 | **"แก้ไข"** / **"บันทึก"** | `route('settings.master-data.positions.update', id)` | PATCH |
| 3 | **"ลบ"** | `route('settings.master-data.positions.delete', id)` | DELETE |

| Field | Name | Type | DB Column |
|---|---|---|---|
| ชื่อตำแหน่ง | `name` | text | `positions.name` |
| Code | `code` | text | `positions.code` |
| แผนก | `department_id` | select | `positions.department_id` |
| Active (edit only) | `is_active` | checkbox | `positions.is_active` |

---

## 18. หน้า Work Manager

**ไฟล์:** `resources/views/settings/works/index.blade.php`  
**URL:** `/settings/works`  
**Route Name:** `settings.works.index`  
**Controller:** `WorkManagerController@index`

### DB Tables

| Table | PK | FK |
|---|---|---|
| `work_log_types` | `id` | — |
| `work_assignments` | `id` | `employee_id`, `work_log_type_id` |
| `employees` | `id` | — |

### ปุ่มและ Forms (3 sections)

#### Section 1: เพิ่ม Work Template

| # | ปุ่ม | Target Route | HTTP |
|---|---|---|---|
| 1 | **"เพิ่ม Work"** | `route('settings.works.store')` | POST |

| Field | Name | Type | DB Column |
|---|---|---|---|
| ชื่อ Work | `name` | text | `work_log_types.name` |
| Code | `code` | text | `work_log_types.code` |
| Module Key | `module_key` | select | `work_log_types.module_key` |
| Payroll Mode | `payroll_mode` | select | `work_log_types.payroll_mode` |
| Footage Size | `footage_size` | text | `work_log_types.footage_size` |
| Target Length (min) | `target_length_minutes` | number | `work_log_types.target_length_minutes` |
| Default Rate/Min | `default_rate_per_minute` | number | `work_log_types.default_rate_per_minute` |
| Sort Order | `sort_order` | number | `work_log_types.sort_order` |
| Description | `description` | textarea | `work_log_types.description` |
| Config JSON | `config_json` | textarea | `work_log_types.config` |
| เปิดใช้งาน | `is_active` | checkbox | `work_log_types.is_active` |

#### Section 2: Assign งาน

| # | ปุ่ม | Target Route | HTTP |
|---|---|---|---|
| 1 | **"Assign Work"** | `route('settings.works.assignments.store')` | POST |

| Field | Name | Type | DB Column |
|---|---|---|---|
| พนักงาน | `employee_id` | select | `work_assignments.employee_id` |
| Work | `work_log_type_id` | select | `work_assignments.work_log_type_id` |
| Assigned Date | `assigned_date` | date | `work_assignments.assigned_date` |
| Due Date | `due_date` | date | `work_assignments.due_date` |
| Priority | `priority` | select | `work_assignments.priority` |
| Notes | `notes` | textarea | `work_assignments.notes` |

#### Section 3: Assignment List & Work Template List

| # | ปุ่ม | Target Route | HTTP |
|---|---|---|---|
| 1 | **"อัปเดต"** (assignment) | `route('settings.works.assignments.update', id)` | PATCH |
| 2 | **"ลบ"** (assignment) | `route('settings.works.assignments.delete', id)` | DELETE |
| 3 | **"ปิด"/"เปิด"** (work template) | `route('settings.works.toggle', id)` | PATCH |
| 4 | **"แก้ไข"** (work template) | `route('settings.works.update', id)` | PATCH |
| 5 | **"ลบ"** (work template) | `route('settings.works.delete', id)` | DELETE |

---

## 19. หน้า Calendar

**ไฟล์:** `resources/views/calendar/index.blade.php`  
**URL:** `/calendar/{month?}/{year?}`  
**Route Name:** `calendar.index`  
**Controller:** `CalendarController@index`

### DB Tables

| Table | PK | FK |
|---|---|---|
| `company_holidays` | `id` | — |
| `attendance_logs` | `id` | `employee_id` |
| `employees` | `id` | — |

### ปุ่ม

| # | ปุ่ม | Target Route | HTTP |
|---|---|---|---|
| 1 | **← เดือนก่อน** | `route('calendar.index', [prevMonth, prevYear])` | GET |
| 2 | **→ เดือนถัดไป** | `route('calendar.index', [nextMonth, nextYear])` | GET |

### หมายเหตุ
- ไม่มี form action — เป็นหน้าแสดงผลอย่างเดียว
- แสดง calendar grid 7 คอลัมน์ (Sun-Sat)
- Events แสดงด้วย color coding (holiday, LWOP, etc.)

---

## 20. หน้า Company Expenses

**ไฟล์:** `resources/views/company/expenses.blade.php`  
**URL:** `/company/expenses`  
**Route Name:** `company.expenses`  
**Controller:** `CompanyFinanceController@expenses`

### DB Tables
**ไม่มี** — Controller return empty view

### ปุ่ม

| # | ปุ่ม | Target Route | HTTP |
|---|---|---|---|
| 1 | **"+ บันทึกค่าใช้จ่าย"** | ❌ ไม่มี action (button ธรรมดา ไม่มี onclick/href) | — |

### หมายเหตุ
- ⚠️ **Placeholder page** — ยังไม่ได้พัฒนา
- Controller `expenses()` return view with empty data
- ปุ่ม "+ บันทึกค่าใช้จ่าย" ไม่ทำงาน (ไม่มี route)

---

## 21. Global Keyboard Navigation

**ไฟล์:** `resources/views/partials/grid-navigation.blade.php`  
**Include จาก:** `workspace/show.blade.php` (`@include('partials.grid-navigation')`)

### ฟังก์ชัน
- Arrow key navigation สำหรับ input/select ภายในตาราง
- **ArrowUp/Down** → ย้ายไปช่อง input เดิมของ row ถัดไป/ก่อนหน้า
- **ArrowLeft/Right** → ย้ายไปช่อง input ซ้าย/ขวาใน row เดียวกัน
- Auto-select text เมื่อ focus

---

## สรุป Route-Button Mapping ทั้งระบบ

### จำนวนปุ่มทั้งหมดที่ทำงานจริง (functional): **~65 ปุ่ม/actions**

### Route Summary

| Route Name | HTTP | Controller | ปุ่มที่เรียกใช้จากหน้าไหน |
|---|---|---|---|
| `employees.index` | GET | EmployeeController@index | Nav, Workspace back, Create/Edit cancel |
| `employees.create` | GET | EmployeeController@create | (ไม่มีปุ่มตรง — ใช้ modal แทน) |
| `employees.store` | POST | EmployeeController@store | Employee Board modal, Create page |
| `employees.edit` | GET | EmployeeController@edit | Employee Board card "EDIT" |
| `employees.update` | PUT | EmployeeController@update | Edit page "บันทึก" |
| `employees.toggle-status` | PATCH | EmployeeController@toggleStatus | Employee Board card toggle |
| `workspace.show` | GET | WorkspaceController@show | Employee Board card click, Payslip ← |
| `workspace.recalculate` | POST | WorkspaceController@recalculate | Workspace "คำนวณใหม่" |
| `workspace.saveAttendance` | POST | WorkspaceController@saveAttendance | Attendance grid "บันทึก" + day_type change |
| `workspace.saveWorkLogs` | POST | WorkspaceController@saveWorkLogs | All work log grids "บันทึก" |
| `workspace.payroll.update` | PATCH | WorkspaceController@updatePayrollItem | Workspace right panel inline edit |
| `workspace.module.toggle` | POST | WorkspaceController@toggleModule | Workspace "ประกันสังคม" toggle |
| `workspace.claims.store` | POST | WorkspaceController@storeClaim | Claims grid "+ เพิ่มรายการ" |
| `workspace.claims.approve` | PATCH | WorkspaceController@approveClaim | Claims grid "Approve" |
| `workspace.claims.delete` | DELETE | WorkspaceController@deleteClaim | Claims grid "ลบ" |
| `workspace.updateAdvanceCeiling` | PATCH | WorkspaceController@updateAdvanceCeiling | Claims grid "Update" เพดาน |
| `workspace.performance.store` | POST | WorkspaceController@storePerformanceRecord | Performance "บันทึก" |
| `workspace.performance.delete` | DELETE | WorkspaceController@deletePerformanceRecord | Performance "ลบ" |
| `workspace.proof.upload` | POST | WorkspaceController@uploadProof | Payslip preview "อัปโหลดสลิป" |
| `payslip.preview` | GET | PayslipController@preview | Workspace "Slip" button |
| `payslip.finalize` | POST | PayslipController@finalize | Payslip preview "Finalize" |
| `payslip.unfinalize` | POST | PayslipController@unfinalize | Payslip preview "ยกเลิก Finalize" |
| `payslip.pdf` | GET | PayslipController@downloadPdf | Payslip preview "Export PDF" |
| `calendar.index` | GET | CalendarController@index | Nav, ← →  |
| `company.expenses` | GET | CompanyFinanceController@expenses | Nav |
| `settings.rules` | GET | SettingsController@rules | Nav, Company ← |
| `settings.rules.update` | PATCH | SettingsController@updateRule | Rules page (4 forms) |
| `settings.holidays.add` | POST | SettingsController@addHoliday | Rules page "เพิ่มรายการ" |
| `settings.holidays.load-legal` | POST | SettingsController@loadLegalHolidays | Rules page "ดึงข้อมูลวันหยุด" |
| `settings.holidays.delete` | DELETE | SettingsController@deleteHoliday | Rules page holiday trash |
| `settings.company` | GET | SettingsController@company | Master Data link |
| `settings.company.update` | POST | SettingsController@updateCompany | Company page "บันทึก" |
| `settings.master-data` | GET | MasterDataController@index | Nav |
| `settings.master-data.payroll-item-types.store` | POST | MasterDataController@store | Master Data Tab1 "เพิ่ม" |
| `settings.master-data.payroll-item-types.update` | PATCH | MasterDataController@update | Master Data Tab1 "บันทึก" |
| `settings.master-data.payroll-item-types.delete` | DELETE | MasterDataController@delete | Master Data Tab1 "ลบ" |
| `settings.master-data.departments.store` | POST | MasterDataController@storeDept | Master Data Tab2 "เพิ่ม" |
| `settings.master-data.departments.update` | PATCH | MasterDataController@updateDept | Master Data Tab2 "บันทึก" |
| `settings.master-data.departments.delete` | DELETE | MasterDataController@deleteDept | Master Data Tab2 "ลบ" |
| `settings.master-data.positions.store` | POST | MasterDataController@storePos | Master Data Tab3 "เพิ่ม" |
| `settings.master-data.positions.update` | PATCH | MasterDataController@updatePos | Master Data Tab3 "บันทึก" |
| `settings.master-data.positions.delete` | DELETE | MasterDataController@deletePos | Master Data Tab3 "ลบ" |
| `settings.works.index` | GET | WorkManagerController@index | Nav |
| `settings.works.store` | POST | WorkManagerController@store | Work Mgr "เพิ่ม Work" |
| `settings.works.update` | PATCH | WorkManagerController@update | Work Mgr "แก้ไข" |
| `settings.works.toggle` | PATCH | WorkManagerController@toggle | Work Mgr "ปิด"/"เปิด" |
| `settings.works.delete` | DELETE | WorkManagerController@destroy | Work Mgr "ลบ" |
| `settings.works.assignments.store` | POST | WorkManagerController@storeAssign | Work Mgr "Assign Work" |
| `settings.works.assignments.update` | PATCH | WorkManagerController@updateAssign | Work Mgr "อัปเดต" |
| `settings.works.assignments.delete` | DELETE | WorkManagerController@deleteAssign | Work Mgr "ลบ" assignment |

---

## DB Relationship Map (FK/PK Summary)

```
employees (PK: id)
  ├── employee_profiles (FK: employee_id)
  ├── employee_salary_profiles (FK: employee_id)
  ├── employee_bank_accounts (FK: employee_id)
  ├── attendance_logs (FK: employee_id)
  ├── work_logs (FK: employee_id)
  ├── payroll_batches (FK: employee_id)
  │     └── payroll_items (FK: payroll_batch_id)
  │           └── payroll_item_types (FK via payroll_item_type_id)
  ├── payslips (FK: employee_id)
  │     └── payslip_items (FK: payslip_id)
  ├── expense_claims (FK: employee_id)
  ├── performance_records (FK: employee_id, work_assignment_id)
  ├── work_assignments (FK: employee_id, work_log_type_id)
  ├── layer_rate_rules (FK: employee_id)
  ├── module_toggles (FK: employee_id)
  └── payment_proofs (FK: employee_id)

departments (PK: id)
  ├── employees (FK: department_id)
  └── positions (FK: department_id)

positions (PK: id)
  └── employees (FK: position_id)

work_log_types (PK: id)
  └── work_assignments (FK: work_log_type_id)

attendance_rules (PK: id) — standalone config
social_security_configs (PK: id) — standalone config
company_holidays (PK: id) — standalone
company_profiles (PK: id) — standalone

users (PK: id) — auth only
roles (PK: id)
permissions (PK: id)
audit_logs (PK: id) — FK: varies (polymorphic-like)
```

---

## Issues Found

| # | หน้า | Issue | ระดับ |
|---|---|---|---|
| 1 | Company Expenses | ปุ่ม "+ บันทึกค่าใช้จ่าย" ไม่มี action — dead button | 🟡 Medium |
| 2 | Payslip PDF | Business logic ใน Blade (`WorkLog::where(...)` query ใน view) | 🔴 High |
| 3 | YouTuber Settlement | ใช้ `notes` field เก็บ "income"/"deduction" — fragile design | 🟡 Medium |
| 4 | Navigation | ไม่มีปุ่ม Logout | 🟡 Medium |
| 5 | Auth | ไม่มี auth middleware — ทุกหน้าเข้าถึงได้ทันที | 🔴 High |
| 6 | All Forms | ไม่มี CSRF rate limiting หรือ double-submit protection | 🟡 Medium |

---

*สร้างจาก source code analysis — ไม่มี screenshot เนื่องจาก browser tool ไม่รองรับการแคปหน้าจอ*
