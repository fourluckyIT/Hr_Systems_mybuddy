# agents.md — xHR AI Agent Architecture
**Version:** 1.0 | **Date:** 2026-04-19
**Project:** xHR Payroll & Finance System

---

## System Architecture Summary

**Stack:** Laravel (PHP), Blade templates, MySQL, role-based access (admin/owner/editor)

**Production Philosophy:** `Work → Output → Pricing → Payroll`

| Pipeline | States |
|---|---|
| Editing Job | `assigned → in_progress → review_ready → final` |
| Payroll | `draft → calculating → calculated → finalized → paid` |
| Bonus Cycle | `draft → calculating → calculated → reviewed → approved → paid → closed` |

**5 Payroll Modes:**
1. `monthly_staff` / `office_staff` — salary + attendance deductions + OT
2. `freelance_layer` — duration × rate/minute × layer multiplier
3. `freelance_fixed` — quantity × fixed rate
4. `youtuber_salary` — monthly salary variant
5. `youtuber_settlement` — revenue-sharing / settlement model

---

## Current Feature Status

| Domain | Status | Key Files |
|---|---|---|
| Employee CRUD + roles | ✅ Complete | `EmployeeController`, `Employee` |
| Attendance tracking (check-in/out, day types, swaps) | ✅ Complete | `WorkspaceController`, `AttendanceLog` |
| Leave & day-swap requests with approval workflow | ✅ Complete | `LeaveRequestController` |
| Multi-mode payroll calculation engine | ✅ Complete | `PayrollCalculationService` + 5 calculators |
| Payslip finalization + PDF | ✅ Complete | `PayslipController` |
| Editing job pipeline (assigned→final) | ✅ Complete | `WorkCommandController`, `EditingJobService` |
| Bonus cycle management + tiered calculation | ✅ Complete | `BonusManagementController`, `BonusCalculationService` |
| Company Finance / P&L dashboard | ✅ Complete | `CompanyFinanceController` |
| Annual summary for tax filing | ✅ Complete | `AnnualSummaryController` |
| Audit log (full change history) | ✅ Complete | `AuditLogService` |
| Email notifications | ❌ Missing | Planned — no mail driver configured |
| Tax simulation (ภ.ง.ด. withholding) | ❌ Missing | Planned |
| Editor self-service login portal | ❌ Missing | Editors managed by admin only |
| WorkCard / OutputMetric models | ❌ Missing | Documented in GG.md as future migration |

---

## Monthly Operational Workflow

```
[Beginning of Month]
  Admin → Work Center → Create editing jobs → Assign to editors
  Admin → Workspace → Open attendance grid

[Mid-Month]
  Editor → My Work → Start job → Submit job (review_ready)
  Admin → Work Command → Review job → Finalize (final)
  Admin → Workspace → Record attendance, work logs, claims

[Late Month]
  Admin → Workspace → Recalculate payroll → Review payroll items
  Admin → Bonus Settings → Run batch bonus calculation → Approve

[Month-End]
  Admin → Workspace → Finalize Slip → PDF generated
  Admin → Company Finance → Review P&L (manual sync currently)
  Admin → Annual Summary → Tax filing data ready
```

---

## Identified Redundancies

1. **Rule Models Sprawl** — `AttendanceRule`, `RateRule`, `LayerRateRule`, `BonusRule`, `ThresholdRule` are separate tables with no unified entry point. GG.md proposes a `PricingRule` unified table — not yet migrated.

2. **EditingJob/WorkLog vs. WorkCard/OutputMetric** — Two naming systems for the same concept. Current code uses `EditingJob`/`WorkLog`; GG.md architecture proposes `WorkCard`/`OutputMetric`. Migration path not yet started.

3. **JobModification + AuditLog** — Both track field changes on editing jobs. No deduplication policy defined.

4. **BonusRule model** — Exists in the schema but `BonusCalculationService` reads config directly from `BonusCycle` fields. `BonusRule` appears effectively unused.

5. **PayrollItem.source_flag enforcement** — Auto/manual/override semantics not clearly enforced in UI; recalculation can silently overwrite manual items if not carefully handled.

---

## Identified Gaps

