# Overall System Design

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
This document presents the overall system design for the xHR Payroll & Finance System. It explains the service-oriented architecture pattern, database-first approach, and rule-driven development methodology. It also documents how the system replaces Excel-based processes with structured database operations, details the separation of concerns among agent roles, and outlines system boundaries, component interactions, and data flow patterns. Architectural diagrams illustrate how payroll modes integrate with the calculation engine and how different modules communicate. Finally, it addresses scalability considerations, performance optimization strategies, and integration points with external systems such as Thai Social Security.

## Project Structure
The repository is a documentation-first workspace focused on defining the system’s architecture, responsibilities, and operational rules. The primary artifact is a comprehensive guide that specifies:
- Core design principles and technology constraints
- Domain model and payroll modes
- Agent responsibilities and module requirements
- Database guidelines and suggested tables
- Business rules and dynamic UI behavior
- Audit and compliance requirements
- Coding standards and folder structure guidance

This documentation establishes the foundation for building a robust, maintainable payroll and finance system that replaces ad-hoc spreadsheets with a structured, rule-driven, and auditable solution.

```mermaid
graph TB
subgraph "Documentation Layer"
Docs["AGENTS.md<br/>System Design & Rules"]
end
subgraph "Development Contracts"
Agents["Agent Roles & Responsibilities"]
Modules["Required Modules"]
DBGuidelines["Database Guidelines"]
BusinessRules["Business Rules"]
UIUX["UI/UX Behavior"]
Audit["Audit & Compliance"]
end
Docs --> Agents
Docs --> Modules
Docs --> DBGuidelines
Docs --> BusinessRules
Docs --> UIUX
Docs --> Audit
```

