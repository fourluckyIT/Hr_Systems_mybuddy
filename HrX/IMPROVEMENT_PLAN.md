# xHR — Improvement Plan
**Version:** 1.0 | **Date:** 2026-04-19 | **Owner:** PAK

จุดประสงค์: ทำระบบ xHR ให้เสถียร ปลอดภัย และใช้งานลื่นไหล ก่อนขยาย feature เพิ่ม — แบ่งเป็น 4 เฟส เรียงตามความสำคัญ "เร่งด่วน → ใช้ดี → สวยงาม → เพิ่มของ"

---

## Phase overview

| Phase | Theme | Effort | Status |
|---|---|---|---|
| **0** | Emergency survival | 30 นาที | ✅ พร้อม apply (ดู `fixes/APPLY.md`) |
| **1** | Stabilize core | 1–2 สัปดาห์ | ⏳ รอ apply Phase 0 ก่อน |
| **2** | UX / UI polish | 2–3 สัปดาห์ | 📋 plan พร้อม |
| **3** | Missing features | 4–6 สัปดาห์ | 📋 backlog |
| **4** | Long-term architecture | เดือน+ | 💭 optional |

---

## Phase 0 — Emergency survival ✅

**เป้าหมาย:** ปิดรูที่ทำให้ระบบ "พัง" หรือ "โดนแฮ็ก" ได้ในวันนี้

- ✅ phpMyAdmin lockdown (nginx IP allow-list + basic auth)
- ✅ BUG-02: recalc ไม่ลบ manual/override rows
- ✅ BUG-08: finalize payslip ภายใน transaction + unique index
- ✅ PHPUnit tests สำหรับ 3 ข้อนี้

**Success criteria:**
- Public scan ไม่เจอ phpmyadmin
- Admin ปรับเงินเดือนเอง → recalc → ตัวเลขยังอยู่
- 2 admin กด finalize พร้อมกัน → ได้ payslip เดียว ไม่ซ้ำ

---

## Phase 1 — Stabilize core (1–2 สัปดาห์)

**เป้าหมาย:** ทำให้ payroll/bonus/finance คำนวณถูกต้อง 100% และตามกฎหมายไทย

