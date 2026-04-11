# AGENTS.md
# xHR Payroll & Finance System
คู่มือ Agent / Development Contract สำหรับระบบ PHP + MySQL + phpMyAdmin

> เอกสารนี้กำหนดกติกาการพัฒนา, ขอบเขตความรับผิดชอบของ agent แต่ละตัว, โครงสร้างระบบ, business rules, และมาตรฐานการเปลี่ยนแปลงระบบ เพื่อให้โปรเจกต์ปรับปรุงได้ง่าย, ไม่กลับไปติดกับดัก Excel, และรองรับการใช้งานจริงภายในองค์กร

---

## 1) Project Overview

ระบบนี้คือ **xHR Payroll & Finance System** สำหรับแทนระบบ Excel เดิมที่ใช้จัดการ:
- เงินเดือนพนักงานประจำ
- ค่าจ้างฟรีแลนซ์ตัดต่อแบบเรทเลเยอร์
- ค่าจ้างฟรีแลนซ์แบบฟิกเรท
- YouTuber / Talent ทั้งแบบเงินเดือนและแบบรายรับ-รายจ่าย
- ประกันสังคม
- โบนัส / Performance / Threshold rules
- ค่าใช้จ่ายบริษัทและ subscription
- สลิปเงินเดือน
- สรุปรายปีรายบุคคล
- สรุปกำไรขาดทุนบริษัท

ระบบต้องพัฒนาโดยยึดหลัก:
1. **PHP-first**
2. **MySQL/phpMyAdmin-friendly**
3. **Dynamic data entry**
4. **Rule-driven**
5. **Audit-able**
6. **ออกสลิปเงินเดือนได้**
7. **ปรับปรุงง่ายในอนาคต**

---

## 2) Core Design Principles

### 2.1 Record-based, not cell-based
ห้ามออกแบบ logic โดยอิงตำแหน่ง cell แบบ Excel  
ทุกอย่างต้องเก็บเป็น record ใน database

ผิด:
- `B33`, `X22`, `jan!X62`

ถูก:
- `employee_id`
- `payroll_batch_id`
- `payroll_item_type`
- `work_log_id`

### 2.2 Single Source of Truth
ข้อมูลหลักต้องมีแหล่งอ้างอิงเดียว

| ข้อมูล | แหล่งจริง |
|---|---|
| employee profile | `employees` / `employee_profiles` |
| base salary | `employee_salary_profiles` |
| rate config | `rate_rules` / `layer_rate_rules` |
| monthly payroll items | `payroll_items` |
| payslip | `payslips` + `payslip_items` |
| company monthly finance | `company_monthly_summaries` |

### 2.3 Rule-driven, not hardcoded
สูตรและกฎต้องเก็บใน config / rules table  
ห้ามฝัง logic สำคัญกระจัดกระจายใน view หรือ controller

ตัวอย่างกฎที่ต้องตั้งค่าได้:
- OT
- เบี้ยขยัน
- Performance threshold
- Layer rate
- Social security
- โบนัส
- รายการหัก
- Module toggle

### 2.4 Dynamic but controlled editing
หน้ากรอกข้อมูลต้องให้ความรู้สึกเหมือน Excel  
แต่ข้างหลังต้องมีโครงสร้างและ audit ครบ

อนุญาต:
- inline editing
- add row
- remove row
- duplicate row
- recalculate instantly

ต้องมี:
- source flag (`auto`, `manual`, `override`, `master`)
- audit log
- permission control
- validation

### 2.5 Maintainability first
ทุกส่วนต้องพร้อมต่อยอด
- เพิ่ม payroll mode ใหม่ได้
- เพิ่ม rule ใหม่ได้
- เปลี่ยนเพดาน SSO ได้
- เปลี่ยนรูปแบบสลิปได้
- เพิ่ม report ได้

---

## 3) Technology Constraints

### Required Stack
- PHP 8.2+
- Laravel (preferred)
- MySQL 8+
- phpMyAdmin-compatible schema
- Blade + vanilla JS / Alpine.js / lightweight JS
- PDF generation via DomPDF or Snappy