1. **No Editor Self-Service** — Editors have no login portal; docs describe an "Editor Flow" that doesn't exist in the application.
2. **Bonus → Payroll Integration** — Bonus final payments are not automatically added to the payroll batch; admin must manually create a PayrollItem.
3. **No Notification Layer** — No alerts for: job deadlines, payslip ready, leave approvals, bonus cycle complete.
4. **No Real-Time P&L Sync** — Company Finance is not automatically updated when payslips are finalized.
5. **Vacation Balance Tracking** — `vacation_entitlement` exists on `Employee` but no ledger tracks consumed days.
6. **No Batch Payslip Generation** — Admin finalizes payslips one employee at a time.
7. **No Tax Simulation** — ภ.ง.ด. withholding tax is not computed.

---

## Agent Design Principles

1. **Observe, then suggest** — Agents surface findings; humans approve actions.
2. **1 Record = 1 Source of Truth** — Agents read from DB; never write without explicit approval.
3. **Audit Everything** — Every agent action creates an `AuditLog` entry.
4. **Fail loudly** — Agents surface anomalies prominently, never silently discard.

---

## Agent Roster

| ID | Agent Name | Trigger | Owner Role |
|---|---|---|---|
| A1 | PayrollGuardAgent | On payslip finalization request | Admin |
| A2 | WorkflowOrchestratorAgent | On job state change / every 6h | Admin |
| A3 | BonusEngineerAgent | On bonus cycle calculation request | Admin |
| A4 | AttendanceVerifierAgent | On attendance data save | Admin |
| A5 | ComplianceWatchdogAgent | Daily cron at 07:00 | System |
| A6 | FinanceReconcilerAgent | On payslip finalized / month-end | Admin/Owner |
| A7 | NotificationDispatchAgent | On state change events from other agents | System |

---

## A1: PayrollGuardAgent

**Role:** Pre-flight validator before any payslip is finalized.

**Trigger:** Admin clicks "Finalize Slip" — fires before actual finalization.

**Inputs:**
- `PayrollBatch` for the target month/year
- `PayrollItem[]` for the employee
- `AttendanceLog[]` for the month
- `Employee.payroll_mode` and salary profile

**Checks Performed:**
1. All working days have attendance records (no gaps)
2. No negative net pay after all deductions
3. OT hours within legal monthly cap (40h)
4. SSO deduction is calculated and does not exceed salary ceiling
5. Manual override items are flagged for human review
6. Freelance work logs all have non-zero amounts
7. No duplicate `PayrollItem` entries (same label + same amount)

**Outputs:**

| Result | Behavior |
|---|---|
| `PASS` | Finalization proceeds normally |
| `WARN` | Finalization proceeds; warnings displayed in UI per line item |
| `BLOCK` | Finalization halted; anomalies shown with item-level detail |

**Interactions:**
- Reads: `PayrollCalculationService`, `SocialSecurityService`
- Writes: `AuditLog` (action: `payroll_guard_check`, result summary)
- Does NOT modify payroll data — read-only validation

---

## A2: WorkflowOrchestratorAgent

**Role:** Ensures editing jobs don't stall in the pipeline.

**Triggers:**
- Every 6 hours (cron) — scan all open jobs
- On job state change — validate the transition and update downstream records

**Inputs:**
- All `EditingJob` records with status ≠ `final`
- `deadline_date` per job
- `started_at`, `review_ready_at` timestamps

**Checks Performed:**
1. `assigned` → no `started_at` after 2 days → flag as stalled
2. `in_progress` → past `deadline_date` with no `review_ready_at` → flag as overdue
3. `review_ready` → no admin finalization after 3 days → escalate
4. On state change: validate transition is legal (mirrors `assertTransition` in `EditingJobService`)
5. On `final`: verify `video_duration` is set (required for freelance payroll linkage)

**Outputs:**
- `StallReport[]` — list of stalled/overdue jobs with days overdue, editor name, game
- `EscalationAlert` — pushed to admin dashboard
- `DeadlineNotification` records created for jobs approaching deadline

**Interactions:**
- Reads: `EditingJob`, `JobStage`, `Employee`
- Writes: `DeadlineNotification`, `AuditLog`
- Triggers: **A7** with `job.stalled`, `job.overdue`, or `job.review_ready` events

---

## A3: BonusEngineerAgent

**Role:** Automates bonus tier assignment and validates calculations before admin approval.

**Trigger:** Admin initiates batch bonus calculation for a `BonusCycle`.

**Inputs:**
- `BonusCycle` config (penalty rates, unlock ratios, scale months)
- `BonusCycleSelectedMonth[]` — which months feed the calculation
- `WorkLog[]` for selected months (per employee)
- `AttendanceLog[]` for selected months
- `Employee.probation_end_date`, `Employee.status`

