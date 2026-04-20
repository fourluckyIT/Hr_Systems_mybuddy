# Production-Based Payroll System Refactor Blueprint

Now I have full context. Here is the complete Production-Based Payroll System refactor blueprint.

## A. Core Flow (Step-by-Step)

### The fundamental shift: Work -> Output -> Pricing -> Payroll

```text
┌─────────────────────────────────────────────────────────────┐
│ 1. ASSIGN        Admin creates Work Card, assigns Editor    │
│    State: draft                                             │
├─────────────────────────────────────────────────────────────┤
│ 2. START         Editor accepts & begins work               │
│    State: in_progress                                       │
├─────────────────────────────────────────────────────────────┤
│ 3. SUBMIT        Editor submits output (minutes/clips/job)  │
│    State: submitted                                         │
│    → System auto-logs OutputMetric                          │
├─────────────────────────────────────────────────────────────┤
│ 4. REVIEW        Admin reviews quality + output metrics     │
│    State: approved | revision_needed                        │
│    → On approve: OutputMetric locked                        │
├─────────────────────────────────────────────────────────────┤
│ 5. PRICE         System applies PricingRule to output       │
│    → Layer mode:  minutes × rate_per_minute × layer        │
│    → Fixed mode:  count × fixed_rate                        │
│    → Monthly:     base_salary + rule-based adjustments     │
│    State: priced                                            │
├─────────────────────────────────────────────────────────────┤
│ 6. AGGREGATE     Monthly payroll batch collects all priced  │
│                  work for each editor                       │
│    State: aggregated                                        │
├─────────────────────────────────────────────────────────────┤
│ 7. FINALIZE      Admin reviews total, triggers payslip      │
│    State: finalized                                         │
│    → Snapshot to PayslipItem (immutable)                    │
├─────────────────────────────────────────────────────────────┤
│ 8. PAYOUT        Mark as paid / generate PDF                │
│    State: paid                                              │
└─────────────────────────────────────────────────────────────┘
```

### Monthly Staff adaptation

Monthly editors still flow through this pipeline; their work is attendance-based:

- Work = attend office + complete assigned editing jobs
- Output = attendance hours + OT + editing job completions
- Pricing = base_salary ± diligence ± OT ± late deduction ± SSO

### State machine (unified for all modes)

| State | Trigger | Who | Next |
|---|---|---|---|
| draft | Admin creates card | Admin | in_progress |
| in_progress | Editor starts | Editor | submitted |
| submitted | Editor marks done | Editor | approved / revision_needed |
| revision_needed | Admin sends back | Admin | in_progress |
| approved | Admin accepts | Admin | priced |
| priced | System auto-calc | System | aggregated |
| aggregated | Batch collection | System | finalized |
| finalized | Admin locks payslip | Admin | paid |
| paid | Payment confirmed | Admin | Terminal |

## B. Role Experience

### ADMIN - Production Commander

Mental model: I manage a pipeline, not people.

| Zone | What they see | Action |
|---|---|---|
| Production Dashboard | This month pipeline KPIs: total work cards, bottlenecks by state, total output value, anomaly flags | Quick-filter by state/editor |
| Work Queue | All active Work Cards (Kanban: draft -> in_progress -> submitted -> approved) | Assign, Review, Approve, Send back |
| Review Panel | Submitted work detail: output metrics, pricing preview, editor notes, job history | Approve / Request revision + reason |
| Payroll Summary | Per-editor aggregated total: income, deductions, net. Batch actions | Recalculate, Finalize batch, Generate payslips |
| Settings | Pricing rules, SSO config, Diligence tiers, Holidays | Update rules (with audit trail) |

Key design rules for Admin:

- No employee-centric landing page. Entry point is the Production Dashboard
- Employee Board becomes a secondary lookup, not the home screen
- Batch operations first (finalize all ready payslips), per-person drill-in second
- Anomaly detection surfaced proactively (missing submissions, overdue, pricing outliers)

### EDITOR (Monthly) - My Work, My Money

Mental model: I do work, I see what I earn.

| Zone | What they see | Action |
|---|---|---|
| My Work | Active task cards assigned to them. Status badges. Deadlines | Start, Submit, Add notes |
| My Earnings | Real-time preview: base salary + completed work value + deductions = net | View payslip history, Download PDF |

Key design rules for Editor:

- Zero navigation complexity: 2 tabs only
- No exposure to other editors data, company finance, or admin tools
- Work status drives everything: card states are the UI
- Earnings preview updates live as work cards move to approved

## C. Screen Architecture

### Admin (4 screens + 1 settings hub)

```text
┌─ ADMIN SCREENS ───────────────────────────────────────────┐
│                                                           │
│  1. Production Dashboard       [HOME]                     │
│     ├── KPI cards (total output, total value, overdue)    │
│     ├── Pipeline chart (cards by state)                   │
│     ├── Anomaly alerts                                    │
│     └── Quick actions: Assign Work, Batch Finalize        │
│                                                           │
│  2. Work Queue                 [PIPELINE]                 │
│     ├── Kanban or filtered list view                      │
│     ├── Filter: editor, game, state, date range           │
│     ├── Bulk actions: approve selected, reassign          │
│     └── Click card → Review Panel (slide-over)            │
│                                                           │
│  3. Review & Approval          [REVIEW]                   │
│     ├── Work Card detail (full screen or drawer)          │
│     ├── Output metrics + pricing preview                  │
│     ├── Editor history / past submissions                 │
│     └── Actions: Approve, Revision, Reject                │
│                                                           │
│  4. Payroll Summary            [PAYROLL]                  │
│     ├── Month selector                                    │
│     ├── Editor rows: total income / deduction / net       │
│     ├── Status badges: ready / needs recalc / finalized   │
│     ├── Batch: Recalculate All, Finalize All              │
│     └── Drill-in: editor payslip detail + PDF             │
│                                                           │
│  5. Settings Hub               [SETTINGS]                 │
│     ├── Pricing Rules (layer rates, fixed rates)          │
│     ├── Attendance Rules (OT, late, diligence)            │
│     ├── SSO Config                                        │
│     ├── Games / Work Types                                │
│     ├── Holidays                                          │
│     └── Company Profile                                   │
│                                                           │
└───────────────────────────────────────────────────────────┘
```

### Editor (2 screens)

```text
┌─ EDITOR SCREENS ──────────────────────────────────────────┐
│                                                           │
│  1. My Work                    [HOME]                     │
│     ├── Active cards (sorted by deadline)                 │
│     ├── Status groups: To Do / In Progress / Submitted    │
│     ├── Actions: Start, Submit, Add log                   │
│     └── Completed archive (collapsed)                     │
│                                                           │
│  2. My Earnings                [EARNINGS]                 │
│     ├── Current month preview (live calc)                 │
│     │   ├── Base salary                                   │
│     │   ├── Work-based income (from approved cards)       │
│     │   ├── Deductions                                    │
│     │   └── Net pay                                       │
│     ├── Past months history                               │
│     └── Download payslip PDF                              │
│                                                           │
└───────────────────────────────────────────────────────────┘
```

### Screens eliminated from current system

| Current Screen | Action | Reason |
|---|---|---|
| Employee Board (as home) | Demote to Settings sub-page | Not production-centric |
| Employee Workspace (mega-page) | Split into Work Queue + Payroll Summary | Too many concerns in one screen |
| Calendar (full weekly) | Merge deadline view into Work Queue | Calendar is secondary data, not primary workflow |
| Company Finance | Keep but move to admin sidebar | Not daily workflow |
| Annual Summary | Keep but move to admin sidebar | Reporting, not operational |
| Audit Log | Keep but move to admin sidebar | Forensic, not operational |
| Master Data (6 tabs) | Consolidate into Settings Hub | Over-fragmented |

### Navigation (final)

Admin nav:

```text
[Dashboard] [Work Queue] [Payroll] [Settings ▾]
                                    ├── Rules & Pricing
                                    ├── Editors & Teams
                                    ├── Company & Finance
                                    └── Audit Log
```

Editor nav:

```text
[My Work] [My Earnings]
```

## D. Work Card Design

The Work Card is the atomic unit of the entire system.

### Card anatomy

```text
┌──────────────────────────────────────────────────┐
│ ● IN_PROGRESS              Deadline: 17 Apr 2026 │
│──────────────────────────────────────────────────│
│ 🎮 ROX พาร์ทที่ 3                                │
│ Game: ROX                                        │
│──────────────────────────────────────────────────│
│ Editor: สมชาย                                    │
│ Type: freelance_layer                            │
│──────────────────────────────────────────────────│
│ OUTPUT                                           │
│   Duration: 45:30 (45 min 30 sec)                │
│   Layers: 3                                      │
│──────────────────────────────────────────────────│
│ PRICING                        ฿ 2,847.50        │
│   Rate: 21.00/min × layer_3                      │
│──────────────────────────────────────────────────│
│  [Submit ▶]   [View Detail]   [⋯]               │
└──────────────────────────────────────────────────┘
```