### Avoid
- Spreadsheets as database
- Magic numbers in views
- Business logic in Blade
- Direct query logic scattered in controllers
- Overengineering with unnecessary microservices

---

## 4) Domain Model

### 4.1 Payroll Modes
ระบบต้องรองรับอย่างน้อย:
- `monthly_staff`
- `freelance_layer`
- `freelance_fixed`
- `youtuber_salary`
- `youtuber_settlement`
- `custom_hybrid`

### 4.2 Core Entities
- Employee
- EmployeeProfile
- EmployeeSalaryProfile
- EmployeeBankAccount
- PayrollBatch
- PayrollItem
- WorkLog
- AttendanceLog
- BonusRule
- ThresholdRule
- SocialSecurityConfig
- Payslip
- ExpenseClaim
- CompanyExpense
- CompanyRevenue
- ModuleToggle
- AuditLog

---

## 5) Agent Responsibilities

ระบบนี้แบ่ง agent ทางความคิด/การพัฒนาออกเป็นหลายบทบาท  
แม้ทำโดยคนเดียวหรือ AI ตัวเดียว ก็ต้องยึด separation นี้

### 5.1 Architecture Agent
รับผิดชอบ:
- วางโครงระบบ
- คุม domain boundaries
- ป้องกัน cell-thinking แบบ Excel
- ตัดสินใจว่าข้อมูลไหนเป็น master / transaction / result

ต้องตรวจให้แน่ใจว่า:
- ไม่มี source ซ้ำโดยไม่จำเป็น
- ไม่มี logic สำคัญใน view
- ทุก module แยกขอบเขตชัด

ห้าม:
- ปล่อย schema แบบใช้ชื่อ field มั่ว
- ปล่อยให้ report เป็น data source หลัก
- ให้ payslip ไปดึงค่าจากตำแหน่งตาราง UI โดยตรง

### 5.2 Database Agent
รับผิดชอบ:
- ออกแบบ schema
- index
- foreign keys
- data types
- migrations
- compatibility กับ phpMyAdmin/shared hosting

หลักสำคัญ:
- ใช้ `unsignedBigInteger` / `bigint unsigned` สำหรับ FK หลัก
- monetary fields ใช้ `decimal(12,2)` หรือมากกว่าตามเหมาะสม
- time duration ใช้ integer minutes/seconds
- หลีกเลี่ยง enum ที่แข็งเกินจำเป็น ถ้าต้อง config ขยายในอนาคต

ต้องมี:
- timestamps
- status columns
- soft deletes เฉพาะ entity ที่เหมาะสม
- audit references

### 5.3 Payroll Rules Agent
รับผิดชอบ:
- นิยาม logic การคิดเงิน
- แยกตาม payroll mode
- สร้าง configurable rules
- validate interdependencies

ต้องรองรับ:
- monthly salary
- OT
- diligence allowance
- performance bonus
- late deduction
- LWOP
- SSO
- freelance layer formula
- freelance fixed formula
- youtuber salary
- youtuber settlement
- hybrid override

ห้าม:
- hardcode SSO 750 แบบถาวร
- hardcode layer rate ใน service โดยไม่มี config
- ใช้ base salary ลดตรงฝั่ง income

### 5.4 UI/UX Agent
รับผิดชอบ:
- ทำหน้าใช้งานให้เหมือน spreadsheet ที่ถูกต้อง
- ลดขั้นตอน
- ทำให้ user ทำงานหลักในหน้าเดียวได้

หน้าหลัก:
- Employee Board
- Employee Workspace
- Payslip Preview
- Annual Summary
- Company Finance
- Rule Manager
- Settings

หลัก UX:
- edit inline
- recalc instantly
- preview slip immediately
- tag/label ชัด
- แสดง source ของค่า
- แสดง field state (`locked`, `auto`, `manual`, `override`)

