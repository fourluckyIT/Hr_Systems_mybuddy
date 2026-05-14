# Pre-Deploy Audit — xHR Payroll v1.3.0 (beta)

วันที่ตรวจ: 2026-05-14
Branch: `feature/v1.3` @ `542ea13`
ขอบเขต: routes / controllers / models / DB / security / deps / tests — ก่อนยิงขึ้น production

---

## TL;DR

**สถานะ: 🟡 พร้อม deploy บน staging แต่มี blocker ก่อน production**

| หมวด | ผล |
|---|---|
| Routes wiring | ✅ 194/196 routes ใช้งานได้ (2 missing methods ใน resource route) |
| Auth coverage | ✅ ทุก route นอก `/login`, `/up`, `/`, `/storage` มี `auth` middleware |
| RBAC | ✅ `role:admin` / `role:admin,owner` ครอบคลุม admin-only routes |
| Tests | 🟡 174 ผ่าน / 6 fail (3 stale tests + 3 ที่ต้องเช็ค) |
| Dependencies | 🟡 มี dead dep 1 ตัว (`laravel-mpdf`) |
| Production config | 🔴 `.env` ยังเป็น `local` + `APP_DEBUG=true` (ต้องแก้ก่อน deploy) |
| Storage | 🟡 ไฟล์แนบเข้าถึงได้ทาง public URL — ใครมี link เปิดได้ |
| Schema integrity | ✅ ไม่มี drift ระหว่าง model/migration |

---

## 1. Critical (ต้องแก้ก่อน production)

