# How to apply these fixes to xHR

**Source:** the 30-bug audit in `bugs-and-fixes.md`.
**Contents of this folder:**

```
fixes/
├── database/migrations/        6 migration files
├── app/
│   ├── Services/
│   │   ├── Agents/             PayrollGuardAgent, NotificationDispatchAgent, FinanceReconcilerAgent, GuardResult
│   │   ├── Payroll/            PayrollCalculationService, FinalizePayslipAction
│   │   └── EditingJobService.php
│   ├── Http/Middleware/        EnsureRole, Require2fa
│   ├── Models/Employee.php     (merge with your existing Employee model)
│   └── Exceptions/             AlreadyFinalizedException, GuardBlockException, IllegalTransitionException
├── config/dompdf.php           (replaces current)
└── routes/web.php.snippet      (merge route groups into your routes/web.php)
```

All files assume the following existing models/tables (named in `agents.md`):
`Employee`, `PayrollBatch`, `PayrollItem`, `Payslip`, `AttendanceLog`, `WorkLog`,
`EditingJob`, `CompanyExpense`, `CompanyRevenue`, `User`, `NotificationLog` (new).

---

## Step-by-step apply

### 1. Back up the database
```bash
mysqldump -u root -p xhr > xhr-backup-$(date +%F).sql
```
Keep this. The migrations are reversible, but a pre-migration dump is the ultimate safety net — especially for the `chk_source_flag` constraint, which will fail if any payroll_items row has a value outside {auto, manual, override}.

### 2. Copy files into your Laravel project
```bash
# From xHR repo root:
cp -r /path/to/fixes/database/migrations/*      database/migrations/
cp -r /path/to/fixes/app/Services/Agents/*      app/Services/Agents/
cp    /path/to/fixes/app/Services/Payroll/*     app/Services/Payroll/
cp    /path/to/fixes/app/Services/EditingJobService.php  app/Services/
cp -r /path/to/fixes/app/Http/Middleware/*      app/Http/Middleware/
cp -r /path/to/fixes/app/Exceptions/*           app/Exceptions/
cp    /path/to/fixes/config/dompdf.php          config/dompdf.php
```

The `app/Models/Employee.php` and `routes/web.php.snippet` are **merge** targets, not
overwrites — open each file and merge the changes into your existing version.

### 3. Register middleware aliases
**Laravel 11** — edit `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role'          => \App\Http\Middleware\EnsureRole::class,
        '2fa.required' => \App\Http\Middleware\Require2fa::class,
    ]);
})
```
**Laravel 10** — edit `app/Http/Kernel.php`, add to `$middlewareAliases`:
```php
'role'          => \App\Http\Middleware\EnsureRole::class,
'2fa.required' => \App\Http\Middleware\Require2fa::class,
```

### 4. Clean payroll_items before the CHECK constraint
If any row has `source_flag` outside {auto, manual, override}, migration 000001 will
abort. Run this first:
```sql
UPDATE payroll_items SET source_flag = 'auto'
 WHERE source_flag NOT IN ('auto','manual','override') OR source_flag IS NULL;
```

### 5. Run migrations
```bash
php artisan migrate
```

If a migration fails mid-way, roll back the batch and re-run after fixing data:
```bash
php artisan migrate:rollback --step=1
```

### 6. Update PayslipController::finalize() to use the new action
Replace the body of your existing finalize action with:
```php
public function finalize(int $payslip, \App\Services\Payroll\FinalizePayslipAction $action)
{
    try {
        $result = $action->execute($payslip, auth()->id());
        return response()->json(['status' => 'finalized', 'payslip' => $result]);
    } catch (\App\Exceptions\AlreadyFinalizedException $e) {
        return response()->json(['status' => 'already_finalized'], 200);
    }
    // GuardBlockException is auto-rendered (see the exception class).
}
```