### 1.1 Apply fixes ที่เหลือจาก `bugs-and-fixes.md`
| Bug | สิ่งที่ทำ | ไฟล์ |
|---|---|---|
| BUG-01 | OT cap → 36h/สัปดาห์ (กฎหมายแรงงาน §26) | `PayrollGuardAgent` |
| BUG-03 | dedupe ด้วย `source_ref_id` แทน label+amount | migration + `PayrollGuardAgent` |
| BUG-04/05 | bonus: bounds units ถูก + probation ineligible | `BonusCalculationService` |
| BUG-06 | rounding นโยบายเดียว (banker's) + tolerance max(1%, ฿10) | global helper |
| BUG-07 | net pay = 0 เป็น WARN ไม่ใช่ BLOCK | `PayrollGuardAgent` |
| BUG-11 | finance reconciler ใช้ `firstOrCreate` | `FinanceReconcilerAgent` |
| BUG-12 | editing job states: `cancelled`, `rejected` | `EditingJobService` |

### 1.2 Security hardening
- BUG-18: เปิด 2FA ให้ admin/owner (Laravel Fortify)
- BUG-19: Employee model `$guarded` ครบ
- BUG-20: dompdf `is_remote_enabled=false`
- BUG-17: CI grep gate สำหรับ `{!!` ใน Blade

### 1.3 ทดสอบครบก่อนขึ้น prod
- รัน PHPUnit ทั้งหมด
- Manual test checklist จาก `APPLY.md` ข้อ 9

**Success criteria:**
- payslip ทุกใบที่ finalize → ตัวเลขตรงกับ Excel verify
- compliance scan ไม่เจอ violation ที่ควรหาเจอ
- หลุดจาก OWASP Top 10 สำหรับ bonus/payroll routes

---

## Phase 2 — UX / UI polish (2–3 สัปดาห์)

**เป้าหมาย:** admin/owner ใช้งานได้เร็ว ไม่พลาด ไม่สับสน

### 2.1 Design system (1 สัปดาห์)
- กำหนด design tokens: สี, spacing, typography (ไทย + อังกฤษ)
- Layout หลัก: sidebar + topbar + breadcrumbs
- Component library: Button, Table, Form, Alert, Modal, Tabs, Stepper
- Thai-first typography (font: Sarabun / IBM Plex Thai)
- Responsive: tablet เป็นอย่างน้อย (โน้ตบุ๊ค admin ใช้งาน)

### 2.2 Priority screens (ตามลำดับความเจ็บ)

**Admin Dashboard — "Month in review"**
- การ์ด 4 ช่อง: Open jobs / Attendance gaps / Pending payslips / Compliance alerts
- Click-through ไปแต่ละหน้า
- แสดง progress bar ของเดือนปัจจุบัน (workflow ไปถึงขั้นไหน)

**Attendance Grid (Workspace)**
- Inline A4 AttendanceVerifier WARN/ERROR แสดงในแต่ละ cell
- Keyboard navigation (Tab/Enter/Arrow)
- Paste จาก Excel (parse CSV ใน clipboard)
- Bulk action: mark holiday, mark LWOP, swap day
- Color coding: workday / holiday / leave / LWOP / OT

**Payroll Batch Screen**
- ตาราง employee × มี warning บ่งบอกว่า A1 guard จะ BLOCK อะไรก่อน finalize
- Bulk finalize (เลือกหลายคน finalize พร้อมกัน) — กันซ้ำด้วย action ทีละคนจริงๆ ใน queue
- Modal แสดง guard result ชัดเจน: WARN = เขียว/เหลือง, BLOCK = แดง พร้อมวิธีแก้

**Bonus Cycle Wizard**
- Stepper: Setup → Select months → Auto-tier → Preview → Approve → Pay → Close
- แต่ละ step preview ตัวเลขก่อน commit
- Rollback ได้ทุก step ก่อน approve

**Payslip PDF**
- หัวจดหมายบริษัท + โลโก้
- ตารางรายได้ / หัก / สุทธิ ชัดเจน
- Thai locale (฿, YYYY/MM/DD หรือ พ.ศ.)
- QR code link ไป portal เช็ค (future)

### 2.3 UX details ที่ต้องทำทุกหน้า
- Loading states (skeleton / spinner)
- Empty states (ถ้ายังไม่มีข้อมูล บอกทำอะไรต่อ)
- Error states พูดภาษาคน ("ไม่สามารถ finalize ได้เพราะ…" ไม่ใช่ "500 Internal Error")
- Confirm modal สำหรับ destructive action (finalize, reset to auto, cancel job, pay bonus)
- Undo toast สำหรับ action ที่ไม่ destructive (เช่น สลับวัน)
- Keyboard shortcuts: `/` = search, `Cmd+K` = command palette
- Accessibility: alt text, aria-label, focus ring, contrast ratio ≥ AA

**Success criteria:**
- เวลา admin finalize 20 payslip: จาก >30 นาที → <10 นาที
- จำนวน "ไม่รู้ว่าเกิดอะไรขึ้น" support ticket ลดลง 80%
- Cognitive load test: admin ใหม่ใช้ระบบได้ภายใน 30 นาที training

---

## Phase 3 — Missing features (4–6 สัปดาห์)

**เป้าหมาย:** เพิ่ม feature ที่ agents.md บอกว่า "❌ Missing"

### 3.1 Editor self-service portal
- Login แยกจาก admin
- My Jobs (list + status)
- Submit job for review
- View my payslip (download PDF)
- View my attendance + leave balance
- Request leave / day swap

### 3.2 Email notifications
- Laravel mail driver config (SMTP / Mailgun / Resend)
- Queue driver (Redis / database)
- Template: payslip ready, job assigned, leave approved, bonus paid
- Unsubscribe + preference page

### 3.3 Tax simulation (ภ.ง.ด. 1/91)
- คำนวณ withholding tax รายเดือน
- Annual summary export สำหรับยื่น e-Filing
- รองรับ ลดหย่อน: ประกันสังคม, กองทุนสำรองเลี้ยงชีพ, ภรรยา, บุตร

### 3.4 Quality-of-life
- Batch payslip finalize + PDF ZIP download
- Vacation balance ledger (consumed vs. entitled)
- CSV import: attendance, work logs, employees
- Audit log viewer (filterable)

**Success criteria:**
- Editor ไม่ต้องถาม admin ว่า "payslip ฉันอยู่ไหน"
- ยื่น ภ.ง.ด. ได้ภายใน 1 ชั่วโมง (เทียบกับ half-day ปัจจุบัน)

---

## Phase 4 — Long-term architecture (optional)

- **Unify rule models** → `PricingRule` table (เลิก `AttendanceRule`/`RateRule`/`LayerRateRule`/`BonusRule` แยก)
- **WorkCard / OutputMetric migration** (ถ้าจะไป)
- **Agent implementation** A1–A7 ครบตาม `agents.md`
- **Multi-tenant** ถ้าจะรับ HR ให้บริษัทอื่น
- **Mobile app** (React Native) สำหรับ editor check-in/out

---

## แนวทางการทำงานโดยรวม

**กติกา:**
1. ไม่ deploy ถ้า test ไม่ผ่าน
2. Migration ทุกตัว reversible
3. Feature flag สำหรับ feature ใหม่ (เปิด/ปิดได้ไม่ต้อง re-deploy)
4. Backup DB ก่อน migration ทุกครั้ง
5. Staging environment mirror prod 100% ก่อนขึ้น

**Cadence ที่แนะนำ:**
- ทุกเช้า: เช็ค compliance scan (A5) ใน dashboard
- ทุกศุกร์: deploy batch เล็กขึ้น staging
- ทุกสิ้นเดือน: retrospective — อะไรช้า อะไรพลาด

---

## Next concrete step

เลือก 1 ใน 3 เส้นทาง:

**A. Finish Phase 0 first** — apply 3 survival fixes + test บน local ก่อน แล้วค่อยคุย Phase 1/2
**B. Start Phase 1 in parallel** — ผมทำ fixes ที่เหลือเตรียมไว้ในขณะที่คุณ apply Phase 0
**C. Skip to Phase 2 UX** — อยากเห็นหน้าจอที่ดีขึ้นเลย (แต่เสี่ยงเพราะ bug logic ยังไม่แก้ครบ)

คำแนะนำของผม: **B** — Phase 0 apply ไปพร้อมกับผมเตรียม Phase 1/2 ให้ คุณจะได้ไม่ต้องรอ
