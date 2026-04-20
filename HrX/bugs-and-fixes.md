# Bug Audit & Fixes — xHR Payroll & Finance System
**Audit scope:** `agents.md` (architecture spec, v1.0, 2026-04-19)
**Auditor:** Claude
**Date:** 2026-04-19
**Important caveat:** Source code was not provided. This is a **spec-level audit** of `agents.md`. Bugs below are defects visible in the design itself. Each finding includes a concrete Laravel/MySQL fix that can be dropped into the real codebase when available. Items marked **[CODE-VERIFY]** need a second pass once the repo is accessible.

Severity scale: **CRITICAL** (data loss / illegal pay / security) · **HIGH** (wrong numbers / race condition) · **MEDIUM** (UX / false positives) · **LOW** (doc clarity).

---

## A. Payroll Correctness Bugs

### BUG-01 — CRITICAL — OT monthly cap is wrong for Thai law
**Location:** A1 PayrollGuardAgent, Check #3.
**Problem:** Spec says "OT hours within legal monthly cap (40h)". Thai Labor Protection Act B.E. 2541, §26 caps OT + holiday work combined at **36 hours per week**, not 40 hours per month. A 40h/month rule will (a) under-enforce in weeks where an employee does 38h OT in one week, and (b) over-block in a month where OT legitimately totals 42h spread across 4 weeks.
**Fix:** Replace the monthly cap with a per-week rolling check.
```php
// app/Services/Agents/PayrollGuardAgent.php
public function checkOtCap(Employee $e, Carbon $month): GuardResult
{
    $weeks = AttendanceLog::where('employee_id', $e->id)
        ->whereBetween('work_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
        ->selectRaw('YEARWEEK(work_date, 3) as yw, SUM(ot_minutes) as ot_min')
        ->groupBy('yw')->get();

    foreach ($weeks as $w) {
        if ($w->ot_min > 36 * 60) {
            return GuardResult::block("OT exceeds 36h/week (week {$w->yw}: ".round($w->ot_min/60,1)."h)");
        }
    }
    return GuardResult::pass();
}
```

### BUG-02 — CRITICAL — Recalc silently overwrites manual payroll items
**Location:** Identified Redundancies, item #5 — already self-flagged but no fix listed.
**Problem:** `PayrollItem.source_flag` distinguishes `auto` / `manual` / `override`, but if recalculation simply deletes-and-regenerates all rows for the batch, manual adjustments (e.g., a one-off allowance, correction for a missed day) are destroyed silently. This is the single highest-risk payroll bug.
**Fix:** Scope recalculation deletions to `source_flag = 'auto'` only. Never touch manual/override rows unless the admin explicitly clicks "Reset to auto".
```php
// app/Services/Payroll/PayrollCalculationService.php
DB::transaction(function () use ($batch, $employee) {
    PayrollItem::where('payroll_batch_id', $batch->id)
        ->where('employee_id', $employee->id)
        ->where('source_flag', 'auto')      // <-- critical filter
        ->delete();

    $this->generateAutoItems($batch, $employee);   // only auto rows
});
```
Also add a DB constraint:
```sql
ALTER TABLE payroll_items
  ADD CONSTRAINT chk_source_flag CHECK (source_flag IN ('auto','manual','override'));
```

### BUG-03 — HIGH — Duplicate-PayrollItem check produces false positives
**Location:** A1 PayrollGuardAgent, Check #7: "No duplicate PayrollItem entries (same label + same amount)".
**Problem:** Two legitimate freelance clips can share the same label ("Short edit") and same amount (฿500). Flagging label+amount as duplicate will block valid finalizations.
**Fix:** Deduplicate by a real foreign-key reference (`work_log_id`, `claim_id`, or `bonus_calculation_id`), not by label+amount. Add a partial unique index:
```sql
ALTER TABLE payroll_items
  ADD UNIQUE KEY uq_payroll_item_source (payroll_batch_id, employee_id, source_ref_type, source_ref_id);
```
Then the guard collapses to a single query checking row count equals distinct source_ref count.