**Process Steps:**
1. **Aggregate output metrics** per employee across selected months:
   - Total duration (minutes) → `freelance_layer`
   - Total quantity → `freelance_fixed`
   - Average clips/month → `monthly_staff`
2. **Auto-assign tier** by matching output against thresholds in `PerformanceTier`
3. **Calculate attendance metrics**: `absent_days`, `late_count`, `leave_days` from `AttendanceLog`
4. **Run `BonusCalculationService.calculate()`** for each employee
5. **Validate results:**
   - Inactive employees → `actual_payment = 0`
   - `unlock_percentage` ∈ [0, `max_allocation`]
   - No negative `actual_payment`

**Outputs:**
- `BonusCalculation[]` with `status = 'calculated'`
- `BonusAuditLog[]` entries per employee
- Summary report: total allocation, employee count by tier, estimated total payout

**Interactions:**
- Reads: `WorkLog`, `AttendanceLog`, `PerformanceTier`, `BonusCycle`
- Writes: `BonusCalculation`, `BonusAuditLog`, `AttendanceAdjustment`
- Triggers: **A7** with `bonus.ready_for_review` event

---

## A4: AttendanceVerifierAgent

**Role:** Validates attendance record integrity on every save event.

**Trigger:** Any attendance save in Workspace (single row or bulk update).

**Inputs:**
- `AttendanceLog` record (before and after state)
- Adjacent `AttendanceLog` records (±14 days)
- `CompanyHoliday[]` for the month
- `AttendanceRule` (target working hours, grace period)

**Checks Performed:**
1. Check-in timestamp is before check-out (temporal sanity)
2. Working minutes > 0 when `day_type = workday`
3. Resulting streak does not exceed 6 consecutive workdays
4. OT minutes present only when `ot_enabled = true`
5. `late_minutes` is consistent with `check_in` vs `target_checkin`
6. Swapped days correctly reference a valid `swapped_from_day_type`
7. `lwop_flag` is only set on days without an approved `LeaveRequest`

**Outputs:**

| Result | Behavior |
|---|---|
| `VALID` | Save proceeds |
| `WARN` | Save proceeds; warning shown in grid cell (e.g. OT detected but disabled) |
| `ERROR` | Save blocked; error shown inline (e.g. checkout before checkin) |

**Interactions:**
- Reads: `AttendanceLog`, `CompanyHoliday`, `AttendanceRule`, `LeaveRequest`
- Writes: `AuditLog` (validation failures only)
- Runs before the save — does not block downstream payroll recalculation

---

## A5: ComplianceWatchdogAgent

**Role:** Daily scan for Thai labor law violations and internal rule breaches.

**Trigger:** Daily cron at 07:00 (before business hours start).

**Inputs:**
- All active `Employee` records
- `AttendanceLog[]` for current and prior month
- `SocialSecurityConfig` (current rate and contribution ceiling)
- `AttendanceRule` (OT cap, working hours standard)

**Checks Performed:**
1. **OT Limit** — Any employee exceeding 40h OT in a calendar month
2. **Consecutive Workday Limit** — Any stretch of 7+ consecutive workdays
3. **SSO Cap** — Any calculated SSO deduction exceeding `max_contribution`
4. **Missing Attendance** — Active employees with no records for > 3 consecutive working days
5. **Probation Expiry** — Employees whose `probation_end_date` has passed without a `status` update
6. **Payslip Gaps** — Active employees with no finalized `Payslip` for completed prior months

**Outputs:**
- `ComplianceReport` grouped by violation type and severity (`warn` / `critical`)
- Critical violations → persistent alert on admin dashboard
- Warn violations → weekly digest summary

**Interactions:**
- Reads: `Employee`, `AttendanceLog`, `Payslip`, `SocialSecurityConfig`
- Writes: `AuditLog` (action: `compliance_scan`, with findings count)
- Triggers: **A7** with `compliance.critical_violation` for critical findings

---

## A6: FinanceReconcilerAgent

**Role:** Keeps Company Finance P&L automatically in sync with finalized payroll costs.

