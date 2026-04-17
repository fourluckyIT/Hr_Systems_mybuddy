# xHR System Full Audit Report

วันที่ตรวจ: 2026-04-14
ขอบเขต: โค้ด, ฐานข้อมูล, Route/API, Service Layer, UX/UI, Test Health, Legacy/ขยะ

## 1) Executive Summary

สถานะโดยรวม: Partial Ready

สิ่งที่ทำได้จริงตอนนี้
- จัดการพนักงาน, workspace, payslip, annual summary, company finance ได้ในระดับใช้งานภายใน
- Editing workflow ฝั่งหน้าเว็บมีการเดินสถานะ assigned -> in_progress -> review_ready -> final
- Bonus module มี API คำนวณเดี่ยว/แบตช์ และ approve ได้

สิ่งที่เป็น Blocker ระดับสูง
- API Editing workflow มีจุดแตก runtime หลายจุดจาก method signature และ relation ไม่ตรงกัน
- Route บางเส้นใน workspace ชี้ไป method ที่ไม่มีอยู่จริงใน controller
- Model/Schema/Test ของ Game ไม่ตรงกัน (game_slug vs game_code)
- API ทั้งชุดไม่มี auth/permission guard
- โครงข้อมูล WorkLog มีชื่อคอลัมน์ drift (editing_job_id vs edit_job_id)