### 🔴 C1. `.env` ยังเป็น `local`
```
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
```
**ผลกระทบ:** เปิด debug = แสดง stack trace ที่มีข้อมูล internal (paths, query, secrets) ให้ผู้ใช้เห็นเมื่อ error
**แก้:** บน server เปลี่ยนเป็น
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
```

### 🔴 C2. ไฟล์แนบ portal เข้าถึง public ได้
- `Route::get('storage/{path}')` ส่งไฟล์ใน `storage/app/public` ออกไปทุกคน
- ใบลา/ใบเบิก/หลักฐานการจ่ายเงินที่แนบเข้ามาทั้งหมดอยู่ในนี้
- ใครรู้/เดา URL ได้ เปิดดูได้โดยไม่ต้อง login
**แก้:** สร้าง `Route::get('/attachments/{type}/{id}/{filename}', controller)` ที่เช็ค `authorize()` ก่อนคืนไฟล์ — เก็บไฟล์ใน `storage/app/private/` แทน

### 🔴 C3. SQLite สำหรับ production ไม่แนะนำ
- File-based, ไม่มี connection pooling, lock ทั้ง DB เวลา write
- เหมาะ dev/staging — production ควรใช้ MySQL 8 หรือ Postgres 14+
**แก้:** เปลี่ยน `DB_CONNECTION=mysql` + dump/restore data

---

## 2. High (แก้ก่อนหรือหลัง deploy ก็ได้ — ไม่ block)

### 🟠 H1. Route 2 เส้นชี้ method ที่ไม่มี
`Route::resource('employees')` สร้าง 7 routes แต่ controller ขาด:
- `GET employees/{employee}` → `EmployeeController::show` (ไม่มี method)
- `DELETE employees/{employee}` → `EmployeeController::destroy` (ไม่มี method)

**ผลกระทบ:** ถ้ามีคนกด URL ตรง ๆ จะได้ 500 (BindingResolutionException)
**แก้:** อย่างใดอย่างหนึ่ง:
- `Route::resource('employees', ...)->except(['show', 'destroy'])`
- หรือ implement methods ทั้งสอง

### 🟠 H2. Tests ล้ม 6 จาก 180
```
- BonusCalculationServiceTest (3) — bonus rate 0.2 vs expected 0.3/0.25/0.55
- FullSystemTest::settings_company_loads — got 302, expected 200
- RoleAccessFlowTest (admin + owner) — `/leave` returns 302 (legacy redirect)
```

**วิเคราะห์:**
- `/leave` redirect ไป `/portal` แล้ว — tests ยังเช็ค 200 (test stale, ไม่ใช่ bug ระบบ)
- `/settings/company` redirect — น่าจะเปลี่ยนเส้นไปแล้ว ต้องเช็ค
- Bonus calc 3 failures — อาจเป็น rate config เปลี่ยนหรือ business logic bug ของจริง (**ต้องสอบ**)

### 🟠 H3. ฟีเจอร์ในเมนูที่ไม่ได้ใช้ (database ว่าง)
| ฟีเจอร์ | Table rows | ควรทำ |
|---|---|---|
| Recording sessions/jobs | 0 | ลบ menu / route หรือเก็บไว้ทดสอบต่อ |
| Layer rate rules/templates | 0 | ตรวจดูใช้งานจริงไหม |
| Performance records | 0 | UI มี แต่ไม่มีคน input — checkระบบสร้างให้ |
| Company expenses/revenues | 0 | controller มี view มี ไม่มีการกรอก |
| Document attachments | 0 | feature ใหม่ ยังไม่มีคนแนบ |
| Permission/permission_role | 0 | ใช้ role_user แทน — table ตายแล้ว |

→ ไม่ block deploy แต่ทำให้ user งง

### 🟠 H4. PDF library ซ้อน 2 ตัว
```json
"barryvdh/laravel-dompdf": "^3.1",     // ใช้ทุกที่
"carlos-meneses/laravel-mpdf": "^2.1",  // ไม่ใช้เลย
```
**แก้:** `composer remove carlos-meneses/laravel-mpdf` ลด attack surface + ขนาด

---

## 3. Medium (cleanup ทั่วไป)

### 🟡 M1. ไม่มี cron / queue worker
- ไม่มี scheduled tasks → ไม่ต้องตั้ง `* * * * * php artisan schedule:run`
- ไม่มี queued jobs → ไม่ต้องรัน `php artisan queue:work`
- ระบบ stateless 100% → deploy ง่าย

**ข้อสังเกต:** ถ้าจะส่ง email notification ในอนาคต (มี `NotificationService` แต่ยัง sync) ค่อยเพิ่ม queue worker

### 🟡 M2. ระบบ notification ยังไม่ส่ง email
- `MAIL_MAILER=log` — แค่เขียน log ไม่ส่งจริง
- มี `NotificationService` + `notification_logs` table (20 rows)
- เก็บใน DB เท่านั้น แสดงในกระดิ่ง — OK ถ้าไม่ต้องการ email
**แก้:** ตั้ง SMTP จริงถ้าต้องการ email notification

### 🟡 M3. `{!! !!}` raw output 9 จุด
ทุกจุดเช็คแล้ว — ไม่มี user input ที่ไม่ escape:
- welcome.blade.php — admin-generated warnings
- workspace/show.blade.php — `json_encode()` ก่อน safe
- employees/index.blade.php — `$sortLink()` จาก controller, static string

→ ไม่มีช่องโหว่ XSS ที่ตรวจพบ

### 🟡 M4. Audit logs ทำงาน (197 entries)
- `audit_logs` table มีการ track เปลี่ยนแปลง
- `AuditLogController` มี view
- ครอบคลุม leave approve/reject, doc status change — ดีแล้ว

---

## 4. Low (Nice-to-have)

### L1. CSRF — ✅ Laravel default token ครอบคลุมทุก form
### L2. Mass-assignment — ✅ models ใช้ `$fillable` (not `$guarded = []`)
### L3. Validation — ✅ controllers ใช้ `$request->validate([...])` ครบ
### L4. SQL injection — ✅ ใช้ Query Builder / Eloquent ตลอด, ไม่มี string concat
### L5. Hardcoded credentials — ✅ ไม่พบใน code

---

## 5. ฟีเจอร์ที่ใช้งานจริง (ตามจำนวนข้อมูลใน DB)

```
Core Active                  rows
─────────────────────────────────
attendance_logs              761  ✅ heavily used
payroll_items                413
payslip_items                336
audit_logs                   197
editing_jobs                 185
payslips                      36
bonus_audit_logs              22
notification_logs             20
payroll_batches               18
expense_claims                14
performance_tiers              7
employees                      7
users                          8
leave_requests                 8
leave_carryovers               6
leave_encashments              4
games                          5
positions                      5
holiday_types                  4
attendance_rules               4
leave_policies                 1
day_swap_requests              1
ot_requests                    1
company_profiles               1
```

**ใช้จริง:** Payroll / Workspace / Editing pipeline / Leave / Bonus / Audit
**ใช้ไม่จริง:** Recording sessions / Layer rate / Company finance / Performance records / Document templates (ใหม่)

---

## 6. Production Deploy Checklist

ลำดับการทำ (ก่อนกด deploy):

### Pre-deploy (บน dev machine)
- [ ] แก้ C1: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`
- [ ] แก้ H1: `Route::resource('employees')->except(['show', 'destroy'])` หรือ implement
- [ ] (แนะนำ) แก้ C2: ทำ authenticated attachment route
- [ ] (แนะนำ) แก้ H4: `composer remove carlos-meneses/laravel-mpdf`
- [ ] รัน `php artisan test` ตรวจ regression (เข้าใจ 6 failures)