### BUG-04 — HIGH — BonusEngineer bounds check mixes units
**Location:** A3 BonusEngineerAgent, Validate step: "`unlock_percentage` ∈ [0, `max_allocation`]".
**Problem:** `unlock_percentage` is a percentage (0–100 or 0–1). `max_allocation` is a currency amount (baht). The bound is incoherent — you can't compare 25% to ฿50,000.
**Fix:** Separate the two checks:
```php
assert($calc->unlock_percentage >= 0 && $calc->unlock_percentage <= 1.0);
assert($calc->actual_payment    >= 0 && $calc->actual_payment    <= $cycle->max_allocation);
```
And rename for clarity: `unlock_ratio` (0–1) vs `actual_payment` (THB).

### BUG-05 — HIGH — Probation handling is undefined
**Location:** A3 BonusEngineerAgent, Validate step.
**Problem:** Only "inactive employees → 0" is specified. Employees still in probation (`probation_end_date > cycle.end_date`) have no rule; current code will silently pay them a full bonus.
**Fix:** Explicit rule:
```php
if ($employee->probation_end_date && $employee->probation_end_date->gt($cycle->end_date)) {
    $calc->actual_payment = 0;
    $calc->note = 'probation_ineligible';
}
```

### BUG-06 — MEDIUM — FinanceReconciler tolerance is too tight and unit-less
**Location:** A6 FinanceReconcilerAgent, process step 4: "flag if delta > ±1%".
**Problem:** 1% on a ฿500,000 monthly payroll = ฿5,000 — fine. But 1% on a ฿1,000 adjustment row = ฿10, which rounding can easily breach. Also, no rounding policy is defined, so results diverge across calculators.
**Fix:** Use `max(1% · recorded, ฿10)` as the tolerance and pin the rounding mode project-wide:
```php
// Centralised helper
function thbRound(float $v): float {
    return round($v, 2, PHP_ROUND_HALF_EVEN);  // banker's rounding, consistent
}

$tolerance = max($recorded * 0.01, 10.00);
if (abs($calculated - $recorded) > $tolerance) { /* flag */ }
```

### BUG-07 — MEDIUM — "No negative net pay" is over-broad
**Location:** A1 PayrollGuardAgent, Check #2.
**Problem:** Written as a blanket BLOCK. Legitimate edge case: employee took an advance > remaining salary → net pay *should* be zero, not blocked. Spec as written forbids zero as well.
**Fix:** `net_pay >= 0` is the only hard rule. Only BLOCK on `net_pay < 0`. If `net_pay == 0`, surface a WARN with reason ("all deductions consumed salary; verify advance/loan balances").

---

## B. Data Integrity & Race Conditions

### BUG-08 — CRITICAL — No transaction wraps PayrollGuard + Finalize
**Location:** A1 PayrollGuardAgent — "fires *before* actual finalization".
**Problem:** If two admins click **Finalize Slip** on the same employee within milliseconds, both guard checks can pass against the same pre-finalized state, and both writes can succeed, producing duplicated finalized payslips (or duplicated bank-transfer instructions downstream).
**Fix:** Wrap guard + finalize in a single DB transaction with a pessimistic lock on the PayrollBatch row (or Payslip row):
```php
DB::transaction(function () use ($batchId, $employeeId) {
    $batch = PayrollBatch::lockForUpdate()->findOrFail($batchId);
    $slip  = Payslip::lockForUpdate()
                ->where('payroll_batch_id', $batchId)
                ->where('employee_id', $employeeId)
                ->firstOrFail();

    if ($slip->status === 'finalized') {
        throw new AlreadyFinalizedException();   // idempotent second click
    }

    $guard = app(PayrollGuardAgent::class)->run($slip);
    if ($guard->isBlock()) { throw new GuardBlockException($guard->messages()); }

    $slip->status = 'finalized';
    $slip->finalized_at = now();
    $slip->save();
});
```
Add a DB-level safety net:
```sql
ALTER TABLE payslips
  ADD UNIQUE KEY uq_payslip_month (payroll_batch_id, employee_id);
```