### Card states (visual language)

| State | Badge Color | Border | CTA |
|---|---|---|---|
| draft | Gray | Dashed | Assign -> |
| in_progress | Blue | Solid blue-left | Submit ▶ |
| submitted | Amber | Solid amber-left | Review ▶ |
| revision_needed | Red | Solid red-left | Revise ▶ |
| approved | Green | Solid green-left | - (auto-priced) |
| priced | Indigo | Solid indigo-left | - (in batch) |
| finalized | Purple | Solid purple-left | View Payslip |
| paid | Gray-green | None | Archive |

### Card variants by pricing mode

| Mode | OUTPUT section shows | PRICING section shows |
|---|---|---|
| monthly_staff | Attendance summary (hours, late, OT) | Base salary ± adjustments |
| freelance_layer | Duration (min:sec) + layer count | minutes × rate × layer |
| freelance_fixed | Job count / quantity | count × fixed_rate |

### Card interactivity

- Click: Opens detail drawer (full output log, pricing breakdown, audit history)
- Actions (contextual by role + state):
  - Editor: Start, Submit, Add work log
  - Admin: Approve, Send back, Reassign, Delete
- Drag (Kanban): Move between states (admin only, with validation)

## E. Data Model (Structured)

### Entity Relationship Map

```text
┌──────────┐     ┌──────────────┐     ┌──────────────┐
│  Editor  │────▶│   WorkCard   │────▶│ OutputMetric │
│  (User)  │  1:M│              │  1:M│              │
└──────────┘     │  job_name    │     │  metric_type │
                 │  game_id     │     │  duration_min│
                 │  pricing_mode│     │  layer_count │
                 │  status      │     │  quantity    │
                 │  deadline    │     │  logged_at   │
                 │  assigned_to │     └──────┬───────┘
                 │  assigned_by │            │
                 └──────┬───────┘            │ applies
                        │                    ▼
                        │            ┌──────────────┐
                        │            │ PricingRule  │
                        │            │              │
                        │            │  mode        │
                        │            │  rate        │
                        │            │  layer_config│
                        │            │  effective_dt│
                        │            └──────┬───────┘
                        │                   │
                        │ aggregated        │ produces
                        ▼                   ▼
                 ┌──────────────┐     ┌──────────────┐
                 │ PayrollBatch │     │ PayrollItem  │
                 │              │◀────│              │
                 │  month/year  │  1:M│  work_card_id│
                 │  status      │     │  amount      │
                 └──────┬───────┘     │  source_flag │
                        │             └──────────────┘
                        │ finalize
                        ▼
                 ┌──────────────┐     ┌──────────────┐
                 │   Payslip    │────▶│ PayslipItem  │
                 │              │  1:M│              │
                 │  net_pay     │     │  label       │
                 │  finalized_at│     │  amount      │
                 └──────────────┘     └──────────────┘
```

### Entity definitions

#### WorkCard (replaces/evolves EditingJob + WorkAssignment)

| Field | Type | Notes |
|---|---|---|
| id | bigint | PK |
| job_name | string | |
| game_id | FK -> games | Category |
| assigned_to | FK -> employees | Editor |
| assigned_by | FK -> users | Admin |
| pricing_mode | enum | monthly_staff / freelance_layer / freelance_fixed |
| status | enum | draft -> in_progress -> submitted -> approved -> priced -> aggregated -> finalized -> paid |
| deadline_date | date | |
| started_at | datetime | Null until started |
| submitted_at | datetime | Null until submitted |
| approved_at | datetime | Null until approved |
| approved_by | FK -> users | |
| revision_notes | text | If sent back |
| notes | text | |
| timestamps | | |

#### OutputMetric (replaces/evolves WorkLog)

