# Project Overview

<cite>
**Referenced Files in This Document**
- [AGENTS.md](file://AGENTS.md)
</cite>

## Table of Contents
1. [Introduction](#introduction)
2. [Project Structure](#project-structure)
3. [Core Components](#core-components)
4. [Architecture Overview](#architecture-overview)
5. [Detailed Component Analysis](#detailed-component-analysis)
6. [Dependency Analysis](#dependency-analysis)
7. [Performance Considerations](#performance-considerations)
8. [Troubleshooting Guide](#troubleshooting-guide)
9. [Conclusion](#conclusion)
10. [Appendices](#appendices)

## Introduction
The xHR Payroll & Finance System is a modern replacement for Excel-based payroll management, designed to streamline and secure financial operations for organizations. It targets HR professionals and finance teams who manage diverse payroll modes including monthly staff, freelancers (layer and fixed rates), YouTubers/Talent (salary and settlement), social security contributions, bonuses, and company finance summaries. The system emphasizes automation, auditability, and compliance while preserving the familiar spreadsheet-like user experience.

Key benefits include:
- Automated calculations driven by configurable rules
- Complete audit trails for transparency and compliance
- PDF payslip generation with standardized templates
- Dynamic, controlled editing that feels like Excel but enforces data integrity
- Maintainability-first architecture enabling future enhancements

## Project Structure
The repository currently includes a comprehensive guide that defines the system’s purpose, design principles, technology stack, domain model, agent roles, required modules, database guidelines, business rules, UI behaviors, audit requirements, coding standards, folder structure guidance, change management, anti-patterns, and minimum deliverables. This guide serves as the foundational blueprint for building the system.

```mermaid
graph TB
A["Repository Root"] --> B["AGENTS.md<br/>Project Overview and System Blueprint"]
B --> C["Project Overview"]
B --> D["Core Design Principles"]
B --> E["Technology Constraints"]
B --> F["Domain Model"]
B --> G["Agent Responsibilities"]
B --> H["Required Modules"]
B --> I["Database Guidelines"]
B --> J["Business Rules"]
B --> K["Dynamic UI Behavior"]
B --> L["Payslip Requirements"]
B --> M["Audit Requirements"]
B --> N["Coding Standards"]
B --> O["Folder Structure Guidance"]
B --> P["Change Management Rules"]
B --> Q["Anti-Patterns"]
B --> R["Minimum Deliverables"]
B --> S["Definition of Done"]
```

**Diagram sources**
- [AGENTS.md:1-721](file://AGENTS.md#L1-L721)

**Section sources**
- [AGENTS.md:9-31](file://AGENTS.md#L9-L31)
- [AGENTS.md:102-118](file://AGENTS.md#L102-L118)
- [AGENTS.md:121-151](file://AGENTS.md#L121-L151)
- [AGENTS.md:153-284](file://AGENTS.md#L153-L284)
- [AGENTS.md:286-383](file://AGENTS.md#L286-L383)
- [AGENTS.md:385-436](file://AGENTS.md#L385-L436)
- [AGENTS.md:438-506](file://AGENTS.md#L438-L506)
- [AGENTS.md:508-547](file://AGENTS.md#L508-L547)
- [AGENTS.md:549-574](file://AGENTS.md#L549-L574)
- [AGENTS.md:576-596](file://AGENTS.md#L576-L596)
- [AGENTS.md:598-620](file://AGENTS.md#L598-L620)
- [AGENTS.md:622-647](file://AGENTS.md#L622-L647)
- [AGENTS.md:650-672](file://AGENTS.md#L650-L672)
- [AGENTS.md:675-710](file://AGENTS.md#L675-L710)

## Core Components
The system is built around a set of core components that align with the agent responsibilities and required modules. These components ensure modularity, maintainability, and scalability.

- Authentication and Authorization: Login/logout, role/permission management
- Employee Management: Add/edit employees, assign payroll modes, departments, positions, bank info, and SSO eligibility
- Employee Board: Card/grid list, search, filters, and quick access to workspaces
- Employee Workspace: Central interface for payroll entry, real-time recalculation, payslip preview, audit timeline, and inspector
- Attendance Module: Check-in/check-out, late minutes, early leave, OT enablement, LWOP flag
- Work Log Module: Date, work type, quantity/time units, layer, rate, amount
- Payroll Engine: Mode-specific calculations, income/deduction aggregation, manual override support, snapshot production
- Rule Manager: Configurable rules for attendance, OT, bonuses, thresholds, layer rates, SSO, taxes, and module toggles
- Payslip Module: Preview, finalize, export PDF, regeneration controls
- Annual Summary: 12-month view, employee summary, annual totals, export
- Company Finance Summary: Revenue, expenses, profit/loss, cumulative, quarterly, tax simulation
- Subscription and Extra Costs: Recurring software, fixed costs, equipment, dubbing, other business expenses

These components collectively address common payroll challenges such as inconsistent calculations, lack of auditability, manual errors, and difficulty in maintaining historical records.

**Section sources**
- [AGENTS.md:288-382](file://AGENTS.md#L288-L382)
- [AGENTS.md:338-353](file://AGENTS.md#L338-L353)
- [AGENTS.md:354-366](file://AGENTS.md#L354-L366)
- [AGENTS.md:367-382](file://AGENTS.md#L367-L382)

## Architecture Overview
The system follows a PHP-first, Laravel-oriented architecture with MySQL/phpMyAdmin compatibility. It enforces rule-driven, dynamic data entry with strong auditability and maintainability. The architecture separates concerns across agents, ensuring clean boundaries and reusable services.

```mermaid
graph TB
subgraph "Presentation Layer"
UI["Blade Views<br/>Vanilla JS / Alpine.js"]
end
subgraph "Application Layer"
Auth["Authentication Service"]
EmpBoard["Employee Board Service"]
EmpWS["Employee Workspace Service"]
PayEngine["Payroll Calculation Service"]
RuleMgr["Rule Manager Service"]
Payslip["Payslip Builder + PDF Service"]
Audit["Audit Log Service"]
Reports["Annual Summary + Company Finance Services"]
end
subgraph "Domain Layer"
Models["Eloquent Models<br/>Employees, Payroll Items, Payslips, Rules, Audit Logs"]
end
subgraph "Data Layer"
DB["MySQL Database<br/>phpMyAdmin Compatible Schema"]
end
UI --> Auth
UI --> EmpBoard
UI --> EmpWS
EmpWS --> PayEngine
EmpWS --> RuleMgr
EmpWS --> Payslip
EmpWS --> Audit
EmpWS --> Reports
PayEngine --> Models
RuleMgr --> Models
Payslip --> Models
Audit --> Models
Reports --> Models
Models --> DB
```

**Diagram sources**
- [AGENTS.md:104-110](file://AGENTS.md#L104-L110)
- [AGENTS.md:196-221](file://AGENTS.md#L196-L221)
- [AGENTS.md:245-256](file://AGENTS.md#L245-L256)
- [AGENTS.md:257-271](file://AGENTS.md#L257-L271)
- [AGENTS.md:636-647](file://AGENTS.md#L636-L647)

## Detailed Component Analysis

### Payroll Modes and Business Value
The system supports multiple payroll modes to meet varied organizational needs:
- Monthly staff: Base salary plus overtime, allowances, performance bonuses, and deductions
- Freelance layer: Minute-based work logs with layered rates
- Freelance fixed: Fixed-rate jobs with quantity-based amounts
- YouTuber/Talent salary and settlement: Specialized modules for creators’ compensation and settlement

Business value:
- Reduces manual effort and human errors typical in Excel-based systems
- Provides standardized, auditable calculations aligned with local regulations (e.g., social security)
- Enables quick scenario modeling and rule changes without disrupting existing data

**Section sources**
- [AGENTS.md:123-131](file://AGENTS.md#L123-L131)
- [AGENTS.md:440-487](file://AGENTS.md#L440-L487)

### Rule-Driven Calculations
Rules are stored in configuration tables and applied dynamically during payroll runs. This ensures:
- Consistency across calculations
- Easy updates to formulas and thresholds
- Traceability of changes through audit logs

Common rule categories:
- Overtime, diligence allowance, performance thresholds, layer rates, social security, bonuses, deductions, module toggles

**Section sources**
- [AGENTS.md:61-74](file://AGENTS.md#L61-L74)
- [AGENTS.md:344-352](file://AGENTS.md#L344-L352)
- [AGENTS.md:454-471](file://AGENTS.md#L454-L471)

### Dynamic UI and User Experience
The UI mimics spreadsheet behavior with inline editing, instant recalculation, and preview capabilities. Users can:
- Add/remove/duplicate rows
- Toggle between auto/manual/override states
- Inspect row sources and audit history
- Finalize payslips with snapshot protection

```mermaid
sequenceDiagram
participant User as "User"
participant Workspace as "Employee Workspace"
participant Engine as "Payroll Calculation Service"
participant Rules as "Rule Manager Service"
participant Payslip as "Payslip Builder + PDF"
participant Audit as "Audit Log Service"
User->>Workspace : "Edit grid fields"
Workspace->>Engine : "Trigger recalculation"
Engine->>Rules : "Apply applicable rules"
Rules-->>Engine : "Calculated values"
Engine-->>Workspace : "Updated totals"
Workspace->>Payslip : "Preview payslip"
User->>Workspace : "Save and finalize"
Workspace->>Payslip : "Generate PDF"
Payslip-->>Workspace : "PDF ready"
Workspace->>Audit : "Log changes and actions"
Audit-->>Workspace : "Audit trail updated"
```

**Diagram sources**
- [AGENTS.md:513-515](file://AGENTS.md#L513-L515)
- [AGENTS.md:517-527](file://AGENTS.md#L517-L527)
- [AGENTS.md:539-546](file://AGENTS.md#L539-L546)
- [AGENTS.md:567-573](file://AGENTS.md#L567-L573)

**Section sources**
- [AGENTS.md:508-547](file://AGENTS.md#L508-L547)
- [AGENTS.md:549-574](file://AGENTS.md#L549-L574)

### Audit and Compliance
The system mandates comprehensive audit logging for high-risk changes and operations:
- Who changed what, when, and why
- Old/new values and action types
- Audit coverage for salary profiles, payroll items, payslip edits, rule changes, and module toggles

```mermaid
flowchart TD
Start(["Change Request"]) --> Identify["Identify Affected Entities"]
Identify --> Determine["Determine Audit Scope"]
Determine --> Log["Record Audit Entry<br/>Who, What, When, Why, Values"]
Log --> Review{"Review Required?"}
Review --> |Yes| Approve["Approve Change"]
Review --> |No| Apply["Apply Change"]
Approve --> Apply
Apply --> End(["Change Complete"])
```

**Diagram sources**
- [AGENTS.md:578-596](file://AGENTS.md#L578-L596)

**Section sources**
- [AGENTS.md:576-596](file://AGENTS.md#L576-L596)

### Database Design and Maintainability
The database schema adheres to conventions that support phpMyAdmin compatibility and future growth:
- Plural snake_case table names, explicit primary and foreign keys
- Monetary fields use precise decimals; durations use integers
- Status flags, timestamps, and soft deletes where appropriate
- Suggested tables cover users, roles, permissions, employees, payroll batches, items, attendance/work logs, rules, expense claims, company finances, subscription costs, payslips, module toggles, and audit logs

```mermaid
erDiagram
USERS {
bigint id PK
string name
string email UK
string password
timestamp created_at
timestamp updated_at
}
EMPLOYEES {
bigint id PK
string employee_code UK
string first_name
string last_name
bigint user_id FK
bigint department_id FK
bigint position_id FK
enum status
timestamp created_at
timestamp updated_at
}
PAYROLL_BATCHES {
bigint id PK
date period_date
enum status
timestamp created_at
timestamp updated_at
}
PAYROLL_ITEMS {
bigint id PK
bigint payroll_batch_id FK
bigint employee_id FK
enum item_type
decimal amount
enum source_flag
timestamp created_at
timestamp updated_at
}
ATTENDANCE_LOGS {
bigint id PK
bigint employee_id FK
date log_date
int check_in_minutes
int check_out_minutes
int late_minutes
int early_leave_minutes
boolean ot_enabled
boolean lwop_flag
timestamp created_at
timestamp updated_at
}
WORK_LOGS {
bigint id PK
bigint employee_id FK
date work_date
enum work_type
decimal qty
decimal rate
decimal amount
timestamp created_at
timestamp updated_at
}
RATE_RULES {
bigint id PK
enum mode
decimal min_amount
decimal max_amount
decimal rate_percent
date effective_from
enum status
timestamp created_at
timestamp updated_at
}
BONUS_RULES {
bigint id PK
enum mode
decimal threshold
decimal bonus_amount
date effective_from
enum status
timestamp created_at
timestamp updated_at
}
THRESHOLD_RULES {
bigint id PK
enum mode
decimal min_value
decimal max_value
decimal multiplier
date effective_from
enum status
timestamp created_at
timestamp updated_at
}
SOCIAL_SECURITY_CONFIGS {
bigint id PK
decimal employee_rate
decimal employer_rate
decimal salary_ceiling
decimal max_contribution
date effective_from
enum status
timestamp created_at
timestamp updated_at
}
PAYSLEEPS {
bigint id PK
bigint employee_id FK
bigint payroll_batch_id FK
date payment_date
decimal total_income
decimal total_deduction
decimal net_pay
enum status
timestamp created_at
timestamp updated_at
}
PAYSHEET_ITEMS {
bigint id PK
bigint payslip_id FK
enum item_type
decimal amount
enum source_flag
timestamp created_at
timestamp updated_at
}
AUDIT_LOGS {
bigint id PK
bigint actor_id FK
enum entity_type
bigint entity_id
string field_name
text old_value
text new_value
string action
timestamp timestamp
text reason
}
USERS ||--o{ EMPLOYEES : "manages"
EMPLOYEES ||--o{ PAYROLL_ITEMS : "generates"
EMPLOYEES ||--o{ ATTENDANCE_LOGS : "records"
EMPLOYEES ||--o{ WORK_LOGS : "logs"
PAYROLL_BATCHES ||--o{ PAYROLL_ITEMS : "contains"
PAYSLEEPS ||--o{ PAYSHEET_ITEMS : "contains"
```

**Diagram sources**
- [AGENTS.md:387-417](file://AGENTS.md#L387-L417)

**Section sources**
- [AGENTS.md:385-436](file://AGENTS.md#L385-L436)

## Dependency Analysis
The system’s agent roles define clear dependencies and responsibilities, ensuring separation of concerns and maintainability.

```mermaid
graph TB
Arch["Architecture Agent"] --> DB["Database Agent"]
Arch --> Rules["Payroll Rules Agent"]
Arch --> UI["UI/UX Agent"]
Arch --> PDF["PDF/Payslip Agent"]
Arch --> Audit["Audit & Compliance Agent"]
Arch --> Refactor["Refactor Agent"]
DB --> Rules
DB --> UI
DB --> PDF
DB --> Audit
Rules --> UI
Rules --> PDF
Rules --> Audit
UI --> PDF
UI --> Audit
PDF --> Audit
Refactor --> Arch
Refactor --> DB
Refactor --> Rules
Refactor --> UI
Refactor --> PDF
Refactor --> Audit
```

**Diagram sources**
- [AGENTS.md:158-283](file://AGENTS.md#L158-L283)

**Section sources**
- [AGENTS.md:153-284](file://AGENTS.md#L153-L284)

## Performance Considerations
- Use indexed foreign keys and appropriate data types to optimize query performance
- Batch payroll calculations and limit recalculations to visible changes
- Employ snapshots for payslips to avoid recomputation overhead
- Cache frequently accessed rule configurations and module toggles
- Minimize N+1 queries in reporting and summary views

## Troubleshooting Guide
Common issues and resolutions:
- Incorrect calculations: Verify rule configurations and effective dates; confirm source flags and manual overrides
- Audit gaps: Ensure all high-risk changes trigger audit logging; review permission scopes
- PDF discrepancies: Confirm finalized snapshot integrity; re-generate PDFs from approved data only
- UI inconsistencies: Check state flags (locked/auto/manual/override); validate real-time recalculation triggers
- Database schema mismatches: Align with phpMyAdmin compatibility guidelines; verify migrations and indexes

**Section sources**
- [AGENTS.md:663-672](file://AGENTS.md#L663-L672)
- [AGENTS.md:578-596](file://AGENTS.md#L578-L596)
- [AGENTS.md:567-573](file://AGENTS.md#L567-L573)

## Conclusion
The xHR Payroll & Finance System transforms traditional Excel-based payroll into a robust, rule-driven, and auditable platform. By combining spreadsheet-like usability with enterprise-grade architecture, it empowers HR and finance teams to manage diverse payroll modes efficiently, maintain compliance, and scale operations confidently. The modular design and comprehensive agent responsibilities ensure long-term maintainability and adaptability to evolving business needs.

## Appendices
- Minimum deliverables and definition of done provide clear milestones for iterative development and validation.

**Section sources**
- [AGENTS.md:675-710](file://AGENTS.md#L675-L710)