### 5.5 PDF/Payslip Agent
รับผิดชอบ:
- render payslip จาก data จริง
- สร้าง PDF ได้
- รักษารูปแบบสลิปตามองค์กร
- รองรับภาษาไทย

กฎ:
- payslip ต้องอ่านจาก `payslips` และ `payslip_items`
- ห้าม render โดยคำนวณสดจากหน้า view โดยตรง
- บันทึก snapshot ตอน finalize เพื่อกันข้อมูลเปลี่ยนย้อนหลัง

### 5.6 Audit & Compliance Agent
รับผิดชอบ:
- ทุกการแก้สำคัญต้องถูกบันทึก
- คุม history และ rollback capability ระดับข้อมูล
- log การเปลี่ยน rule/module/config

ต้อง audit:
- employee status change
- salary profile change
- payroll item change
- payslip edit/finalize
- SSO config change
- bonus rule change
- module toggle change

### 5.7 Refactor Agent
รับผิดชอบ:
- รักษาความง่ายในการแก้ระบบ
- ลด duplication
- แยก reusable service
- ตรวจ code smell

ห้าม:
- copy-paste services ตาม payroll mode แบบยาว
- ซ่อน logic ไว้ใน 1 method ขนาดยักษ์
- ทำ “quick fix” ที่ไปเพิ่ม technical debt โดยไม่จด

---

## 6) Required Modules

### 6.1 Authentication
- login
- logout
- forgot password (optional)
- role / permission

### 6.2 Employee Management
- add employee
- edit employee
- activate / deactivate
- assign payroll mode
- assign department / position
- bank info
- SSO eligibility

### 6.3 Employee Board
- card/grid list
- search
- filter by role/status/mode
- add employee
- open workspace

### 6.4 Employee Workspace
หน้าเดียวที่ user ใช้งานหลัก

ต้องมี:
- header
- month selector
- summary cards
- main payroll grid
- detail inspector
- payslip preview
- audit timeline

### 6.5 Attendance Module
- check-in/check-out style input
- late minutes
- early leave
- OT enabled
- LWOP flag

### 6.6 Work Log Module
ใช้กับ freelance และบางกรณี hybrid
- date
- work type
- qty / minutes / seconds
- layer
- rate
- amount

### 6.7 Payroll Engine
- calculate by payroll mode
- aggregate income/deductions
- support manual override
- produce payroll result snapshot

### 6.8 Rule Manager
- attendance rules
- OT rules
- bonus rules
- threshold rules
- layer rate rules
- SSO rules
- tax rules
- module toggles

### 6.9 Payslip Module
- preview
- finalize
- export PDF
- regenerate from finalized data only by permission

### 6.10 Annual Summary
แทน MACRO
- 12-month view
- employee summary
- annual totals
- export

### 6.11 Company Finance Summary
แทนสรุปรวมบัญชี
- revenue
- expenses
- P&L
- cumulative
- quarterly
- tax simulation

### 6.12 Subscription & Extra Costs
- recurring software
- fixed costs
- equipment
- dubbing
- other business expenses

---

## 7) Database Guidelines

### 7.1 Suggested Tables
ขั้นต่ำควรมี:
- `users`
- `roles`
- `permissions`
- `employees`
- `employee_profiles`
- `employee_salary_profiles`
- `employee_bank_accounts`
- `departments`
- `positions`
- `payroll_batches`
- `payroll_items`
- `payroll_item_types`
- `attendance_logs`
- `work_logs`
- `work_log_types`
- `rate_rules`
- `layer_rate_rules`
- `bonus_rules`
- `threshold_rules`
- `social_security_configs`
- `expense_claims`
- `company_revenues`
- `company_expenses`
- `subscription_costs`
- `payslips`
- `payslip_items`
- `module_toggles`
- `audit_logs`

### 7.2 Database Conventions
- table names = plural snake_case
- primary key = `id`
- foreign key = `<entity>_id`
- status flags = `status`, `is_active`
- dates = `*_date`
- durations = `*_minutes` or `*_seconds`
- amount fields = `decimal(12,2)`
- percentage fields = `decimal(5,2)` หรือ decimal fraction ตามมาตรฐานเดียวกันทั้งระบบ