| Field | Type | Notes |
|---|---|---|
| id | bigint | PK |
| work_card_id | FK -> work_cards | |
| employee_id | FK -> employees | Denormalized for query speed |
| metric_type | enum | duration / quantity / attendance |
| hours | int | |
| minutes | int | |
| seconds | int | |
| layer | int | For layer pricing |
| quantity | int | For fixed pricing |
| rate_applied | decimal(12,4) | Snapshot of rate at calc time |
| amount | decimal(12,2) | Calculated value |
| logged_at | date | |
| month / year | int | For aggregation |
| source_flag | enum | auto / manual / override |
| is_disabled | bool | Skip toggle |
| sort_order | int | |

#### PricingRule (replaces LayerRateRule + RateRule + AttendanceRule)

| Field | Type | Notes |
|---|---|---|
| id | bigint | PK |
| rule_type | enum | layer_rate / fixed_rate / ot_rate / diligence / late_deduction / sso |
| pricing_mode | enum | Which work mode this applies to |
| employee_id | FK nullable | Null = global, set = per-editor override |
| config | json | Rate tables, thresholds, tiers |
| effective_date | date | |
| is_active | bool | |

#### BonusCycle (unchanged structure, integrates with OutputMetric)

| Field | Type | Notes |
|---|---|---|
| id | bigint | PK |
| cycle_code | string | e.g. 2026-H1 |
| cycle_period | enum | H1 / H2 |
| payment_date | date | Jun or Dec |
| max_allocation | decimal | Budget cap |
| status | enum | draft / calculating / approved / paid |

#### BonusCalculation (links to output data)

| Field | Type | Notes |
|---|---|---|
| employee_id | FK | |
| cycle_id | FK | |
| base_reference | decimal | Derived from OutputMetric aggregation |
| tier_id | FK -> PerformanceTier | Auto-resolved from avg clip minutes |
| tier_multiplier | decimal | |
| attendance_adjustment | decimal | Behavior factor |
| final_bonus_net | decimal | |
| actual_payment | decimal | After probation unlock % |

### Data ownership

| Entity | Admin | Editor |
|---|---|---|
| WorkCard | CRUD + state transitions | Read own + Start/Submit |
| OutputMetric | Read all + override | Create own (via submit) |
| PricingRule | CRUD | Read (to understand earnings) |
| PayrollBatch | Create + finalize | - |
| Payslip | Generate + view all | Read own + download |
| BonusCycle | Full control | Read own calculation |

## F. Bonus Integration Flow

### Trigger: Semi-annual (June / December)

```text
┌─ BONUS PIPELINE ──────────────────────────────────────────┐
│                                                           │
│  1. CREATE CYCLE                                          │
│     Admin creates BonusCycle (e.g. 2026-H1)               │
│     Selects qualifying months (e.g. Jan-Jun)              │
│                                                           │
│  2. AGGREGATE OUTPUT                                      │
│     System queries OutputMetric for selected months:      │
│     → Total clip minutes per editor per month             │
│     → Average clip minutes / month                        │
│                                                           │
│  3. AUTO-TIER                                             │
│     For each editor:                                      │
│     avg_clip_minutes → PerformanceTier match              │
│     ┌────────────────────────────────────────────┐        │
│     │ Tier A: ≥300 min/mo → multiplier 1.5       │        │
│     │ Tier B: ≥200 min/mo → multiplier 1.2       │        │
│     │ Tier C: ≥100 min/mo → multiplier 1.0       │        │
│     │ Tier D: <100 min/mo → multiplier 0.5       │        │
│     └────────────────────────────────────────────┘        │
│                                                           │
│  4. BEHAVIOR ADJUSTMENT                                   │
│     Attendance factor per editor:                         │
│     → late_count, lwop_days from AttendanceLog            │
│     → Adjustment multiplier (0.0 to 1.0)                  │
│     Admin can override per person                         │
│                                                           │
│  5. CALCULATE                                             │
│     base = max_allocation / num_eligible_editors          │
│     tier_bonus = base × tier_multiplier                   │
│     adjusted = tier_bonus × attendance_adjustment         │
│     probation_factor = months_after_probation unlock %    │
│     actual_payment = adjusted × probation_factor          │
│                                                           │
│  6. REVIEW                                                │
│     Admin reviews per-editor breakdown                    │
│     Can override tier, adjustment, or final amount        │
│     Each override logged to BonusAuditLog                 │
│                                                           │
│  7. APPROVE & PAY                                         │
│     Cycle status -> approved -> paid                      │
│     actual_payment added to PayrollItem for payout month  │
│     Payslip snapshot includes bonus line item             │
│                                                           │
└───────────────────────────────────────────────────────────┘
```