**Diagram sources**
- [AGENTS.md:1-721](file://AGENTS.md#L1-L721)

**Section sources**
- [AGENTS.md:9-118](file://AGENTS.md#L9-L118)
- [AGENTS.md:121-151](file://AGENTS.md#L121-L151)
- [AGENTS.md:153-284](file://AGENTS.md#L153-L284)
- [AGENTS.md:286-383](file://AGENTS.md#L286-L383)
- [AGENTS.md:385-436](file://AGENTS.md#L385-L436)
- [AGENTS.md:438-547](file://AGENTS.md#L438-L547)
- [AGENTS.md:549-596](file://AGENTS.md#L549-L596)
- [AGENTS.md:598-648](file://AGENTS.md#L598-L648)

## Core Components
The system is designed around a set of cohesive components aligned with the agent roles and module requirements. These components form the backbone of the payroll and finance workflow:

- Authentication and Authorization: Provides secure access with role-based permissions.
- Employee Management: Centralizes employee profiles, salary profiles, and payroll mode assignments.
- Employee Board and Workspace: Offers a unified interface for payroll entry, real-time recalculation, and payslip preview.
- Attendance and Work Log: Captures attendance and work logs for applicable payroll modes.
- Payroll Engine: Calculates earnings, deductions, and net pay according to configured rules and payroll modes.
- Rule Manager: Manages configurable rules for OT, allowances, bonuses, thresholds, layer rates, SSO, taxes, and module toggles.
- Payslip Builder and PDF Generator: Renders and exports payslips from validated, finalized snapshots.
- Annual Summary and Company Finance: Produces year-over-year summaries and financial statements.
- Audit and Compliance: Tracks changes and enforces compliance with audit requirements.

These components interact through clearly defined boundaries and responsibilities, ensuring separation of concerns and maintainability.

**Section sources**
- [AGENTS.md:288-383](file://AGENTS.md#L288-L383)
- [AGENTS.md:438-547](file://AGENTS.md#L438-L547)
- [AGENTS.md:549-596](file://AGENTS.md#L549-L596)
- [AGENTS.md:598-648](file://AGENTS.md#L598-L648)

## Architecture Overview
The system follows a service-oriented architecture with a database-first approach and rule-driven development. The architecture emphasizes:
- Service-centric business logic encapsulated in dedicated services
- A relational database as the single source of truth
- Configurable rules stored in dedicated tables
- Modular UI components integrated with backend services
- Audit trails and compliance controls

```mermaid
graph TB
subgraph "Presentation Layer"
UI["Employee Board<br/>Employee Workspace<br/>Payslip Preview<br/>Annual Summary<br/>Company Finance"]
end
subgraph "Application Layer"
AuthSvc["Authentication Service"]
EmpSvc["Employee Service"]
PayrollSvc["Payroll Calculation Service"]
RuleSvc["Rule Manager Service"]
PayslipSvc["Payslip Service"]
FinanceSvc["Company Finance Service"]
AuditSvc["Audit Log Service"]
end
subgraph "Data Layer"
DB["MySQL Database<br/>Tables: employees, payroll_items, payslips, audit_logs, etc."]
end
UI --> AuthSvc
UI --> EmpSvc
UI --> PayrollSvc
UI --> RuleSvc
UI --> PayslipSvc
UI --> FinanceSvc
UI --> AuditSvc
AuthSvc --> DB
EmpSvc --> DB
PayrollSvc --> DB
RuleSvc --> DB
PayslipSvc --> DB
FinanceSvc --> DB
AuditSvc --> DB
```

**Diagram sources**
- [AGENTS.md:288-383](file://AGENTS.md#L288-L383)
- [AGENTS.md:385-436](file://AGENTS.md#L385-L436)
- [AGENTS.md:598-648](file://AGENTS.md#L598-L648)

## Detailed Component Analysis

### Payroll Modes and Calculation Engine
The calculation engine integrates with multiple payroll modes, each governed by specific business rules and configurations. The engine aggregates income and deductions, supports manual overrides, and produces a final snapshot for payslip generation.

```mermaid
sequenceDiagram
participant User as "User"
participant Workspace as "Employee Workspace"
participant Engine as "Payroll Calculation Service"
participant Rules as "Rule Manager Service"
participant DB as "Database"
User->>Workspace : "Edit payroll grid"
Workspace->>Engine : "Trigger recalculation"
Engine->>Rules : "Fetch applicable rules"
Rules-->>Engine : "OT, allowances, thresholds, SSO"
Engine->>DB : "Read employee, attendance, work logs"
DB-->>Engine : "Raw data"
Engine->>Engine : "Aggregate income/deductions"
Engine-->>Workspace : "Updated totals"
Workspace-->>User : "Preview payslip"
User->>Workspace : "Finalize payslip"
Workspace->>Engine : "Finalize request"
Engine->>DB : "Write payslip snapshot"
DB-->>Engine : "Success"
Engine-->>Workspace : "Finalized"
```

**Diagram sources**
- [AGENTS.md:338-353](file://AGENTS.md#L338-L353)
- [AGENTS.md:438-506](file://AGENTS.md#L438-L506)
- [AGENTS.md:549-574](file://AGENTS.md#L549-L574)

**Section sources**
- [AGENTS.md:123-131](file://AGENTS.md#L123-L131)
- [AGENTS.md:338-353](file://AGENTS.md#L338-L353)
- [AGENTS.md:438-506](file://AGENTS.md#L438-L506)

### Rule-Driven Development Methodology
Rules are stored in dedicated configuration tables and applied dynamically during calculations. This approach ensures maintainability, auditability, and flexibility to adapt to changing regulations or business needs.

```mermaid
flowchart TD
Start(["Rule Application"]) --> LoadRules["Load Rules from Config Tables"]
LoadRules --> ModeCheck{"Payroll Mode?"}
ModeCheck --> |Monthly Staff| ApplyMonthly["Apply Monthly Staff Rules"]
ModeCheck --> |Freelance Layer| ApplyLayer["Apply Layer Rate Rules"]
ModeCheck --> |Freelance Fixed| ApplyFixed["Apply Fixed Rate Rules"]
ModeCheck --> |Youtuber Salary| ApplyYTSalary["Apply Youtuber Salary Rules"]
ModeCheck --> |Youtuber Settlement| ApplyYTSettlement["Apply Youtuber Settlement Rules"]
ModeCheck --> |Custom Hybrid| ApplyHybrid["Apply Hybrid Overrides"]
ApplyMonthly --> CalcIncome["Calculate Income/Deductions"]
ApplyLayer --> CalcIncome
ApplyFixed --> CalcIncome
ApplyYTSalary --> CalcIncome
ApplyYTSettlement --> CalcIncome
ApplyHybrid --> CalcIncome
CalcIncome --> Validate["Validate Against Thresholds"]
Validate --> Output["Produce Payroll Result Snapshot"]
```

**Diagram sources**
- [AGENTS.md:61-74](file://AGENTS.md#L61-L74)
- [AGENTS.md:196-221](file://AGENTS.md#L196-L221)
- [AGENTS.md:438-506](file://AGENTS.md#L438-L506)

**Section sources**
- [AGENTS.md:61-74](file://AGENTS.md#L61-L74)
- [AGENTS.md:196-221](file://AGENTS.md#L196-L221)
- [AGENTS.md:438-506](file://AGENTS.md#L438-L506)

### Database-First Approach and Schema Design
The database is the single source of truth, with tables designed for readability, auditability, and phpMyAdmin compatibility. The schema supports:
- Core entities: employees, payroll batches, payroll items, payslips, audit logs
- Supporting entities: attendance logs, work logs, rate rules, bonus rules, social security configs
- Financial summaries: company revenues, expenses, subscription costs

```mermaid
erDiagram
EMPLOYEES {
bigint id PK
string name
string email
enum status
timestamp created_at
timestamp updated_at
}
PAYROLL_BATCHES {
bigint id PK
int year
int month
enum status
timestamp created_at
timestamp updated_at
}
PAYROLL_ITEMS {
bigint id PK
bigint employee_id FK
bigint payroll_batch_id FK
enum item_type
decimal amount
enum source_flag
timestamp created_at
timestamp updated_at
}
ATTENDANCE_LOGS {
bigint id PK
bigint employee_id FK
int late_minutes
int early_leave_minutes
boolean ot_enabled
boolean lwop_flag
date log_date
timestamp created_at
timestamp updated_at
}
WORK_LOGS {
bigint id PK
bigint employee_id FK
enum work_type
decimal qty
decimal rate
decimal amount
date log_date
timestamp created_at
timestamp updated_at
}
SOCIAL_SECURITY_CONFIGS {
bigint id PK
date effective_date
decimal employee_rate
decimal employer_rate
decimal salary_ceiling
decimal max_monthly_contribution
timestamp created_at
timestamp updated_at
}
PAYSLETS {
bigint id PK
bigint employee_id FK
bigint payroll_batch_id FK
decimal total_income
decimal total_deduction
decimal net_pay
enum status
timestamp created_at
timestamp updated_at
}
PAYSLET_ITEMS {
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
enum action
string entity
string field
string old_value
string new_value
string reason
timestamp timestamp
}
EMPLOYEES ||--o{ PAYROLL_ITEMS : "has"
PAYROLL_BATCHES ||--o{ PAYROLL_ITEMS : "contains"
EMPLOYEES ||--o{ ATTENDANCE_LOGS : "logs"
EMPLOYEES ||--o{ WORK_LOGS : "records"
EMPLOYEES ||--o{ PAYSLETS : "receives"
PAYROLL_BATCHES ||--o{ PAYSLETS : "generates"
PAYSLETS ||--o{ PAYSLET_ITEMS : "includes"
```

**Diagram sources**
- [AGENTS.md:387-417](file://AGENTS.md#L387-L417)

**Section sources**
- [AGENTS.md:385-436](file://AGENTS.md#L385-L436)
- [AGENTS.md:387-417](file://AGENTS.md#L387-L417)

### Agent Roles and Separation of Concerns
Each agent role defines a distinct responsibility area, ensuring clear boundaries and reducing coupling:
- Architecture Agent: Defines system boundaries and prevents spreadsheet-style cell thinking.
- Database Agent: Designs schema, indexes, foreign keys, and ensures phpMyAdmin compatibility.
- Payroll Rules Agent: Creates configurable rules per payroll mode and validates interdependencies.
- UI/UX Agent: Ensures a spreadsheet-like experience with inline editing, instant recalculation, and clear state indicators.
- PDF/Payslip Agent: Renders payslips from validated snapshots and maintains format consistency.
- Audit & Compliance Agent: Enforces audit logging and rollback capability for critical changes.
- Refactor Agent: Maintains simplicity, reduces duplication, and promotes reusable services.

```mermaid
classDiagram
class ArchitectureAgent {
+define_system_boundaries()
+prevent_cell_thinking()
+decide_source_types()
}
class DatabaseAgent {
+design_schema()
+set_indexes()
+define_foreign_keys()
+ensure_phpmyadmin_compatibility()
}
class PayrollRulesAgent {
+define_mode_rules()
+create_configurable_rules()
+validate_interdependencies()
}
class UIUXAgent {
+spreadsheet_like_experience()
+inline_editing()
+instant_recalculation()
+preview_payslip()
}
class PDFPayslipAgent {
+render_payslip_from_snapshot()
+export_pdf()
+maintain_format_consistency()
}
class AuditComplianceAgent {
+log_changes()
+enforce_audit_trail()
+rollback_capability()
}
class RefactorAgent {
+reduce_duplication()
+separate_reusable_services()
+detect_code_smells()
}
ArchitectureAgent --> DatabaseAgent : "guides"
ArchitectureAgent --> PayrollRulesAgent : "guides"
ArchitectureAgent --> UIUXAgent : "guides"
ArchitectureAgent --> PDFPayslipAgent : "guides"
ArchitectureAgent --> AuditComplianceAgent : "guides"
ArchitectureAgent --> RefactorAgent : "guides"
```

**Diagram sources**
- [AGENTS.md:158-284](file://AGENTS.md#L158-L284)

**Section sources**
- [AGENTS.md:158-284](file://AGENTS.md#L158-L284)

### Module Interactions
Modules interact through well-defined interfaces and shared services. The Employee Workspace orchestrates interactions between Attendance, Work Log, Payroll Engine, Rule Manager, and Payslip services, while maintaining audit and compliance controls.

```mermaid
sequenceDiagram
participant Board as "Employee Board"
participant Workspace as "Employee Workspace"
participant Attendance as "Attendance Module"
participant WorkLog as "Work Log Module"
participant Engine as "Payroll Engine"
participant RuleMgr as "Rule Manager"
participant Payslip as "Payslip Module"
participant Audit as "Audit & Compliance"
Board->>Workspace : "Open Employee Workspace"
Workspace->>Attendance : "Fetch attendance logs"
Attendance-->>Workspace : "Late/LWOP/Overtime flags"
Workspace->>WorkLog : "Fetch work logs"
WorkLog-->>Workspace : "Freelance amounts"
Workspace->>Engine : "Run calculation"
Engine->>RuleMgr : "Apply rules"
RuleMgr-->>Engine : "Configured rules"
Engine-->>Workspace : "Aggregated totals"
Workspace->>Payslip : "Generate preview"
Payslip-->>Workspace : "Payslip preview"
Workspace->>Audit : "Log changes"
Audit-->>Workspace : "Audit trail"
Workspace-->>Board : "Ready for finalize"
```

**Diagram sources**
- [AGENTS.md:303-383](file://AGENTS.md#L303-L383)
- [AGENTS.md:338-353](file://AGENTS.md#L338-L353)
- [AGENTS.md:576-596](file://AGENTS.md#L576-L596)

**Section sources**
- [AGENTS.md:303-383](file://AGENTS.md#L303-L383)
- [AGENTS.md:338-353](file://AGENTS.md#L338-L353)
- [AGENTS.md:576-596](file://AGENTS.md#L576-L596)

## Dependency Analysis
The system exhibits low coupling and high cohesion across modules. Dependencies flow from presentation to services and from services to the database. The rule-driven design centralizes business logic in services and configuration tables, minimizing direct dependencies between UI and business rules.

```mermaid
graph TB
UI["UI/UX Layer"] --> Services["Service Layer"]
Services --> DB["Database"]
Services --> Rules["Rule Config Tables"]
Services --> Audit["Audit Logs"]
```

**Diagram sources**
- [AGENTS.md:598-648](file://AGENTS.md#L598-L648)
- [AGENTS.md:385-436](file://AGENTS.md#L385-L436)

**Section sources**
- [AGENTS.md:598-648](file://AGENTS.md#L598-L648)
- [AGENTS.md:385-436](file://AGENTS.md#L385-L436)

## Performance Considerations
- Indexing and Foreign Keys: Ensure appropriate indexes on frequently queried columns (employee_id, payroll_batch_id, log_date) and maintain referential integrity via foreign keys.
- Decimal Precision: Use precise numeric types for monetary fields to avoid rounding errors.
- Batch Processing: Aggregate and process payroll items in batches to reduce round-trips to the database.
- Caching: Cache frequently accessed rule configurations and static lists (e.g., departments, positions) to minimize repeated reads.
- Pagination and Filtering: Implement pagination and efficient filtering in the Employee Board and Workspace to handle large datasets.
- Query Optimization: Prefer joins and aggregations over multiple round-trips; use stored procedures or materialized views for complex financial summaries when feasible.
- Asynchronous Jobs: Offload heavy tasks (e.g., PDF generation, bulk recalculations) to queued jobs to keep the UI responsive.

[No sources needed since this section provides general guidance]

## Troubleshooting Guide
Common issues and resolutions:
- Incorrect Totals: Verify that the payroll mode is correctly assigned and that the Rule Manager is applying the intended rules. Confirm that manual overrides are properly tagged and audited.
- Payslip Discrepancies: Ensure that payslips are finalized and rendered from the snapshot tables. Check audit logs for changes made after finalization.
- Attendance/Work Log Errors: Validate that attendance and work logs are correctly linked to employees and payroll batches. Confirm that OT flags and LWOP flags are set appropriately.
- Audit Trail Gaps: Review audit logs for missing entries and ensure that all critical changes are captured with reasons and timestamps.
- Database Migration Issues: Confirm that migrations are compatible with phpMyAdmin and shared hosting environments. Validate foreign key constraints and data types.

**Section sources**
- [AGENTS.md:576-596](file://AGENTS.md#L576-L596)
- [AGENTS.md:549-574](file://AGENTS.md#L549-L574)
- [AGENTS.md:385-436](file://AGENTS.md#L385-L436)

## Conclusion
The xHR Payroll & Finance System is designed to replace Excel-based processes with a structured, database-first, and rule-driven solution. By adhering to service-oriented architecture, clear separation of concerns among agent roles, and maintainable coding standards, the system achieves reliability, auditability, and scalability. The documented modules, data flows, and integration points provide a blueprint for building a robust payroll and finance platform tailored to diverse payroll modes and compliance requirements, including integration with external systems such as Thai Social Security.

[No sources needed since this section summarizes without analyzing specific files]

## Appendices
- Minimum Deliverables: Project structure, database schema, migrations, seed data, model relationships, payroll services, rule manager, Employee workspace UI, payslip builder + PDF, audit logs, annual summary, company finance summary.
- Definition of Done: Successful onboarding of new employees, assignment of payroll modes, single-page salary entry, correct calculations across all payroll modes, configurable SSO, PDF payslips, annual summaries, company P&L, audit logs, and future extensibility.

**Section sources**
- [AGENTS.md:675-710](file://AGENTS.md#L675-L710)