### 7.3 phpMyAdmin Compatibility
ต้องออกแบบให้:
- เปิดดูตารางง่าย
- field names อ่านง่าย
- ไม่พึ่ง DB feature แปลกเกิน shared hosting
- migrations ยังทำงานได้
- query พื้นฐาน debug ได้ใน phpMyAdmin

---

## 8) Business Rules

### 8.1 Monthly Staff
สูตรหลัก:
- `total_income = base_salary + overtime_pay + diligence_allowance + performance_bonus + other_income`
- `total_deduction = cash_advance + late_deduction + lwop_deduction + social_security_employee + other_deduction`
- `net_pay = total_income - total_deduction`

### 8.2 Diligence Allowance
ค่าเริ่มต้น:
- ถ้า `late_minutes_total = 0`
- และ `lwop_days = 0`
- ให้ `diligence_allowance = 500`

ต้อง config ได้

### 8.3 OT
รองรับ:
- OT by minute
- OT by hour
- min threshold
- requires enable flag

### 8.4 Late Deduction
รองรับ:
- fixed per minute
- tier penalty
- grace period

### 8.5 LWOP
รองรับ:
- day-based deduction
- proportional salary deduction

### 8.6 Freelance Layer
สูตรมาตรฐาน:
- `duration_minutes = minute + (second / 60)`
- `amount = duration_minutes * rate_per_minute`

### 8.7 Freelance Fixed
สูตร:
- `amount = quantity * fixed_rate`

### 8.8 Youtuber Salary
เหมือน monthly staff แต่เปิดโมดูลเฉพาะที่เกี่ยวข้อง

### 8.9 Youtuber Settlement
สูตร:
- `net = total_income - total_expense`

### 8.10 Social Security (Thailand)
ระบบต้อง config ได้ตาม effective date  
อย่าฝังเลขตาย

ค่าโดยทั่วไปใน payroll mode ที่เกี่ยวข้องควรรองรับ:
- employee contribution rate
- employer contribution rate
- salary ceiling
- max monthly contribution

### 8.11 Payslip Editing Rule
แม้หน้า payroll จะปรับได้มาก  
แต่ระบบต้องแยกค่าเป็น:
- `master value`
- `monthly override`
- `manual item`
- `rule-generated`

---

## 9) Dynamic UI Behavior

### 9.1 Employee Board Flow
`Login -> Dashboard -> Employee Board -> Click Employee`

### 9.2 Payroll Entry Flow
`Employee Workspace -> Edit Grid -> Recalculate -> Preview Slip -> Save -> Finalize`

### 9.3 Grid Rules
main grid ต้องรองรับ:
- add row
- remove row
- duplicate row
- inline editing
- dropdown type/category
- auto amount calculation
- manual override
- recalculation
- source badges

### 9.4 Required UI States
ทุก field หรือ row ควรมี state ที่มองเห็นได้:
- `locked`
- `auto`
- `manual`
- `override`
- `from_master`
- `rule_applied`
- `draft`
- `finalized`

### 9.5 Detail Inspector
เมื่อคลิก row:
- show source
- show formula/rule source
- show whether monthly-only or master
- allow note/reason
- show audit history for that row

---

## 10) Payslip Requirements

### 10.1 Payslip Structure
- company header
- employee details
- month
- payment date
- account/bank info
- left: incomes
- right: deductions
- totals
- signatures

### 10.2 Critical Rule
ถ้าจะลดเงิน:
- ต้องลงฝั่ง deduction
- ห้ามลดฝั่ง income ด้วยการแก้ base salary แบบเงียบๆ

### 10.3 Snapshot Rule
เมื่อ finalize payslip:
- copy items ไป `payslip_items`
- store totals
- store rendering meta
- PDF อ้างจาก snapshot

---

## 11) Audit Requirements

### Must log
- who
- what entity
- what field
- old value
- new value
- action
- timestamp
- optional reason