### Traceability guarantee

Every bonus amount is explainable:

```text
actual_payment = (max_allocation / editors)
               × tier_multiplier
               × attendance_adjustment
               × probation_unlock%
```

All intermediate values stored; all overrides audited with reason + who + when.

## G. Top 10 Refactor Actions (Priority Order)

| # | Action | What changes | Why first |
|---|---|---|---|
| 1 | Unify WorkCard model | Merge EditingJob + WorkAssignment into single WorkCard with unified state machine | This is the atomic unit - everything flows from it |
| 2 | Create OutputMetric table | Refactor WorkLog -> OutputMetric with work_card_id FK + metric_type enum | Decouples output measurement from pricing mode |
| 3 | Build Production Dashboard | New admin home: pipeline KPIs, state counts, anomaly flags. Replace Employee Board as landing page | Aligns admin mental model to production flow |
| 4 | Build Work Queue (Kanban) | Kanban board of WorkCards by state, with filter/sort/batch approve | Core operational screen; replaces per-employee workspace for work management |
| 5 | Build Editor My Work screen | Card-based task view for the editor role. 2-tab layout: My Work + My Earnings | Editor UX is currently broken (sees admin-level workspace with disabled sections) |
| 6 | Consolidate PricingRule | Merge LayerRateRule + RateRule + AttendanceRule into unified PricingRule with rule_type + config JSON | Single source for all pricing logic; eliminates config fragmentation |
| 7 | Refactor PayrollCalculationService | Calculator reads from OutputMetric (approved) + PricingRule instead of querying raw logs directly | Clean separation: Output -> Pricing -> PayrollItem pipeline |
| 8 | Fix nav permission matrix | Replace stale hasAnyRole checks in app layout and views with single isAdmin | Prevents incorrect menu visibility |
| 9 | Build batch payroll finalization | Payroll Summary page with per-editor rows + batch Recalculate All / Finalize All | Current system requires clicking into each employee individually |
| 10 | Integrate Bonus into payroll flow | Bonus actual_payment -> auto-inject as PayrollItem in payout month (June/December) | Closes the loop: Work -> Output -> Pricing -> Payroll -> Bonus -> Payout |

### Migration path (incremental, not big-bang)

#### Phase 1 - Foundation (Week 1-2): Items #1, #2, #8

- Create work_cards migration (copy from editing_jobs + extend)
- Create output_metrics migration (evolve from work_logs)
- Fix nav permissions
- Both old and new tables coexist; write to both during transition

#### Phase 2 - Core UI (Week 3-4): Items #3, #4, #5

- Production Dashboard (new page, new route)
- Work Queue (Kanban)
- Editor My Work / My Earnings
- Old screens remain accessible but demoted in nav

#### Phase 3 - Engine (Week 5-6): Items #6, #7, #9

- PricingRule consolidation
- Calculator refactor
- Batch payroll

#### Phase 4 - Integration (Week 7-8): Item #10

- Bonus pipeline auto-injects into payroll
- Full end-to-end test: Assign -> Submit -> Approve -> Price -> Finalize -> Pay

### What gets deleted after full migration

| Current Entity | Replacement | Delete when |
|---|---|---|
| EditingJob | WorkCard | Phase 2 complete |
| WorkAssignment | WorkCard | Phase 2 complete |
| WorkLog (for pricing) | OutputMetric | Phase 3 complete |
| LayerRateRule | PricingRule | Phase 3 complete |
| RateRule | PricingRule | Phase 3 complete |
| Employee Board as home | Production Dashboard | Phase 2 complete |
| Workspace mega-page | Work Queue + Payroll Summary | Phase 3 complete |

### What stays (adapted)

| Entity | Adaptation |
|---|---|
| Employee | Becomes Editor profile; still holds payroll_mode, salary, bank |
| AttendanceLog | Stays for monthly_staff; feeds into OutputMetric as metric_type=attendance |
| PayrollBatch/Item/Payslip/PayslipItem | Unchanged structure; sourced differently |
| BonusCycle/Calculation/Tier | Unchanged; input changes from raw WorkLog to OutputMetric |
| AuditLog | Unchanged; more entities audited |
| Game | Stays as work categorization |
| CompanyRevenue/Expense/Subscription | Stays; demoted to Settings -> Company & Finance |