ผลทดสอบ
- รัน test ชุด EditingJob แล้วล้มตั้งแต่ setup เพราะ schema drift
- หลักฐาน: [tests/Feature/EditingJobFeatureTest.php](tests/Feature/EditingJobFeatureTest.php#L49), [database/migrations/2026_04_13_090000_create_games_table.php](database/migrations/2026_04_13_090000_create_games_table.php#L14), [app/Models/Game.php](app/Models/Game.php#L12)

## 2) Flow วิธีการคิดและวิธี Audit

Flow การตรวจที่ใช้
1. ตรวจ surface ทั้งระบบจาก route list เพื่อระบุ capability ที่เปิดใช้งานจริง
2. แมป route -> controller -> service -> model -> migration
3. เทียบสัญญา method และ payload ว่าตรงกันหรือไม่
4. ตรวจ schema consistency ระหว่าง migration, model fillable/cast, และ test
5. ตรวจ UX flow หลักจากหน้า work/workspace/annual/company
6. รัน test เฉพาะจุดเพื่อยืนยัน regression ที่กระทบการใช้งานจริง
7. แยกประเด็นเป็น Critical/High/Medium/Low พร้อมข้อเสนอแนะและลำดับแก้

หลักคิดที่ใช้
- ถ้า route เรียก method ที่ไม่มีจริง = runtime blocker
- ถ้า controller กับ service signature ไม่ตรง = runtime blocker
- ถ้า model กับ migration ไม่ตรง = data integrity + test failure
- ถ้า API ไม่มี auth = security blocker

## 3) ระบบทำอะไรได้บ้างตอนนี้

### 3.1 Employee Management
สถานะ: Partial
- มีหน้า list/create/edit/toggle status
- มี role select ตอนสร้างพนักงาน
- ข้อจำกัด: role จะ sync ได้เฉพาะกรณี employee มี user ผูกอยู่แล้ว
- หลักฐาน: [app/Http/Controllers/EmployeeController.php](app/Http/Controllers/EmployeeController.php#L64), [resources/views/employees/create.blade.php](resources/views/employees/create.blade.php#L132)

### 3.2 Workspace และ Payroll
สถานะ: Partial to Good
- แสดง payroll summary, claims, attendance/worklog ตาม payroll mode
- มี recalculation และ finalize payslip
- ข้อจำกัด: route หลายเส้นชี้ method ที่ไม่มีอยู่จริง
- หลักฐาน: [routes/web.php](routes/web.php#L56), [app/Http/Controllers/WorkspaceController.php](app/Http/Controllers/WorkspaceController.php#L1)

### 3.3 Work Pipeline (Editing)
สถานะ: Partial
- หน้า work pipeline มอบหมายและเปลี่ยนสถานะงานได้
- owner action ถูกเปิดใน workspace แล้ว
- ข้อจำกัด: API ฝั่ง editing workflow ยังแตกหลายจุด
- หลักฐาน: [app/Http/Controllers/WorkCommandController.php](app/Http/Controllers/WorkCommandController.php#L149), [app/Http/Controllers/EditingJobController.php](app/Http/Controllers/EditingJobController.php#L39)

### 3.4 Payslip
สถานะ: Good
- preview/finalize/pdf มี
- ใช้ snapshot ที่ payslip_items ตอน finalize
- หลักฐาน: [app/Services/Payroll/PayrollCalculationService.php](app/Services/Payroll/PayrollCalculationService.php#L118), [app/Http/Controllers/PayslipController.php](app/Http/Controllers/PayslipController.php#L113)

### 3.5 Annual Summary และ Company Finance
สถานะ: Good (ฟังก์ชันหลักทำงาน)
- annual summary 12 เดือนรายบุคคล
- company P&L รายเดือน + รายละเอียดรายรับ/รายจ่าย/subscription
- หลักฐาน: [app/Http/Controllers/AnnualSummaryController.php](app/Http/Controllers/AnnualSummaryController.php#L12), [app/Http/Controllers/CompanyFinanceController.php](app/Http/Controllers/CompanyFinanceController.php#L13)

## 4) Critical Findings

### C1. Route ชี้ method ที่ไม่มีอยู่จริงใน WorkspaceController
ความรุนแรง: Critical
ผลกระทบ: กด action แล้ว 500 ทันที

เส้น route ที่เสี่ยง
- workspace.updateItem -> updatePayrollItem
- workspace.payroll.update -> updatePayrollItem
- workspace.performance.store -> storePerformanceRecord
- workspace.performance.delete -> deletePerformanceRecord

หลักฐาน
- route: [routes/web.php](routes/web.php#L64)
- controller ไม่มี method ดังกล่าว: [app/Http/Controllers/WorkspaceController.php](app/Http/Controllers/WorkspaceController.php#L1)

### C2. EditingJobController เรียก service ด้วย signature คนละแบบ
ความรุนแรง: Critical
ผลกระทบ: API jobs runtime error เมื่อเรียก start/mark-ready/finalize/reassign/update/delete

ตัวอย่าง
- controller ส่ง int job id + employee id
- service รับ EditingJob object เป็นหลัก

หลักฐาน
- [app/Http/Controllers/EditingJobController.php](app/Http/Controllers/EditingJobController.php#L39)
- [app/Services/EditingJobService.php](app/Services/EditingJobService.php#L44)

### C3. EditingJobController ใช้ relation ที่ไม่มีใน EditingJob model
ความรุนแรง: Critical
ผลกระทบ: API show อาจแตกทันทีที่เรียก with(reassignments, modifications)

หลักฐาน
- เรียก relation: [app/Http/Controllers/EditingJobController.php](app/Http/Controllers/EditingJobController.php#L143)
- model ไม่มี relation นี้: [app/Models/EditingJob.php](app/Models/EditingJob.php#L7)

### C4. Service methods ที่ controller เรียก ไม่มีจริง
ความรุนแรง: Critical
ผลกระทบ: API performance/overdue ใช้งานไม่ได้

หลักฐาน
- controller เรียก getEmployeePerformance และ getOverdueJobs: [app/Http/Controllers/EditingJobController.php](app/Http/Controllers/EditingJobController.php#L189)
- service ไม่มี method เหล่านี้: [app/Services/EditingJobService.php](app/Services/EditingJobService.php#L1)

### C5. API ไม่มี auth/authorization guard
ความรุนแรง: Critical
ผลกระทบ: external caller สามารถยิง calculate bonus, create/edit jobs โดยไม่ล็อกอิน

หลักฐาน
- [routes/api.php](routes/api.php#L6)

## 5) High Findings

### H1. Drift ของ Game schema/model/tests
ความรุนแรง: High
ผลกระทบ: test ล้ม, create game จาก model ไม่ตรง DB

สถานะ
- migration ใช้ game_code
- model + controller + tests ใช้ game_slug

หลักฐาน
- [database/migrations/2026_04_13_090000_create_games_table.php](database/migrations/2026_04_13_090000_create_games_table.php#L14)
- [app/Models/Game.php](app/Models/Game.php#L12)
- [app/Http/Controllers/MasterDataController.php](app/Http/Controllers/MasterDataController.php#L349)
- [tests/Feature/EditingJobFeatureTest.php](tests/Feature/EditingJobFeatureTest.php#L49)

### H2. WorkLog foreign key naming drift
ความรุนแรง: High
ผลกระทบ: ความสับสนในการเชื่อมงานตัดต่อ -> work logs, เสี่ยง query ผิดคอลัมน์

สถานะ
- ตารางมี editing_job_id ตั้งแต่แรก
- migration ใหม่เพิ่ม edit_job_id ซ้ำความหมาย

หลักฐาน
- [database/migrations/0001_01_01_000006_create_attendance_worklogs_tables.php](database/migrations/0001_01_01_000006_create_attendance_worklogs_tables.php#L56)
- [database/migrations/2026_04_08_100001_add_source_fields_to_work_logs_table.php](database/migrations/2026_04_08_100001_add_source_fields_to_work_logs_table.php#L15)

### H3. Unit/Feature tests ของ EditingJob ล้าหลังโค้ดจริงหนัก
ความรุนแรง: High
ผลกระทบ: test suite ไม่ใช่ safety net

ตัวอย่างที่ไม่ตรง
- test เรียก service methods ที่ไม่มี
- test import models ที่ไม่มีจริง เช่น DeadlineNotification, JobModification, JobReassignment

หลักฐาน
- [tests/Unit/EditingJobServiceTest.php](tests/Unit/EditingJobServiceTest.php#L5)
- [app/Services/EditingJobService.php](app/Services/EditingJobService.php#L1)

### H4. ระบบประกาศปิด recording/resource บางส่วน แต่ยังค้าง logic ใน calendar
ความรุนแรง: High
ผลกระทบ: requirement ปิดคิวถ่าย ยังไม่ clean ทั้งระบบ

หลักฐาน
- [app/Http/Controllers/CalendarController.php](app/Http/Controllers/CalendarController.php#L45)

## 6) Medium Findings

### M1. Youtuber salary partial ไม่ถูกใช้งาน
- มีไฟล์ partial แต่ไม่ถูก include ใน workspace show
- หลักฐาน: [resources/views/workspace/partials/youtuber-salary-grid.blade.php](resources/views/workspace/partials/youtuber-salary-grid.blade.php), [resources/views/workspace/show.blade.php](resources/views/workspace/show.blade.php#L181)

### M2. Employee role assignment ยังไม่ end-to-end
- ฟอร์มบังคับเลือก role แต่ไม่ได้สร้าง user/link user ใน flow เดียวกัน
- เสี่ยงเข้าใจผิดว่าตั้ง role สำเร็จแล้ว ทั้งที่ไม่มี user ผูก
- หลักฐาน: [app/Http/Controllers/EmployeeController.php](app/Http/Controllers/EmployeeController.php#L131)

### M3. WorkCommandController ยังพก recording/media imports และ methods
- แม้ route หลักจะหันไป editing เป็นหลัก
- หลักฐาน: [app/Http/Controllers/WorkCommandController.php](app/Http/Controllers/WorkCommandController.php#L5)

## 7) Low Findings

### L1. README ยังเป็น default Laravel
- onboarding knowledge ไม่พอสำหรับทีม
- หลักฐาน: [README.md](README.md)

### L2. Route surface ใหญ่ แต่ไม่มี capability matrix ทางการ
- แนะนำทำเอกสาร map module -> route -> owner

## 8) UX/UI Audit

จุดแข็ง
- Workspace มี month navigation, summary cards, source badges
- Work Pipeline table ชัด เข้าใจสถานะง่าย
- Company finance และ annual summary ใช้งานจริงได้

จุดที่ควรแก้
- ปุ่ม/route บางจุดมีแต่ backend method ไม่อยู่ เสี่ยงผู้ใช้เจอ error
- feature recording ยังปรากฏทางอ้อมผ่าน calendar
- youtuber salary flow ยังไม่ชัดว่าใช้ grid ไหนเป็น source จริง

หลักฐานหลัก
- [resources/views/work/index.blade.php](resources/views/work/index.blade.php)
- [resources/views/workspace/show.blade.php](resources/views/workspace/show.blade.php)
- [resources/views/calendar/index.blade.php](resources/views/calendar/index.blade.php)

## 9) Database Audit

ภาพรวม
- migration มีจำนวนมากและครอบคลุม domain หลัก
- มี FK หลายจุดตามแนวทางที่ดี

ความเสี่ยงหลัก
- naming drift ของคอลัมน์และ entity (game_slug/game_code, edit_job_id/editing_job_id)
- ตารางรองรับ history/reassignment/modification ของ editing workflow ยังไม่ปรากฏตามที่ API/test คาดหวัง

หลักฐาน
- [database/migrations/2026_04_13_100000_create_editing_workflow_tables.php](database/migrations/2026_04_13_100000_create_editing_workflow_tables.php)
- [tests/Unit/EditingJobServiceTest.php](tests/Unit/EditingJobServiceTest.php#L213)

## 10) Security and Access Audit

ประเด็นเสี่ยง
- API routes ไม่มี middleware auth หรือ role
- route web มี role guard ดีขึ้น แต่ยังต้องตรวจ policy ระดับ resource เพิ่ม

หลักฐาน
- [routes/api.php](routes/api.php#L6)
- [routes/web.php](routes/web.php#L33)

## 11) Garbage / Legacy / Candidate Cleanup

ไฟล์หรือโค้ดที่เข้าข่ายขยะหรือ legacy ควรทบทวน
- [resources/views/company/expenses.blade.php](resources/views/company/expenses.blade.php)
: ไม่พบการ render ตรงแล้ว (controller redirect ไป finance)
- [resources/views/workspace/partials/youtuber-salary-grid.blade.php](resources/views/workspace/partials/youtuber-salary-grid.blade.php)
: ไม่ถูก include ใน flow ปัจจุบัน
- [tests/Unit/EditingJobServiceTest.php](tests/Unit/EditingJobServiceTest.php)
: อ้างอิง model/method ที่ไม่มีจริงจำนวนมาก
- recording stack บางส่วน เช่น [app/Models/RecordingJob.php](app/Models/RecordingJob.php), [app/Models/MediaResource.php](app/Models/MediaResource.php)
: ยังถูกใช้โดย calendar แต่ขัดกับทิศทางปิดคิวถ่าย ควรตัดสินใจเชิงผลิตภัณฑ์ว่าจะเลิกจริงหรือคงไว้

หมายเหตุ
- ยังไม่ลบไฟล์ใดออกในรอบ audit นี้ เพราะบางส่วนยังมี dependency ทางอ้อม

## 12) ข้อเสนอแนะแบบลำดับความสำคัญ

### Sprint 0 (ต้องทำทันที)
1. ปิดความเสี่ยง security: ใส่ auth + role middleware ให้ API ทั้งหมด
2. แก้ route แตก: เพิ่ม method ที่ขาดใน WorkspaceController หรือถอน route ที่ไม่ใช้
3. ทำให้ EditingJobController และ EditingJobService พูดภาษาเดียวกัน
4. แก้ Game schema drift ให้ตรงทั้ง migration/model/controller/tests

### Sprint 1
1. ตัดสินใจ canonical field ของ work log relation เหลือชื่อเดียว
2. เพิ่ม relation/table ที่ API editing ต้องใช้ หรือถอด endpoint ที่ยังไม่พร้อม
3. Refactor test ชุด EditingJob ให้ตรงโค้ดจริง แล้วเปิด CI gate

### Sprint 2
1. ปิด/ย้าย recording flow ออกจาก calendar หาก requirement คือปิดถาวร
2. ทำ capability matrix และ owner matrix ของทุก module
3. เขียน README ระบบจริงสำหรับ onboarding และ production operation

## 13) Definition of Done สำหรับรอบ Stabilization

ถือว่าผ่าน stabilization เมื่อ
- Route ทุกเส้นที่เปิดใช้งานเรียก method ได้จริง
- API สำคัญมี auth/role ครบ
- Editing workflow web + api เดินครบ end-to-end พร้อม test ผ่าน
- test ชุด Feature/Unit ที่ critical ผ่านโดยไม่ skip
- naming drift ใน DB/model ถูกแก้ให้เหลือ source of truth เดียว

## 14) สรุปสุดท้าย

ระบบมีฐานที่ดีและฟังก์ชันธุรกิจหลักหลายตัวใช้งานได้แล้ว แต่ยังไม่ถึง production-safe เพราะมี blocker เชิงสัญญาโค้ดและความปลอดภัยของ API ที่ชัดเจน

ถ้าจัดลำดับแก้ตาม Sprint 0 ก่อน ระบบจะนิ่งขึ้นอย่างมีนัยสำคัญและพร้อมเข้าสู่รอบ hardening/testing ต่อทันที