**Triggers:**
- When a `Payslip.status` is set to `finalized`
- On the 1st of each month (for the prior month's totals)

**Inputs:**
- All `Payslip` records for the target month with `status = 'finalized'`
- Existing `CompanyExpense[]` for the month
- `PayrollItem[]` grouped by category (salary, bonus, SSO employer share)

**Process Steps:**
1. Sum all finalized payslips: `net_pay` total, employer SSO total, bonus total
2. Check if a `CompanyExpense` entry with category `payroll` already exists for the month
3. If absent: create a draft `CompanyExpense` entry for admin approval
4. If present: compare stored amount vs. calculated total — flag if delta > ±1%
5. Alert if no `CompanyRevenue` entries exist for the same month

**Outputs:**
- `ReconciliationReport` — month, expected vs. recorded expense, delta
- Draft `CompanyExpense` entry with `source_flag = 'agent_draft'` awaiting approval
- P&L dashboard reflects actual costs once admin approves

**Interactions:**
- Reads: `Payslip`, `PayrollItem`, `CompanyExpense`, `CompanyRevenue`
- Writes (draft only): `CompanyExpense` with `source_flag = 'agent_draft'`
- Triggers: **A7** with `finance.reconciliation_ready` event

---

## A7: NotificationDispatchAgent

**Role:** Unified notification router — delivers the right event to the right audience.

**Trigger:** Called by other agents (A2, A3, A5, A6) or on direct system events.

**Event Registry:**

| Event Key | Audience | Priority |
|---|---|---|
| `job.stalled` | Admin | Medium |
| `job.overdue` | Admin + assigned Editor | High |
| `job.review_ready` | Admin | Medium |
| `payslip.finalized` | Employee | Low |
| `bonus.ready_for_review` | Admin | Medium |
| `compliance.critical_violation` | Admin | Critical |
| `finance.reconciliation_ready` | Admin + Owner | Medium |
| `leave.pending_review` | Admin | Low |

**Process:**
1. Receive event payload (`type`, `entity_id`, `metadata`)
2. Resolve audience by role and/or specific `employee_id`
3. Determine delivery channel: in-app alert (now), email (when mail driver is configured)
4. De-duplicate: suppress if identical notification was sent < 24h ago
5. Record in `notification_logs` (for audit and replay)

**Outputs:**
- In-app notification (dashboard badge / alert panel)
- Email queued (future — when Laravel mail driver is configured)
- `NotificationLog` entry (`notification_type`, `recipient_id`, `sent_at`, `channel`)

**Interactions:**
- Reads: `Employee`, `User`, role assignments
- Writes: `notification_logs` table, in-app alert store
- Called by: A2, A3, A5, A6 — never self-triggered

---

## Agent Interaction Map

```
[Cron: daily 07:00]
  ComplianceWatchdogAgent (A5)
    └─ critical violation → NotificationDispatchAgent (A7)

[Cron: every 6h]
  WorkflowOrchestratorAgent (A2)
    └─ stall / overdue / escalation → NotificationDispatchAgent (A7)

[Admin: initiate bonus calc]
  BonusEngineerAgent (A3)
    └─ cycle calculated → NotificationDispatchAgent (A7)

[Admin: save attendance]
  AttendanceVerifierAgent (A4)
    └─ VALID / WARN / ERROR → inline UI response

[Admin: finalize payslip]
  PayrollGuardAgent (A1)
    └─ PASS →  finalization proceeds
              FinanceReconcilerAgent (A6)
                └─ reconciliation draft → NotificationDispatchAgent (A7)
    └─ BLOCK → finalization halted, UI shows line-item anomalies
```

---

## Implementation Requirements

### New DB Tables
| Table | Purpose |
|---|---|
| `notification_logs` | A7 audit trail (`type`, `recipient_id`, `sent_at`, `channel`, `payload`) |

### New Columns
| Table | Column | Purpose |
|---|---|---|
| `company_expenses` | `source_flag` | Distinguish `'manual'` from `'agent_draft'` entries |

### Existing Services to Reuse
| Service | Used By |
|---|---|
| `app/Services/AuditLogService.php` | All agents (A1–A7) |
| `app/Services/Payroll/PayrollCalculationService.php` | A1 |
| `app/Services/BonusCalculationService.php` | A3 |
| `app/Services/SocialSecurityService.php` | A1, A5 |
| `app/Services/WorkCalendarService.php` | A4, A5 |

### Implementation Priority
| Order | Agent | Rationale |
|---|---|---|
| 1 | **A4** AttendanceVerifier | Prevents bad data from entering payroll |
| 2 | **A1** PayrollGuard | Prevents incorrect payslip finalization |
| 3 | **A2** WorkflowOrchestrator | Keeps editing pipeline flowing |
| 4 | **A5** ComplianceWatchdog | Legal protection (Thai labor law) |
| 5 | **A3** BonusEngineer | Automates the manual bonus workflow |
| 6 | **A6** FinanceReconciler | Closes the P&L sync gap |
| 7 | **A7** NotificationDispatch | Enables all inter-agent communication |