### BUG-09 — HIGH — A2 cron + state-change trigger can double-fire
**Location:** A2 WorkflowOrchestratorAgent — runs every 6h **and** on state change.
**Problem:** The two triggers overlap. A cron run at 12:00:00 and a user clicking "Submit for review" at 12:00:01 both call A2; both can insert a `DeadlineNotification` for the same job.
**Fix:** Idempotency key on `DeadlineNotification`:
```sql
ALTER TABLE deadline_notifications
  ADD UNIQUE KEY uq_dln_job_type_date (editing_job_id, notification_type, notification_date);
```
And in the agent, use `upsert`:
```php
DeadlineNotification::upsert([$payload], ['editing_job_id','notification_type','notification_date']);
```

### BUG-10 — HIGH — A7 dedupe silently drops CRITICAL notifications
**Location:** A7 NotificationDispatchAgent — "suppress if identical notification was sent < 24h ago".
**Problem:** `compliance.critical_violation` is Critical priority but will be suppressed for 24h. For a labour-law critical alert (e.g., an employee on 8 consecutive workdays) this is unacceptable — compliance violations compound daily.
**Fix:** Dedupe window varies by priority:
```php
$window = match($event->priority) {
    'Critical' => 60 * 60,          // 1 hour
    'High'     => 6 * 60 * 60,
    default    => 24 * 60 * 60,
};
```
Also: dedupe key should include the *state* of the entity (`consecutive_days=8` vs `=9` should fire again), not just event type.

### BUG-11 — HIGH — A6 can double-create the monthly expense draft
**Location:** A6 FinanceReconcilerAgent — fires on Payslip.finalized **and** on 1st of month.
**Problem:** If the last payslip for March is finalized on April 1st 07:05, and the monthly cron runs at 07:00, both runs will attempt to create a `CompanyExpense` draft. Step 2 ("Check if entry already exists") is a TOCTOU race.
**Fix:** Either (a) use `firstOrCreate` guarded by a unique index, or (b) lock the month:
```sql
ALTER TABLE company_expenses
  ADD UNIQUE KEY uq_expense_month_cat (year, month, category, source_flag);
```
```php
CompanyExpense::firstOrCreate(
    ['year'=>$y, 'month'=>$m, 'category'=>'payroll', 'source_flag'=>'agent_draft'],
    ['amount'=>$calculated, 'note'=>'Auto-reconciled']
);
```

### BUG-12 — MEDIUM — EditingJob state machine missing cancel/reject
**Location:** Pipeline table — `assigned → in_progress → review_ready → final`.
**Problem:** No terminal state for jobs that are cancelled (client pulled the game), rejected (scope changed), or replaced (reassigned with fresh ID). Admins currently mutate `status` directly in phpMyAdmin, which bypasses `assertTransition` and AuditLog.
**Fix:** Add two terminal states and a legal-transition table:
```
assigned    → in_progress, cancelled
in_progress → review_ready, cancelled
review_ready→ final, rejected
rejected    → in_progress
final       → (terminal)
cancelled   → (terminal)
```
```php
// app/Services/EditingJobService.php
private const TRANSITIONS = [
    'assigned'     => ['in_progress','cancelled'],
    'in_progress'  => ['review_ready','cancelled'],
    'review_ready' => ['final','rejected'],
    'rejected'     => ['in_progress'],
];
```