### Deploy steps (บน production server)
- [ ] ติดตั้ง PHP 8.3+, MySQL 8, Nginx, LibreOffice (ถ้าใช้ docx template)
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `npm ci && npm run build`
- [ ] `cp .env.example .env` + แก้ค่า + `php artisan key:generate`
- [ ] `php artisan migrate --force`
- [ ] `php artisan storage:link`
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] `chown -R www-data:www-data storage bootstrap/cache`
- [ ] `chmod -R 775 storage bootstrap/cache`
- [ ] SSL cert (Let's Encrypt)
- [ ] Backup script (mysqldump + storage)

### Post-deploy verification
- [ ] เปิด `/login` → login ได้
- [ ] ไป `/portal` → list คำขอ + กดดู + กดพิมพ์ PDF
- [ ] ไป `/workspace/<id>/<m>/<y>` → ทำ workspace ครบ
- [ ] ไป `/payslip/.../preview` + `/pdf` → render PDF ได้
- [ ] เช็ค `/up` (Laravel health check) คืน 200
- [ ] ตรวจ `storage/logs/laravel.log` ไม่มี error stack

---

## 7. สิ่งที่ผมแนะนำให้แก้ก่อน deploy

**ตอนนี้เลย (15 นาที):**
1. แก้ `Route::resource('employees')->except(['show', 'destroy'])`
2. `composer remove carlos-meneses/laravel-mpdf`
3. เพิ่ม `.env.production.example` ที่มีค่า production-ready

**ก่อนเปิดให้คนใช้จริง (1-2 ชม.):**
4. ทำ authenticated attachment route (C2)
5. ตรวจ 3 bonus calc failures ว่าเป็น bug จริงไหม
6. เพิ่ม `/up` monitoring (ping จาก uptime service)

**Optional (ทำหลังก็ได้):**
7. ลบ menu/route ฟีเจอร์ที่ไม่ใช้ (recording / layer rate / company finance) ถ้าไม่มีแผนใช้
8. เปลี่ยน MAIL_MAILER เป็น smtp จริงสำหรับ notification

---

## ผมจะทำให้เลยถ้าต้องการ

บอกได้ว่าจะทำให้ข้อไหนก่อน — ผมแนะนำเรียงตามนี้:
1. H1 + H4 (เก็บ resource route + ลบ mpdf)
2. C1 (สร้าง `.env.production.example`)
3. C2 (authenticated attachment route)
4. ตรวจ bonus test failures