### High-priority audit areas
- employee salary profile
- payroll item amount
- payslip finalize/unfinalize
- rule changes
- module toggle changes
- SSO config changes

---

## 12) Coding Standards

### 12.1 PHP / Laravel
- ใช้ service classes สำหรับ business logic
- Controllers ต้องบาง
- Validation ผ่าน FormRequest หรือ service validation
- ใช้ transactions ใน operation สำคัญ
- หลีกเลี่ยง God Class

### 12.2 Naming
- class names อ่านตรง domain เช่น `PayrollCalculationService`
- methods ตั้งชื่อแบบ action ชัด
- constants แยกเป็น enum-like class หรือ config

### 12.3 Tests
ขั้นต่ำควรมี:
- payroll mode calculation tests
- SSO calculation tests
- layer rate tests
- payslip snapshot tests
- audit logging tests

---

## 13) Folder Structure Guidance

แนะนำแบบ Laravel:
- `app/Models`
- `app/Services`
- `app/Actions`
- `app/Enums` or `app/Support`
- `app/Http/Controllers`
- `app/Http/Requests`
- `app/Policies`
- `resources/views`
- `database/migrations`
- `database/seeders`

Suggested services:
- `EmployeeService`
- `PayrollCalculationService`
- `AttendanceService`
- `WorkLogService`
- `BonusRuleService`
- `SocialSecurityService`
- `PayslipService`
- `CompanyFinanceService`
- `AuditLogService`
- `ModuleToggleService`

---

## 14) Change Management Rules

ทุกการเปลี่ยนแปลงระบบต้องถาม 5 ข้อ:
1. เปลี่ยนที่ master หรือ monthly?
2. กระทบ payroll mode ไหน?
3. กระทบ payslip / report / finance summary ไหม?
4. ต้อง migration เพิ่มไหม?
5. ต้องเพิ่ม audit coverage หรือ test ไหม?

ถ้าตอบไม่ได้ ห้าม merge แบบส่งเดช

---

## 15) Anti-Patterns (ห้ามทำ)
- ห้ามอิงตำแหน่ง row/column แบบ Excel
- ห้ามคำนวณเงินหลักใน Blade
- ห้าม hardcode legal values ถ้าเปลี่ยนได้ตามเวลา
- ห้าม copy logic เดิมไปวางหลาย service
- ห้ามใช้ชื่อพนักงานเป็น key
- ห้ามให้ report page เป็น source of truth
- ห้ามให้ PDF คำนวณเองตอน render
- ห้ามซ่อน manual override โดยไม่แสดงต่อ user

---

## 16) Minimum Deliverables

agent ที่ทำระบบต้องส่งมอบอย่างน้อย:
1. Project structure
2. Database schema
3. Migrations
4. Seed data
5. Model relationships
6. Payroll services
7. Rule manager
8. Employee workspace UI
9. Payslip builder + PDF
10. Audit logs
11. Annual summary
12. Company finance summary

---

## 17) Definition of Done

ถือว่า “เสร็จ” เมื่อ:
- เพิ่มพนักงานใหม่ได้
- ตั้ง payroll mode ได้
- กรอกเงินเดือนในหน้าเดียวได้
- คิด monthly staff ได้ถูก
- คิด freelance layer ได้ถูก
- คิด freelance fixed ได้ถูก
- คิด youtuber salary/settlement ได้ถูก
- คิด SSO ตาม config ได้
- ออก payslip PDF ได้
- ดู annual summary ได้
- ดู company P&L ได้
- มี audit logs
- แก้ระบบต่อได้ง่าย

---

## 18) Final Intent

ระบบนี้ต้องให้ประสบการณ์เหมือน:
- ใช้ spreadsheet ที่ลื่น
- แต่โครงสร้างถูกต้องแบบระบบจริง
- และแก้ต่อในอนาคตง่าย

พูดง่ายๆ:
> “ฟีลเหมือน Excel แต่ไม่โง่แบบ Excel”