### BUG-13 — MEDIUM — A4 self-contradicts on ERROR behaviour
**Location:** A4 AttendanceVerifierAgent.
**Problem:** Outputs table says `ERROR → Save blocked` but the last line says "Runs before the save — does not block downstream payroll recalculation." This reads as "save is blocked but payroll still recalcs" — self-contradicting.
**Fix:** Reword to separate save-gating from downstream effect:
> ERROR blocks the save (transaction rolled back). WARN/VALID commit the save; a successful save enqueues `RecalculatePayrollJob` on the queue (not inside the verifier's transaction).

### BUG-14 — MEDIUM — A1 BLOCK path never notifies admin
**Location:** A7 Event Registry lists events from A2/A3/A5/A6 only; A1 is not listed. Agent Interaction Map shows A1 BLOCK → "UI shows line-item anomalies" but no A7 call.
**Problem:** If the admin is running batch finalization, a silent in-UI anomaly is easy to miss (particularly for 20+ employees). No persistent notification means the block disappears when the page reloads.
**Fix:** Register `payroll.guard_blocked` in A7 and have A1 fire it on BLOCK.

### BUG-15 — LOW — Bonus cycle has no path to `closed`
**Location:** Bonus Cycle pipeline: `... → paid → closed`.
**Problem:** A3 writes `calculated`; admin approves → `approved`; payroll runs → `paid`. Nothing in the spec transitions `paid → closed`. Cycles accumulate in `paid` forever.
**Fix:** Add to A6 (or a new closer job): when every bonus payment in the cycle has a matching finalized payslip, flip cycle status to `closed`.

---

## C. Security

### BUG-16 — CRITICAL — phpMyAdmin on host is an attack surface
**Location:** Project instructions say "host phpmyadmin laravel".
**Problem:** Publicly exposed phpMyAdmin is one of the most scanned paths on the internet (`/phpmyadmin`, `/pma`). Credentials brute-force plus occasional CVEs in older pma versions can lead to full DB compromise — which for this system means every employee's salary, ID, and bank details.
**Fix:** Do **all** of the following, not any one:
1. Move phpMyAdmin behind a non-standard path (`/internal/db-<random-slug>/`) in the web server config.
2. Protect with HTTP Basic Auth **in addition to** pma login (nginx `auth_basic` or Apache `AuthType Basic`).
3. Allow-list office/VPN IPs: `allow 10.0.0.0/8; deny all;`
4. Keep pma on the latest patch release; subscribe to the advisory feed.
5. Long-term: remove pma and use Laravel Telescope + a read-only `mysql` CLI over SSH.

### BUG-17 — HIGH — No mention of Blade auto-escape audit [CODE-VERIFY]
**Location:** Missing from spec entirely.
**Problem:** Payslip PDFs, company-finance reports, and editor names rendered in admin views are high-value XSS targets. `{!! $var !!}` in any Blade template outputs raw HTML.
**Fix:** Add a pre-release grep gate:
```bash
# In CI
if grep -rn '{!!' resources/views/ | grep -v '{!! \$__'; then
  echo "Raw Blade output detected — audit required"; exit 1
fi
```
And convert any legitimate HTML fields to an allow-list filter (e.g., `Purify::clean($var)`) before `{!!`.

### BUG-18 — HIGH — No 2FA on admin/owner [CODE-VERIFY]
**Location:** Missing from spec.
**Problem:** Admin can finalize payroll (monetary write) and approve bonuses. The only barrier is a password. One phished admin = one month's payroll mis-routed.
**Fix:** Enable `laravel/fortify` TOTP, require for any user with role ∈ {admin, owner}. Middleware on all payroll/finance routes:
```php
Route::middleware(['auth','role:admin|owner','2fa.required'])->group(function () {
    Route::post('/payslips/{id}/finalize', [PayslipController::class, 'finalize']);
    ...
});
```

### BUG-19 — HIGH — Mass assignment risk on Employee status fields [CODE-VERIFY]
**Location:** Employee model — fields used by A5 (`status`, `probation_end_date`).
**Problem:** If `Employee` uses `$guarded = []` (or omits these from `$fillable` guards), a crafted form submission `status=inactive` on the employee-edit form can flip a user out of payroll. Likewise `probation_end_date = 2099-01-01` makes them permanently bonus-ineligible.
**Fix:**
```php
// app/Models/Employee.php
protected $fillable = [ /* explicit list — NO status, NO probation_end_date */ ];
protected $guarded  = ['status','probation_end_date','role','salary','company_id'];
```
Only change these via dedicated service methods that require an admin policy check.

### BUG-20 — HIGH — PDF generator must disable remote content [CODE-VERIFY]
**Location:** A1 → Payslip PDF (feature marked Complete).
**Problem:** If the PDF renderer is `dompdf` with `isRemoteEnabled=true`, user-controlled HTML in a payslip (e.g., a bonus note) can trigger SSRF (`<img src="http://169.254.169.254/latest/meta-data/">`).
**Fix:**
```php
// config/dompdf.php
'options' => [
    'is_remote_enabled' => false,   // <-- must be false
    'is_php_enabled'    => false,
    'is_javascript_enabled' => false,
],
```
If logos/charts must load, whitelist by local path only, never by URL.

### BUG-21 — MEDIUM — Audit log can be a vector for log injection
**Location:** A5 writes `AuditLog` with findings; A1 writes "result summary".
**Problem:** If findings include raw employee-controlled strings (e.g., leave-request reason) and the audit log is viewed as HTML, stored XSS is possible on the admin dashboard.
**Fix:** Audit payloads are always JSON-encoded at write, and the admin dashboard renders them with `{{ }}` (auto-escaped), never `{!! !!}`.

---

## D. Performance (N+1 & Indexing)

### BUG-22 — HIGH — A3 BonusEngineer is N+1 on worklogs [CODE-VERIFY]
**Location:** A3 "Aggregate output metrics per employee across selected months".
**Problem:** Typical naïve implementation: `foreach ($employees as $e) { $e->worklogs()->whereBetween(...)->sum(...); }` — one query per employee per metric. For 30 employees × 3 metrics × N months, expect hundreds of queries.
**Fix:** Single aggregated query then hydrate results per employee:
```php
$agg = WorkLog::selectRaw('
        employee_id,
        SUM(duration_minutes)  AS total_minutes,
        COUNT(*)               AS clip_count,
        SUM(quantity)          AS total_quantity
    ')
    ->whereIn('month', $selectedMonths)
    ->groupBy('employee_id')
    ->get()
    ->keyBy('employee_id');

foreach ($employees as $e) {
    $row = $agg->get($e->id);
    // feed into calculator
}
```
Index: `CREATE INDEX idx_worklog_emp_month ON work_logs (employee_id, month);`

### BUG-23 — HIGH — A5 ComplianceWatchdog is N+1 per employee [CODE-VERIFY]
**Location:** A5 iterates active employees and reads `AttendanceLog[]` per employee.
**Fix:** Single SQL with window function to detect 7+ consecutive workdays:
```sql
SELECT employee_id, MAX(streak) AS max_streak FROM (
  SELECT employee_id, work_date,
         ROW_NUMBER() OVER (PARTITION BY employee_id ORDER BY work_date)
           - DATEDIFF(work_date, '1970-01-01') AS grp,
         COUNT(*) OVER (PARTITION BY employee_id,
           (ROW_NUMBER() OVER (PARTITION BY employee_id ORDER BY work_date)
            - DATEDIFF(work_date, '1970-01-01'))) AS streak
  FROM attendance_logs
  WHERE day_type = 'workday'
    AND work_date BETWEEN ? AND ?
) s
GROUP BY employee_id
HAVING MAX(streak) >= 7;
```
One query replaces one-per-employee.

### BUG-24 — MEDIUM — A2 scan is a full-table read [CODE-VERIFY]
**Location:** A2 every 6h — "all `EditingJob` records with status ≠ `final`".
**Fix:** Composite index that matches the query shape:
```sql
CREATE INDEX idx_editing_jobs_open ON editing_jobs (status, deadline_date)
  WHERE status <> 'final';    -- partial index (MySQL 8.0.13+ via functional, or use status alone on older)
```
For MySQL without partial indexes, a plain `(status, deadline_date)` covers it well enough since open jobs are a minority.

### BUG-25 — MEDIUM — PayrollItem missing composite index [CODE-VERIFY]
**Location:** PayrollItem table, queried by `payroll_batch_id + employee_id` on every recalc.
**Fix:**
```sql
ALTER TABLE payroll_items
  ADD INDEX idx_pi_batch_emp (payroll_batch_id, employee_id),
  ADD INDEX idx_pi_source   (payroll_batch_id, source_flag);
```

### BUG-26 — LOW — A1 re-reads AttendanceLog on every finalize
**Fix:** Eager-load via the PayrollBatch relationship; cache monthly attendance per (employee, month) in Redis with a short TTL (invalidate on AttendanceLog save).

---

## E. Documentation Contradictions (fix = update agents.md)

### BUG-27 — Contradiction: BonusRule is "unused" yet listed as A3 input
**Location:** Redundancy #4 says `BonusRule` is effectively unused. A3's Interactions still lists reading from config derived via rules.
**Fix:** Either resurrect `BonusRule` as the single source for penalty/unlock config (preferred — one place to change rules) or delete the model and migration and update A3 Inputs to say "read directly from `BonusCycle`".

### BUG-28 — Contradiction: A6 owner row vs. approval flow
**Location:** Agent Roster: A6 owner "Admin/Owner". But A6 outputs `agent_draft` and the approval step is described only as "admin approves".
**Fix:** Define two thresholds — admin can approve draft ≤ ฿100k, anything larger requires Owner. Add to A6 output spec.

### BUG-29 — Inconsistency: WorkCard/OutputMetric mentioned but no migration plan
**Location:** Redundancy #2 says WorkCard/OutputMetric is the proposed future model. Implementation Requirements lists only `notification_logs` and one new column. No migration for WorkCard.
**Fix:** Either commit to keeping EditingJob/WorkLog and strike WorkCard from the doc, or add the migration plan to Implementation Requirements.

### BUG-30 — Inconsistency: "Editor" role present but no login portal
**Location:** Stack line mentions `admin/owner/editor` roles; Current Feature Status says Editor portal is Missing.
**Fix:** If the `editor` role exists in DB but no routes are role-guarded, an editor account could still hit admin APIs via direct URL. Either:
(a) strip the `editor` role until the portal exists, or
(b) ship a middleware blanket-deny on any route not in an explicit `editor.allowed` list.

---

## Priority-ordered fix list (what I'd merge first)

1. **BUG-02** (manual-item overwrite) — data-loss risk, 1-line fix.
2. **BUG-08** (finalize race) — wrap in transaction + unique index.
3. **BUG-16** (phpMyAdmin exposure) — firewall/allow-list today.
4. **BUG-01** (OT cap) — legal compliance.
5. **BUG-10** (A7 dropping criticals) — small code change, big impact.
6. **BUG-11** (A6 double-draft) — add unique index, use `firstOrCreate`.
7. **BUG-03** (false-positive dup check) — switch to `source_ref_id`.
8. **BUG-09** (A2 duplicate notifications) — idempotency key.
9. **BUG-19 / BUG-20** (mass-assignment, dompdf) — config hardening.
10. **BUG-22 / BUG-23** (N+1) — measurable win at scale.

---

## What I need to go deeper

To turn every **[CODE-VERIFY]** item into confirmed fixes, please share one of:
- The repo (zip, tarball, or mounted folder),
- Or at minimum: `app/Models/Employee.php`, `app/Services/Payroll/PayrollCalculationService.php`, `config/dompdf.php`, `routes/web.php`, and `database/migrations/`.

With those files I can grep for `{!!`, check `$fillable`, verify indexes from migrations, and produce a PR-ready patch set.