### 7. Enable 2FA for admin/owner
Install Fortify (if not already):
```bash
composer require laravel/fortify
php artisan vendor:publish --provider="Laravel\Fortify\FortifyServiceProvider"
php artisan migrate
```
Then have every admin/owner user go through `/user/two-factor-authentication/confirm`
before April 30. Until they do, the `2fa.required` middleware will redirect them from
payroll routes.

### 8. Lock down phpMyAdmin (BUG-16 — do this **today**)
Add to your nginx server block:
```nginx
location ~ ^/(phpmyadmin|pma) {
    allow 10.0.0.0/8;         # office LAN
    allow 203.0.113.42;       # your home VPN exit IP
    deny all;
    auth_basic           "Restricted";
    auth_basic_user_file /etc/nginx/.htpasswd;
    # ... usual fastcgi_pass to php-fpm
}
```
Create the basic-auth file:
```bash
sudo htpasswd -c /etc/nginx/.htpasswd admin
sudo nginx -t && sudo systemctl reload nginx
```
Long-term: move phpMyAdmin off the public interface entirely — only reachable over SSH tunnel.

### 9. Verification checklist
After deploy, smoke-test these:

- [ ] A payslip with a manual PayrollItem row: click "Recalculate" → manual row must still be present (BUG-02).
- [ ] Open the same payslip in two browser tabs, click Finalize simultaneously → exactly one succeeds; the other returns 200 "already_finalized" (BUG-08).
- [ ] Insert an AttendanceLog with `ot_minutes = 40*60` in a single week → PayrollGuard blocks with code `ot_weekly_cap_exceeded` (BUG-01).
- [ ] Attempt `UPDATE editing_jobs SET status='final' WHERE status='assigned'` via controller — blocked with `IllegalTransitionException` (BUG-12).
- [ ] Upload a payslip PDF template with `<img src="http://evil.example.com/x.png">` → renders as broken image, no outbound request (BUG-20). Check `tcpdump` or nginx access log during render.
- [ ] Log in as `editor` role → /payslips returns 403 (BUG-30).
- [ ] Log in as `admin` without 2FA → visit /payslips/X/finalize → redirected to profile.security (BUG-18).
- [ ] Trigger A5 at 07:00 → if it fires a `compliance.critical_violation`, then fire again with a state change (consecutive_days from 7 → 8) within the hour → second notification delivers, not suppressed (BUG-10).

### 10. Rollback plan
Every migration is reversible. To undo the whole batch:
```bash
php artisan migrate:rollback --step=6
```
Service class changes are in new files or adapted methods — to revert, `git checkout` the files. The middleware aliases can be removed from `bootstrap/app.php`.

---

## What's NOT included in this batch (needs real source to finish)

- **BUG-04 (BonusEngineer bounds)** — need the existing `BonusCalculationService` to patch.
- **BUG-05 (probation handling)** — ditto.
- **BUG-15 (bonus cycle → closed)** — needs your cycle closer job.
- **BUG-17 (Blade grep gate)** — 1-line CI step, depends on your CI config.
- **BUG-21 (audit log escaping)** — depends on your audit dashboard blade file.
- **BUG-22 / 23** — the indexes are in migration 000005, but the N+1 query rewrites in A3/A5 need your existing service method signatures.
- **BUG-26 — A1 attendance cache** — Redis-dependent, needs your cache config.

When you drop the repo (or the specific files listed in the audit's closing section) I can patch those too.

---

## File size sanity check

All files in this batch:
```
migrations:  6 files  ≈  300 lines total
agents:      4 files  ≈  350 lines total
services:    3 files  ≈  300 lines total
models:      1 file   ≈   90 lines
middleware:  2 files  ≈   60 lines
exceptions:  3 files  ≈   40 lines
config:      1 file   ≈   60 lines
routes:      1 file   ≈   50 lines
                     ─────────────
total:     21 files  ≈ 1,250 lines
```

No new packages required beyond `laravel/fortify` for 2FA.
