# คู่มือระบบ xHR Payroll — ฉบับสมบูรณ์

> เวอร์ชัน: 2.0 | อัปเดต: เมษายน 2026

---

## สารบัญ

1. [ภาพรวมระบบ](#1-ภาพรวมระบบ)
2. [โครงสร้างผู้ใช้และสิทธิ์การเข้าถึง](#2-โครงสร้างผู้ใช้และสิทธิ์การเข้าถึง)
3. [โมดูลพนักงาน](#3-โมดูลพนักงาน)
4. [Workspace — ศูนย์บริหารเงินเดือนรายคน](#4-workspace--ศูนย์บริหารเงินเดือนรายคน)
5. [โหมดการคำนวณเงินเดือน (Payroll Modes)](#5-โหมดการคำนวณเงินเดือน-payroll-modes)
6. [สูตรการคำนวณทุกรายการ](#6-สูตรการคำนวณทุกรายการ)
7. [กฎและ Master Rules (AttendanceRule)](#7-กฎและ-master-rules-attendancerule)
8. [ระบบการลาและการสลับวัน](#8-ระบบการลาและการสลับวัน)
9. [ระบบโบนัสประจำปี](#9-ระบบโบนัสประจำปี)
10. [การเงินบริษัท (Company Finance)](#10-การเงินบริษัท-company-finance)
11. [Work Command Center (งาน Editing)](#11-work-command-center-งาน-editing)
12. [WORK Center (ตั้งค่าประเภทงาน)](#12-work-center-ตั้งค่าประเภทงาน)
13. [รายงาน](#13-รายงาน)
14. [ตั้งค่าระบบ (Settings)](#14-ตั้งค่าระบบ-settings)
15. [Master Data](#15-master-data)
16. [Audit Log — ประวัติการเปลี่ยนแปลง](#16-audit-log--ประวัติการเปลี่ยนแปลง)
17. [การอ่าน Payslip](#17-การอ่าน-payslip)
18. [คำถามที่พบบ่อย (FAQ)](#18-คำถามที่พบบ่อย-faq)

---

## 1. ภาพรวมระบบ

xHR Payroll เป็นระบบบริหารทรัพยากรบุคคลและเงินเดือนแบบ Multi-mode รองรับพนักงานหลายประเภท ตั้งแต่พนักงานประจำรายเดือน, Freelancer คิดค่าแรงเป็นนาที, ไปจนถึง Youtuber คิดค่าตอบแทนแบบ Settlement

```
┌─────────────────────────────────────────────────────────┐
│                    xHR Payroll System                   │
├─────────────┬──────────────┬───────────────────────────┤
│  พนักงาน    │  Workspace   │   Payslip / Finalize      │
│  (CRUD)     │  (แก้ข้อมูล)  │   (สรุปจ่าย)             │
├─────────────┼──────────────┼───────────────────────────┤
│  กฎ / Rules │  โบนัส       │   การเงินบริษัท           │
│  (ตั้งค่า)   │  (Bonus)     │   (P&L)                  │
├─────────────┼──────────────┼───────────────────────────┤
│  Work Center │  ปฏิทิน     │   Audit / รายงาน          │
│  (งาน Edit) │  (Calendar)  │                           │
└─────────────┴──────────────┴───────────────────────────┘
```

### ความสัมพันธ์หลักของข้อมูล

```
Employee
 ├── SalaryProfile      (ประวัติฐานเงินเดือน)
 ├── AttendanceLog[]    (บันทึกการเข้างานรายวัน)
 ├── WorkLog[]          (บันทึกงาน/ชั่วโมง สำหรับ Freelance)
 ├── ModuleToggle[]     (เปิด/ปิดฟีเจอร์ต่อพนักงาน)
 ├── LayerRateRule[]    (อัตราค่าแรงตาม Layer)
 ├── ExpenseClaim[]     (รายการเบิก)
 ├── PerformanceRecord[](ประเมินผล)
 └── Payslip[]
      └── PayslipItem[] (Snapshot รายการเงิน)

AttendanceRule (ตารางเดียวกัน หลาย rule_type)
 ├── working_hours      (เวลาทำงาน, ชั่วโมงต่อวัน)
 ├── ot_rate            (อัตรา OT, เพดาน)
 ├── late_deduction     (กฎหักสาย)
 ├── diligence          (เบี้ยขยัน)
 └── module_defaults    (ค่า default ของโมดูล)
```

---

## 2. โครงสร้างผู้ใช้และสิทธิ์การเข้าถึง

### บทบาท (Roles)

| Role | ชื่อ | สิทธิ์หลัก |
|------|------|-----------|
| `admin` | ผู้ดูแลระบบ | เข้าถึงทุกส่วน, แก้ไขได้ทุกอย่าง |
| `hr` | HR | จัดการพนักงาน, Workspace, รายงาน (ยกเว้น Settings บางส่วน) |
| `manager` | ผู้จัดการ | ดูรายชื่อพนักงาน, ดูรายงาน, ปฏิทิน |
| `employee` | พนักงาน | เข้าได้เฉพาะ My Workspace ของตัวเอง |
| `viewer` | ผู้ดู | เข้าได้เฉพาะ My Workspace (read-only) |

### Navigation ที่แต่ละ Role เห็น

**Admin:**
- พนักงาน | WORK Center | รอบบิลเงินเดือน | การเงินบริษัท | รายงาน▾ | ตั้งค่า▾

**HR:**
- พนักงาน | WORK Center | การเงินบริษัท | รายงาน▾ (ปฏิทิน, สรุปรายปี, Audit Log)

**Manager:**
- พนักงาน | รายงาน▾ (ปฏิทินหลัก)

**Employee/Viewer:**
- My Workspace (เฉพาะของตัวเอง)

### โมดูล workspace_editing

ถ้าปิด module `workspace_editing` → พนักงานจะมองเห็นหน้า Workspace แต่ไม่สามารถแก้ไขข้อมูลใดๆ ได้ (Read-only) แม้จะเป็น owner ก็ตาม

---

## 3. โมดูลพนักงาน

### เส้นทาง: `/employees`

#### หน้ารายชื่อพนักงาน (`index`)

**ปุ่มและฟังก์ชัน:**

| ปุ่ม/ฟีเจอร์ | ผล |
|------------|-----|
| [+ เพิ่มพนักงาน] | ไปหน้า create |
| [ค้นหา] | กรองตามชื่อ, รหัส, แผนก, ตำแหน่ง |
| [Sort] | เรียงตาม ชื่อ / แผนก / เงินเดือน / รหัส / payroll_mode |
| [แก้ไข] | ไปหน้า edit |
| [Toggle Status] | สลับ active/inactive (บันทึก audit) |
| คลิกชื่อ | ไปหน้า Workspace ของพนักงานนั้น |

#### ข้อมูลที่กรอกเมื่อสร้างพนักงาน

**ข้อมูลทั่วไป:**
- รหัสพนักงาน (auto-generate หรือกรอกเอง)
- ชื่อ, นามสกุล, ชื่อเล่น
- แผนก, ตำแหน่ง
- วันที่เริ่มงาน, วันทดลองงาน, วันสิ้นสุดงาน
- วันลาพักร้อนที่มีสิทธิ์ต่อปี (`vacation_entitlement`)
- เพดานเบิกเงินล่วงหน้า (`advance_ceiling_percent`) — % ของเงินเดือน

**โหมดการจ่ายเงิน (payroll_mode):**
ดูรายละเอียดใน [ส่วนที่ 5](#5-โหมดการคำนวณเงินเดือน-payroll-modes)

**ข้อมูลเงินเดือน:**
- ฐานเงินเดือน (`base_salary`)
- วันที่มีผล (`effective_date`)

**บัญชีธนาคาร:**
- ชื่อธนาคาร, เลขบัญชี, ชื่อบัญชี

**User Account:**
- เชื่อมกับ User (email/password) สำหรับล็อกอิน
- กำหนด Role (admin/hr/manager/employee/viewer)

#### การ Generate รหัสพนักงาน

ระบบใช้ prefix อัตโนมัติตาม payroll_mode + แผนก:
- `MS001` — Monthly Staff
- `FL001` — Freelance
- `YT001` — Youtuber

---

## 4. Workspace — ศูนย์บริหารเงินเดือนรายคน

### เส้นทาง: `/workspace/{employee}/{month}/{year}`

Workspace คือหน้าหลักสำหรับแก้ไขข้อมูลการเข้างาน คำนวณเงินเดือน และอนุมัติการจ่าย ทุกอย่างของพนักงาน 1 คน ใน 1 เดือน รวมอยู่ที่นี่

### ส่วนบนของหน้า — ข้อมูลพนักงาน

แสดง: ชื่อ, รหัส, แผนก, ตำแหน่ง, payroll_mode, เงินเดือนปัจจุบัน, สถานะ Payslip

**ปุ่มนำทาง:**
- `◀ เดือนก่อน` / `เดือนหน้า ▶` — สลับเดือน
- `[คำนวณใหม่]` — บังคับ recalculate ทั้งหมด (sync metrics + คำนวณ + บันทึก)
- `[Finalize]` — สร้าง Payslip snapshot ถาวร
- `[Unfinalize]` — ยกเลิก Finalize (เฉพาะ admin)
- `[ดู Payslip]` — preview หน้าสลิปเงินเดือน
- `[โหลด PDF]` — ดาวน์โหลด PDF

### ส่วน Payroll Summary (ด้านขวา/ด้านล่าง)

แสดงผลการคำนวณล่าสุดแบบ Real-time:

| รายการ | ค่า |
|--------|-----|
| ชั่วโมงทำงานรวม | sum ของ working_minutes ÷ 60 |
| OT รวม (ชั่วโมง) | total_ot_minutes ÷ 60 |
| มาสาย (ครั้ง) | lateCount |
| ขาดงาน (วัน) | lwopDays |
| รายได้รวม | sum ของ income items |
| หักรวม | sum ของ deduction items |
| **รับสุทธิ** | **รายได้รวม − หักรวม** |

### ส่วน Attendance Grid (เฉพาะ Monthly Staff / Office Staff / Youtuber Salary)

ตารางบันทึกการเข้างานรายวัน แต่ละแถวคือ 1 วัน ประกอบด้วย:

| คอลัมน์ | คำอธิบาย |
|---------|---------|
| วันที่ | วันที่ในเดือน |
| ประเภทวัน | dropdown: workday/holiday/sick_leave/personal_leave/vacation_leave/lwop/company_holiday/ot_full_day |
| เข้างาน | เวลา check_in (HH:MM) |
| ออกงาน | เวลา check_out (HH:MM) |
| สาย (นาที) | คำนวณอัตโนมัติ: check_in − target_check_in (ถ้าสาย) |
| ออกเร็ว (นาที) | คำนวณอัตโนมัติ: target_check_out − check_out (ถ้าออกก่อน) |
| OT Checkbox | ✓ = นับ OT, ปล่อยว่าง = ไม่นับ |
| OT (นาที) | คำนวณอัตโนมัติ: check_out − target_check_out (ถ้ากด OT checkbox) |
| หมายเหตุ | ข้อความอิสระ |

**ประเภทวัน (day_type) และผลกระทบ:**

| day_type | ไทย | ผลต่อการคำนวณ |
|----------|-----|--------------|
| `workday` | วันทำงาน | นับ working_minutes, สาย, LWOP, OT ปกติ |
| `holiday` | วันหยุด (เสาร์-อาทิตย์) | ถ้ามา = ค่าทำงานวันหยุด (flat 1 วัน) + OT ถ้ากด checkbox |
| `company_holiday` | วันหยุดบริษัท | เหมือน holiday แต่ต้องเปิดสิทธิ์ swap ถ้าจะสลับ |
| `sick_leave` | ลาป่วย | ไม่หักเงิน (ถ้ามีสิทธิ์), ไม่นับ LWOP |
| `personal_leave` | ลากิจ | ไม่หักเงิน (ถ้ามีสิทธิ์) |
| `vacation_leave` | ลาพักร้อน | ตัดจาก vacation balance |
| `lwop` | ขาดงาน (ไม่รับค่าจ้าง) | หัก LWOP = base_salary ÷ weekDays × วันที่ขาด |
| `ot_full_day` | OT เต็มวัน | คิดเป็น workday + OT ทั้งวัน |
| `not_started` | ยังไม่เริ่มงาน | ไม่คำนวณ |

**การบันทึก Attendance:**
1. แก้ไขข้อมูลในตาราง
2. กด [บันทึก] → ระบบ recalculate payroll อัตโนมัติ

**หมายเหตุสำคัญ:**
- การเข้าก่อนเวลา **ไม่** เพิ่มค่าจ้าง — เงินเดือน monthly staff คงที่ ไม่ขึ้นกับ check-in
- OT จะถูกนับ **ก็ต่อเมื่อ** กด checkbox OT เท่านั้น
- วันหยุดที่มาทำงาน = **flat 1 วันมาตรฐาน** (target_minutes_per_day) ไม่ว่าจะเข้าช้าแค่ไหน

### ส่วน Work Logs (เฉพาะ Freelance / Youtuber)

สำหรับ `freelance_layer` และ `freelance_fixed`: บันทึกงานที่ทำพร้อมชั่วโมงและอัตราค่าจ้าง

สำหรับ `youtuber_settlement`: บันทึกรายการรายได้/หักค่าใช้จ่าย

### ส่วน Expense Claims (รายการเบิก)

| ช่อง | คำอธิบาย |
|------|---------|
| คำอธิบาย | รายละเอียดรายการ |
| จำนวนเงิน | บาท |
| ประเภท | `reimbursement` = เบิกคืน (income) / `advance` = เบิกล่วงหน้า (deduction) |
| วันที่ | วันที่เบิก |

- Advance มีเพดานตาม `advance_ceiling_percent` × base_salary
- ต้อง Approve ก่อนจึงจะรวมในการคำนวณ

### ส่วน Performance Records

บันทึก Tier ผลงานประจำเดือน (ใช้ในการคำนวณโบนัส)
- เลือก Tier (A/B/C/D หรือตามที่ตั้งค่าไว้)
- หมายเหตุ

### โมดูล Toggle (เปิด/ปิดต่อพนักงาน)

| โมดูล | ผลเมื่อเปิด |
|-------|-----------|
| `sso_deduction` | หักประกันสังคม (ฝั่งพนักงาน) |
| `deduct_late` | หักเงินเมื่อมาสาย |
| `deduct_early` | หักเงินเมื่อออกก่อนเวลา |
| `workspace_editing` | อนุญาตให้พนักงานแก้ไข Workspace ของตัวเอง |

### Payslip Finalize / Unfinalize

**Finalize:**
1. ระบบ lock payroll items ณ เวลานั้น → บันทึกลง `payslips` + `payslip_items`
2. สถานะเปลี่ยนเป็น `finalized`
3. บันทึกผู้ Finalize + เวลา
4. ป้องกันการแก้ไขย้อนหลัง

**Unfinalize:**
1. เฉพาะ Admin เท่านั้น
2. เปลี่ยนสถานะกลับเป็น `draft`
3. ลบ snapshot ใน `payslip_items`
4. บันทึกใน Audit Log

---

## 5. โหมดการคำนวณเงินเดือน (Payroll Modes)

### 5.1 monthly_staff / office_staff — พนักงานประจำรายเดือน

**ลักษณะ:** ได้รับเงินเดือนคงที่ + OT + เบี้ยขยัน − หักต่างๆ

**สูตรหลัก:**
```
Net Pay = base_salary
        + holiday_work_pay     (ถ้ามาวันหยุด)
        + overtime_pay         (ถ้ากด OT checkbox)
        + diligence            (ถ้าผ่านเงื่อนไข)
        − lwop_deduction       (ถ้าขาดงาน)
        − late_deduction       (ถ้ามาสาย)
        − early_leave_deduction (ถ้าออกก่อน)
        − sso_employee         (ถ้าเปิดโมดูล)
```

**Grid ที่แสดง:** `workspace/partials/attendance-grid.blade.php`

---

### 5.2 freelance_layer — Freelance คิดตาม Layer

**ลักษณะ:** คิดค่าแรงต่อนาทีตาม "Layer" ของงาน (Layer 1/2/3/... มีอัตราต่างกัน)

**สูตรหลัก:**
```
สำหรับแต่ละ Work Log:
  amount = duration_minutes × resolved_rate

Total Income = sum(amount ของทุก work log ที่เปิดอยู่)
```

**การหา resolved_rate:**
1. ถ้า `pricing_mode = "custom"` → ใช้ `custom_rate`
2. ถ้ามี `pricing_template_label` และ `rate > 0` → ใช้ `rate`
3. ค้นหา `LayerRateRule` ที่ `layer_from ≤ layer ≤ layer_to` → ใช้ `rate_per_minute`
4. ถ้าไม่มีเลย → 0

**Grid ที่แสดง:** `workspace/partials/freelance-layer-grid.blade.php`

คอลัมน์: วันที่ | ประเภทงาน | Layer | ชั่วโมง:นาที:วินาที | อัตรา | จำนวนเงิน

---

### 5.3 freelance_fixed — Freelance คิดตาม Quantity

**ลักษณะ:** คิดค่าแรงตาม จำนวน × ราคาต่อหน่วย

**สูตรหลัก:**
```
สำหรับแต่ละ Work Log:
  amount = quantity × rate

Total Income = sum(amount ของทุก work log ที่เปิดอยู่)
```

**Grid ที่แสดง:** `workspace/partials/freelance-fixed-grid.blade.php`

คอลัมน์: วันที่ | ประเภทงาน | จำนวน | ราคา/หน่วย | จำนวนเงิน

---

### 5.4 youtuber_salary — Youtuber รายเดือน

**ลักษณะ:** คิดเหมือน `monthly_staff` (เงินเดือนคงที่) + มีบันทึกงาน Editing เพื่อดูสถิติ

**สูตร:** เหมือน monthly_staff ทุกอย่าง

**Grid ที่แสดง:** `workspace/partials/youtuber-salary-grid.blade.php`

---

### 5.5 youtuber_settlement — Youtuber คิดตาม Settlement

**ลักษณะ:** ไม่มีเงินเดือนคงที่ — รายได้/หักค่าใช้จ่ายมาจาก Work Log แต่ละรายการ

**สูตรหลัก:**
```
สำหรับแต่ละ Work Log:
  ถ้า entry_type = "income"   → บวกเข้า total_income
  ถ้า entry_type = "deduction" → บวกเข้า total_deduction

Net Pay = total_income − total_deduction
```

**Grid ที่แสดง:** `workspace/partials/youtuber-settlement-grid.blade.php`

---

## 6. สูตรการคำนวณทุกรายการ

### 6.1 Minute Rate (อัตราต่อนาที)

```
weekDaysCount = จำนวนวันจันทร์-ศุกร์ในเดือนนั้น

minuteRate = base_salary ÷ (weekDaysCount × target_minutes_per_day)
```

**ตัวอย่าง:**
- base_salary = 15,000
- weekDays เมษายน 2026 = 22 วัน
- target_minutes_per_day = 540 (9 ชั่วโมง)
- minuteRate = 15,000 ÷ (22 × 540) = 15,000 ÷ 11,880 = **1.2626 บาท/นาที**

---

### 6.2 ค่าทำงานวันหยุด (Holiday Work Pay)

เงื่อนไข: `day_type IN (holiday, company_holiday)` AND มี check_in + check_out

```
holidayWorkPay = targetMinutesPerDay × minuteRate × holiday_regular_multiplier_monthly
```

**ค่า default:**
- `targetMinutesPerDay` = 540 นาที (9 ชั่วโมง)
- `holiday_regular_multiplier_monthly` = 1.0 (configurable ใน `ot_rate` rule)

**ตัวอย่าง:**
- เงินเดือน 15,000 | minuteRate = 1.2626 | multiplier = 1.0
- holidayWorkPay = 540 × 1.2626 × 1.0 = **฿681.80 ต่อวัน**

**หมายเหตุ:** ค่าทำงานวันหยุดเป็น "เงินพิเศษ" บนของเงินเดือน ไม่ใช่แทนวันทำงาน

---

### 6.3 OT (Overtime Pay)

**OT วันทำงานปกติ:**
```
workdayOtPay = workday_ot_minutes × minuteRate × rate_multiplier_workday
               (default: × 1.5)
```

**OT วันหยุด:**
```
holidayOtPay = holiday_ot_minutes × minuteRate × rate_multiplier_holiday
               (default: × 3.0)
```

**เพดาน OT:**
- รายสัปดาห์: ไม่เกิน `weekly_ot_limit_hours` (default: 36 ชั่วโมง) = 2,160 นาที/สัปดาห์
- รายเดือน: ไม่เกิน `max_ot_hours` (default: 40 ชั่วโมง) = 2,400 นาที/เดือน
- ถ้าเกินรายเดือน → หักจาก holiday OT ก่อน (เพราะ multiplier สูงกว่า)

**การคำนวณ OT จาก check-in/out:**
```
ot_minutes = check_out - target_check_out    (ถ้า check_out > target_check_out)
             (เฉพาะเมื่อกด OT checkbox เท่านั้น)
```

---

### 6.4 เบี้ยขยัน (Diligence Allowance)

**แบบ Flat:**
```
ถ้า lateCount = 0 AND lwopDays = 0 (หรือตามเงื่อนไขที่ตั้ง)
  diligenceAmount = amount   (เช่น 500 บาท)
```

**แบบ Tier (use_tiers = true):**
```
เรียง tier จาก amount มากไปน้อย, หาอันแรกที่ผ่านเงื่อนไข:
  lateCount ≤ tier.late_count_max  AND  lwopDays ≤ tier.lwop_days_max
  → diligenceAmount = tier.amount
```

**ตัวอย่าง Tier:**

| late_count_max | lwop_days_max | amount |
|---------------|--------------|--------|
| 0 | 0 | 500 |
| 2 | 1 | 300 |
| 5 | 3 | 100 |

---

### 6.5 LWOP Deduction (หักขาดงาน)

```
lwopDeduction = (base_salary ÷ weekDaysCount) × lwopDays
```

**ตัวอย่าง:**
- base_salary = 15,000 | weekDays = 22 | lwopDays = 2
- lwopDeduction = (15,000 ÷ 22) × 2 = 681.82 × 2 = **฿1,363.64**

---

### 6.6 Late Deduction (หักมาสาย)

**แบบ per_minute:**
```
lateDeduction = max(0, totalLateMinutes - grace_minutes) × rate_per_minute
```

**แบบ tiered:**
```
สำหรับแต่ละ tier → นำนาทีที่อยู่ใน range มาคิด × rate_per_minute ของ tier นั้น
```

---

### 6.7 Early Leave Deduction (หักออกก่อน)

```
earlyLeaveDeduction = totalEarlyLeaveMinutes × minuteRate
```

(ใช้ minuteRate เดียวกับการคำนวณ OT)

---

### 6.8 ประกันสังคม (Social Security)

```
cappedSalary = min(base_salary, salary_ceiling)
               (ceiling ปัจจุบัน = 15,000 บาท)

ssoEmployee = min(cappedSalary × employee_rate / 100, max_contribution)
              (rate ปัจจุบัน = 5%, max = 750 บาท)

ssoEmployer = min(cappedSalary × employer_rate / 100, max_contribution)
              (ไม่หักจากพนักงาน — เป็นค่าใช้จ่ายบริษัท)
```

**ตัวอย่าง:**
- base_salary = 20,000 → capped = 15,000
- ssoEmployee = min(15,000 × 5% / 100, 750) = min(750, 750) = **฿750**

---

### 6.9 สรุปยอดสุทธิ

```
totalIncome     = sum ของ items ที่ category = "income"
totalDeduction  = sum ของ items ที่ category = "deduction"
netPay          = totalIncome − totalDeduction
```

---

### 6.10 Payroll Items และ source_flag

แต่ละรายการใน payroll มี `source_flag` กำกับ:

| source_flag | ความหมาย | พฤติกรรมเมื่อ recalculate |
|------------|---------|--------------------------|
| `auto` | คำนวณอัตโนมัติ | ถูก overwrite ทุกครั้ง |
| `master` | มาจาก base_salary | ถูก overwrite ทุกครั้ง |
| `manual` | กรอกเอง | **ถูกเก็บไว้**, ไม่ถูก overwrite |
| `override` | เขียนทับด้วยมือ | **ถูกเก็บไว้**, ไม่ถูก overwrite |
| `rule_applied` | มาจาก Rule | ถูก overwrite ทุกครั้ง |

**นัยสำคัญ:** ถ้าต้องการ "ล็อค" ยอดใดยอดหนึ่ง ให้เปลี่ยน source_flag เป็น `manual` หรือ `override`

---

## 7. กฎและ Master Rules (AttendanceRule)

### เส้นทาง: `/settings/rules`

กฎทั้งหมดเก็บในตาราง `attendance_rules` ด้วย `rule_type` ต่างกัน แต่ละ type มีเพียง 1 active record

### 7.1 working_hours — เวลาทำงาน

| ช่อง | ค่า default | ความหมาย |
|-----|------------|---------|
| `target_check_in` | 09:30 | เวลาเข้างานมาตรฐาน (ใช้คำนวณสาย) |
| `target_check_out` | 18:30 | เวลาออกงานมาตรฐาน (ใช้คำนวณ OT) |
| `target_minutes_per_day` | 540 | นาทีทำงานมาตรฐานต่อวัน (9 ชม) |
| `lunch_break_minutes` | 60 | นาทีพักกลางวัน (หักจาก working_minutes workday) |
| `standard_holidays` | [0, 6] | Day of week ที่เป็นวันหยุด (0=อาทิตย์, 6=เสาร์) |
| `allow_company_holiday_swap` | false | อนุญาตให้ swap วันหยุดตามประเพณีหรือไม่ |

### 7.2 ot_rate — กฎ OT

| ช่อง | ค่า default | ความหมาย |
|-----|------------|---------|
| `rate_multiplier_workday` | 1.5 | ตัวคูณ OT วันทำงาน (กม.แรงงาน §61) |
| `rate_multiplier_holiday` | 3.0 | ตัวคูณ OT วันหยุด (กม.แรงงาน §63) |
| `holiday_regular_multiplier_monthly` | 1.0 | ตัวคูณค่าวันหยุดปกติ monthly staff (§62) |
| `enable_holiday_legal_split` | true | แยก holiday regular / holiday OT ออกจากกัน |
| `max_ot_hours` | 40 | เพดาน OT รายเดือน (ชั่วโมง) |
| `weekly_ot_limit_hours` | 36 | เพดาน OT รายสัปดาห์ (กม.แรงงาน §24) |

### 7.3 late_deduction — กฎหักสาย

**แบบ per_minute:**
```json
{
  "type": "per_minute",
  "grace_minutes": 5,
  "rate_per_minute": 2.5
}
```

**แบบ tiered:**
```json
{
  "type": "tiered",
  "grace_minutes": 0,
  "tiers": [
    { "from_minutes": 1,  "to_minutes": 30, "rate_per_minute": 2.0 },
    { "from_minutes": 31, "to_minutes": 60, "rate_per_minute": 3.5 }
  ]
}
```

### 7.4 diligence — เบี้ยขยัน

**แบบ flat:**
```json
{
  "use_tiers": false,
  "require_zero_late": true,
  "require_zero_lwop": true,
  "amount": 500
}
```

**แบบ tiered:**
```json
{
  "use_tiers": true,
  "tiers": [
    { "late_count_max": 0, "lwop_days_max": 0, "amount": 500 },
    { "late_count_max": 3, "lwop_days_max": 1, "amount": 300 }
  ]
}
```

### 7.5 module_defaults — ค่า default โมดูล

ใช้เมื่อสร้างพนักงานใหม่ — ระบบจะ set ModuleToggle ตาม config นี้

```json
{
  "enable_overtime": true,
  "enable_diligence": true,
  "default_sso_deduction": true,
  "default_deduct_late": true,
  "default_deduct_early": false
}
```

---

## 8. ระบบการลาและการสลับวัน

### เส้นทาง: `/leave`

### 8.1 ประเภทการลา

| ประเภท | code | การหักเงิน |
|--------|------|-----------|
| ลาป่วย | `sick_leave` | ไม่หัก (ในสิทธิ์) |
| ลากิจ | `personal_leave` | ไม่หัก (ในสิทธิ์) |
| ลาพักร้อน | `vacation_leave` | ไม่หัก (ตัดจาก balance) |
| ขาดงาน | `lwop` | หัก LWOP |

**Vacation Balance:**
```
balance = vacation_entitlement − วันที่ใช้ vacation_leave ในปีนั้น
```

### 8.2 Flow การลา (Leave Request)

```
พนักงาน → ส่งคำขอ (status: pending)
             ↓
Admin Review → approved / rejected
             ↓
ถ้า approved → system เปลี่ยน attendance_log.day_type เป็นประเภทนั้น
```

### 8.3 Flow การสลับวัน (Day Swap Request)

**คือ:** พนักงานมาทำงานวันหยุด แล้วหยุดแทนในวันทำงาน

```
พนักงาน → ส่งคำขอสลับ (work_date ↔ off_date)
              ↓
Admin Review → validate ก่อน:
  1. work_date ต้องเป็น holiday/company_holiday
  2. off_date ต้องเป็น workday/ot_full_day
  3. ถ้า company_holiday → ต้องเปิด allow_company_holiday_swap ใน Rules
  4. ต้องไม่ทำงานติดต่อกันเกิน 6 วัน
              ↓
ถ้า approved → ระบบเปลี่ยน:
  attendance_log[work_date].day_type = 'workday'
  attendance_log[off_date].day_type = 'holiday'
  is_swapped_day = true (ทั้งสองวัน)
```

**ข้อจำกัดปัจจุบัน:**
- ยกเลิก swap หลัง approved ไม่ได้ผ่าน UI — ต้องแก้ grid เอง
- Admin สร้าง swap แบบ bypass โดยไม่ผ่าน validation ได้ (bug รู้แล้ว)

---

## 9. ระบบโบนัสประจำปี

### เส้นทาง: `/settings/bonus`

### 9.1 Bonus Cycle (รอบโบนัส)

โบนัสแบ่งเป็น Cycle ตามรอบการจ่าย:

| cycle_period | รอบ | ลักษณะ |
|-------------|-----|--------|
| `january` | มกราคม | โบนัสสิ้นปี (เต็ม) |
| `june` | มิถุนายน | โบนัสกลางปี (ครึ่งหนึ่ง) |
| `december` | ธันวาคม | โบนัสปลายปี |

**พารามิเตอร์หลักของ Cycle:**

| ช่อง | ความหมาย |
|-----|---------|
| `max_allocation` | ยอดโบนัสสูงสุดที่จ่ายได้ (บาท) |
| `june_max_ratio` | สัดส่วนสูงสุดของรอบ June (เช่น 0.5 = 50%) |
| `june_scale_months` | เดือนที่ใช้ scale สำหรับรอบ June |
| `full_scale_months` | เดือนที่ใช้ scale สำหรับรอบปกติ |
| `absent_penalty_per_day` | หักต่อวันขาดงาน (บาท) |
| `late_penalty_per_occurrence` | หักต่อครั้งที่มาสาย (บาท) |
| `leave_free_days` | วันลาที่ไม่โดนหัก |
| `leave_penalty_rate` | อัตราหักถ้าลาเกิน free days |

### 9.2 สูตรโบนัส

```
Step 1: เลือกเดือนที่นับ (BonusCycleSelectedMonth)
         → ดึง PerformanceRecord ของแต่ละเดือน → หา Tier

Step 2: คำนวณ base_reference
         = ค่าเฉลี่ย base_salary ในเดือนที่เลือก

Step 3: คำนวณ tier_multiplier
         = ค่าเฉลี่ย multiplier ของ PerformanceTier ในเดือนที่เลือก

Step 4: tier_adjusted_bonus
         = base_reference × tier_multiplier

Step 5: unlock_percentage
         = months_after_probation ÷ full_scale_months × 100
         (จำกัดที่ 100%)

Step 6: attendance_adjustment
         = − (absent_days × absent_penalty_per_day)
         − (late_count × late_penalty_per_occurrence)
         − (excess_leave_days × leave_penalty_rate)

Step 7: final_bonus_net
         = tier_adjusted_bonus × (unlock_percentage / 100) + attendance_adjustment
         (ไม่ต่ำกว่า 0)
```

### 9.3 สถานะ Bonus Calculation

```
draft → calculated → approved → paid
          ↓
       rejected
```

### 9.4 Performance Tiers

ตั้งค่าได้ใน Master Data:

| Tier | Multiplier ตัวอย่าง |
|------|-------------------|
| S | 2.0 |
| A | 1.5 |
| B | 1.0 |
| C | 0.7 |
| D | 0.3 |

---

## 10. การเงินบริษัท (Company Finance)

### เส้นทาง: `/company/finance`

### 10.1 Dashboard P&L รายเดือน

แสดงผลสรุปรายรับ-รายจ่ายของบริษัทในรูป P&L:

```
รายรับรวม    = sum(CompanyRevenue ของเดือนนั้น)
รายจ่ายรวม   = sum(CompanyExpense) + sum(SubscriptionCost) + sum(Payslip.net_pay)
กำไรสุทธิ    = รายรับรวม − รายจ่ายรวม
```

### 10.2 ประเภทรายการ

**Revenue (รายรับ):**
- source: แหล่งรายได้
- description: รายละเอียด
- amount: จำนวนเงิน

**Expense (รายจ่าย):**
- category: หมวดค่าใช้จ่าย
- description: รายละเอียด
- amount: จำนวนเงิน

**Subscription (ค่า Subscription):**
- service_name: ชื่อบริการ
- amount: รายเดือน

---

## 11. Work Command Center (งาน Editing)

### เส้นทาง: `/work`

ระบบจัดการงาน Video Editing สำหรับทีม Youtuber/Content Creator

### 11.1 สถานะงาน (Job Pipeline)

```
assigned → started → review_ready → final
```

| สถานะ | ความหมาย | ปุ่มที่กดได้ |
|-------|---------|-----------|
| `assigned` | มอบหมายแล้ว รอเริ่ม | [เริ่มงาน] |
| `started` | กำลังทำ | [ส่งตรวจ] |
| `review_ready` | รอตรวจ | [Finalize] |
| `final` | เสร็จสิ้น | — |

### 11.2 ข้อมูลงาน

| ช่อง | ความหมาย |
|-----|---------|
| job_name | ชื่องาน |
| game | เกม/project ที่เกี่ยวข้อง |
| assigned_to | พนักงานที่รับผิดชอบ |
| deadline_days | กำหนดส่งภายในกี่วัน |
| layer_count | จำนวน layer (ใช้คิดค่าแรง freelance_layer) |
| video_duration | ความยาววิดีโอ (นาที:วินาที) |

### 11.3 ความสัมพันธ์กับ Payroll

- Youtuber ที่มี Editing Job ที่ `final` ในเดือนนั้น → ระบบนับเวลาวิดีโอรวมไว้ใน `performanceSummary`
- Freelance Layer: งาน `final` สร้าง Work Log อัตโนมัติได้ (ตาม layer ของงาน)

---

## 12. WORK Center (ตั้งค่าประเภทงาน)

### เส้นทาง: `/settings/works`

### 12.1 Work Log Type (ประเภทงาน)

กำหนดประเภทงานที่พนักงาน Freelance สามารถบันทึกได้ใน Work Log

| ช่อง | ความหมาย |
|-----|---------|
| name | ชื่อประเภทงาน (แสดงใน dropdown) |
| code | รหัส (unique) |
| payroll_mode | mode ที่ใช้ได้ |
| is_active | เปิด/ปิด |

### 12.2 Work Assignment (มอบหมายประเภทงาน)

กำหนดว่าพนักงานคนไหนสามารถบันทึกประเภทงานอะไรได้บ้าง

---

## 13. รายงาน

### 13.1 ปฏิทินหลัก (`/calendar`)

แสดงมุมมองรายสัปดาห์ รวม:
- วันหยุดบริษัท (company holidays)
- คำขอลา (สี: pending=เหลือง, approved=เขียว, rejected=แดง)
- คำขอสลับวัน
- งาน Editing (Recording/Editing)
- บันทึกการเข้างาน

**การนำทาง:** ◀ สัปดาห์ก่อน | สัปดาห์หน้า ▶

### 13.2 สรุปรายปี (`/annual`)

แสดงรายงานเงินเดือนทั้งปีแบบ Matrix:
- แถว = พนักงาน
- คอลัมน์ = เดือน (ม.ค. − ธ.ค.)
- ค่าในช่อง = net_pay

พร้อมสรุป: รายได้รวม, หักรวม, สุทธิรวม ต่อพนักงาน

### 13.3 รอบบิลเงินเดือน (`/payroll-batches`)

**Index:** รายการ Batch ทุกเดือน พร้อมสถานะ (draft/finalized) และยอดรวม

**Detail (`/payroll-batches/{year}/{month}`):**
- ตาราง payroll items ของทุกพนักงานในเดือนนั้น
- แสดง income items / deduction items / net pay แยกต่อคน

---

## 14. ตั้งค่าระบบ (Settings)

### 14.1 Rules (`/settings/rules`) — เฉพาะ Admin

ดูและแก้ไขกฎทั้งหมด ดูรายละเอียดใน [ส่วนที่ 7](#7-กฎและ-master-rules-attendancerule)

### 14.2 Bonus Manager (`/settings/bonus`)

- สร้าง/แก้ไข Bonus Cycle
- กดคำนวณโบนัสทีละคน หรือ Batch ทั้งหมด
- Approve และ Mark ว่าจ่ายแล้ว

### 14.3 Company Holidays (`/settings/rules` — ส่วน Holidays)

- เพิ่มวันหยุดบริษัท (ชื่อ + วันที่)
- โหลดวันหยุดตามกฎหมายไทยอัตโนมัติ
- ลบวันหยุด

วันหยุดที่เพิ่มจะกลายเป็น `company_holiday` ใน attendance grid โดยอัตโนมัติ

---

## 15. Master Data

### เส้นทาง: `/settings/master-data`

### 15.1 Payroll Item Types

รายการ code ของ payroll items ที่ระบบรู้จัก:

| code | ไทย | category |
|------|-----|---------|
| `base_salary` | ฐานเงินเดือน | income |
| `holiday_work_pay` | ค่าทำงานวันหยุด | income |
| `overtime` | ค่าล่วงเวลา | income |
| `diligence` | เบี้ยขยัน | income |
| `cash_advance` | เงินหักล่วงหน้า | deduction |
| `lwop` | ขาดงาน | deduction |
| `late_deduction` | มาสาย | deduction |
| `early_leave_deduction` | ออกเร็ว | deduction |
| `sso_employee` | ประกันสังคม | deduction |

### 15.2 Departments (แผนก)

- ชื่อ, รหัส, is_active

### 15.3 Positions (ตำแหน่ง)

- ชื่อ, รหัส, แผนก, is_active

### 15.4 Layer Rate Rules (อัตราค่าแรงตาม Layer)

สำหรับพนักงาน `freelance_layer` — กำหนดอัตราต่อนาทีตาม Layer ของงาน:

| ช่อง | ความหมาย |
|-----|---------|
| employee_id | พนักงาน (ต่อคน) |
| layer_from | Layer เริ่มต้น (เช่น 1) |
| layer_to | Layer สิ้นสุด (เช่น 3) |
| rate_per_minute | บาท/นาที |
| effective_date | วันที่มีผล |

**ตัวอย่าง:**
- Layer 1-2: 2.50 บาท/นาที
- Layer 3-5: 3.00 บาท/นาที
- Layer 6+: 3.50 บาท/นาที

### 15.5 Job Stages (ขั้นตอนงาน)

Pipeline สำหรับ Editing Job — กำหนดลำดับและสี

### 15.6 Games (เกม/Project)

รายชื่อ Project/เกมที่ Editing Job สามารถเลือกได้

---

## 16. Audit Log — ประวัติการเปลี่ยนแปลง

### เส้นทาง: `/audit-logs`

ทุก action สำคัญในระบบจะถูกบันทึก:

| action | ทริกเกอร์เมื่อ |
|--------|-------------|
| `created` | สร้าง record ใหม่ |
| `updated` | แก้ไข record |
| `deleted` | ลบ record |
| `attendance_updated` | บันทึก attendance batch |
| `attendance_row_updated` | แก้ไข attendance แถวเดียว |
| `payroll_recalculated` | กดคำนวณใหม่ |
| `payslip_finalized` | Finalize payslip |
| `payslip_unfinalized` | Unfinalize payslip |
| `module_toggled` | เปิด/ปิดโมดูล |
| `advance_ceiling_updated` | เปลี่ยนเพดานเบิก |
| `approved` | อนุมัติ claim/leave/swap |

**ข้อมูลที่บันทึก:**
- user_id (ใครทำ)
- auditable_type + auditable_id (กระทบ model/record ไหน)
- field_name (ช่องที่เปลี่ยน)
- old_value → new_value
- description (บริบทเพิ่มเติม)

**การกรอง:**
- Entity type (employees/attendance_logs/payslips ฯลฯ)
- Action (created/updated/deleted)
- User ที่กระทำ
- ช่วงวันที่

---

## 17. การอ่าน Payslip

### เส้นทาง: `/payslip/{employee}/{month}/{year}/preview`

### 17.1 ส่วนต่างๆ ของ Payslip

**Header:**
- ชื่อบริษัท, เดือน/ปี
- ชื่อ-นามสกุลพนักงาน, รหัส, แผนก, ตำแหน่ง

**สรุปการทำงาน:**
- ชั่วโมงทำงานรวม
- OT (ชม.)
- มาสาย (ครั้ง/นาที)
- ขาดงาน (วัน)
- วันลาพักร้อนคงเหลือ

**รายการรายได้ (Income):**

| รายการ | จำนวนเงิน | หมายเหตุ |
|--------|---------|---------|
| ฐานเงินเดือน | X,XXX.XX | — |
| ค่าทำงานวันหยุด | X,XXX.XX | วันที่ทำงาน |
| ค่าล่วงเวลา | X,XXX.XX | รายละเอียดวัน |
| เบี้ยขยัน | X,XXX.XX | — |
| รายได้อื่นๆ | X,XXX.XX | — |

**รายการหัก (Deduction):**

| รายการ | จำนวนเงิน | หมายเหตุ |
|--------|---------|---------|
| ขาดงาน | X,XXX.XX | — |
| มาสาย | X,XXX.XX | รายละเอียดวัน |
| ออกก่อน | X,XXX.XX | — |
| ประกันสังคม | XXX.XX | — |
| หักอื่นๆ | X,XXX.XX | — |

**สรุป:**
- รายได้รวม
- หักรวม
- **รับสุทธิ (Net Pay)**

**YTD (Year-to-Date):**
- สะสมรายได้, หัก, สุทธิ ตั้งแต่ต้นปีถึงเดือนนี้

### 17.2 Payslip Status

| สถานะ | ความหมาย | แก้ไขได้? |
|-------|---------|---------|
| `draft` | ยังไม่ Finalize | ได้ |
| `finalized` | Finalize แล้ว | ไม่ได้ (ต้อง Unfinalize ก่อน) |

---

## 18. คำถามที่พบบ่อย (FAQ)

**Q: ทำไมยอดเงินเดือนไม่เปลี่ยนทั้งที่แก้ attendance แล้ว?**
A: ต้องกด [บันทึก] หรือ [คำนวณใหม่] ระบบไม่ auto-save แบบ real-time

**Q: เข้างานเร็วกว่า 09:30 ได้เงินมากขึ้นไหม?**
A: ไม่ — เงินเดือน monthly staff เป็น fixed ไม่ขึ้นกับเวลา check-in ยิ่งเข้าเร็วก็ได้เท่าเดิม

**Q: วันหยุดที่มาทำงานได้เท่าไหร่?**
A: ได้ค่าทำงานวันหยุด = 1 วันมาตรฐาน (540 นาที × minuteRate × multiplier) ไม่ว่าจะเข้าช้า/เร็วแค่ไหน + OT ถ้ากด checkbox

**Q: จะเพิ่มรายการพิเศษใน payslip (โบนัสพิเศษ, หักพิเศษ) ทำยังไง?**
A: ใน Workspace → แก้ไข Payroll Items → เพิ่มรายการใหม่หรือแก้ amount → เปลี่ยน source_flag เป็น `manual` เพื่อไม่ให้ถูก overwrite ตอน recalculate

**Q: ถ้า Finalize แล้วพบว่าตัวเลขผิด ทำยังไง?**
A: Admin กด [Unfinalize] → แก้ข้อมูล → กด [คำนวณใหม่] → [Finalize] ใหม่

**Q: ประกันสังคมสูงสุดเท่าไหร่?**
A: 750 บาท/เดือน (ฐาน cap ที่ 15,000 × 5%) ตาม SocialSecurityConfig

**Q: OT สูงสุดต่อเดือนเท่าไหร่?**
A: 40 ชั่วโมง/เดือน (2,400 นาที) และ 36 ชั่วโมง/สัปดาห์ (2,160 นาที) — configurable ใน ot_rate Rule

**Q: เบี้ยขยันไม่ได้รับทั้งที่ไม่ขาดงาน ไม่สาย?**
A: ตรวจสอบว่า enable_diligence = true ใน module_defaults Rule และ module `diligence` ไม่ได้ถูกปิดที่ระดับพนักงาน

**Q: ลาพักร้อนคงเหลือดูจากไหน?**
A: ใน Workspace → ส่วน Vacation Balance หรือบน Payslip preview

**Q: Day Swap ที่ approved แล้วยกเลิกได้ไหม?**
A: ปัจจุบันยังไม่รองรับ Revert อัตโนมัติ — ต้องแก้ attendance grid ด้วยตนเองทั้งสองวัน

---

## ภาคผนวก A — ตาราง day_type ครบถ้วน

| day_type | สี badge | นับ working_minutes | นับ LWOP | ได้ holiday pay | สามารถ OT |
|----------|---------|-------------------|---------|---------------|---------|
| workday | เขียว | ✓ | — | — | ✓ (workday rate) |
| holiday | ส้ม | — | — | ✓ (ถ้ามา) | ✓ (holiday rate) |
| company_holiday | ม่วง | — | — | ✓ (ถ้ามา) | ✓ (holiday rate) |
| sick_leave | ฟ้า | — | — | — | — |
| personal_leave | เหลือง | — | — | — | — |
| vacation_leave | teal | — | — | — | — |
| ot_full_day | indigo | ✓ | — | — | ✓ (workday rate) |
| lwop | แดง | — | ✓ | — | — |
| not_started | เทา | — | — | — | — |

---

## ภาคผนวก B — ความสัมพันธ์ตาราง DB (ERD สรุป)

```
users ──────────── employees
                       │
          ┌────────────┼────────────────┐
          │            │                │
   salary_profiles  attendance_logs  work_logs
          │            │                │
          │      ┌─────┤           payroll_items
          │      │     │
          │  leave_req swap_req    payroll_batches
          │                             │
          └──────────────────── payslips
                                    │
                              payslip_items
```

---

*จัดทำโดย xHR System | ปรับปรุงล่าสุด 20 เมษายน 2026*
