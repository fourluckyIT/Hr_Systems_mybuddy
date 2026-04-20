# Attendance and Work Tracking

<cite>
**Referenced Files in This Document**
- [AGENTS.md](file://AGENTS.md)
</cite>

## Table of Contents
1. [Introduction](#introduction)
2. [System Architecture](#system-architecture)
3. [Attendance Module](#attendance-module)
4. [Work Log Module](#work-log-module)
5. [Payroll Integration](#payroll-integration)
6. [Configuration Management](#configuration-management)
7. [Data Models](#data-models)
8. [Business Rules](#business-rules)
9. [UI/UX Considerations](#uiux-considerations)
10. [Audit and Compliance](#audit-and-compliance)
11. [Implementation Guidelines](#implementation-guidelines)
12. [Conclusion](#conclusion)

## Introduction

The xHR Payroll & Finance System is a comprehensive HR management solution designed to replace traditional Excel-based payroll systems with a modern, rule-driven, and auditable platform. This system specifically addresses attendance and work tracking requirements for both monthly staff and freelancers, providing automated calculation of overtime, late deductions, and performance bonuses while maintaining full compliance with Thai labor regulations.

The system follows six core design principles: PHP-first development, MySQL/phpMyAdmin compatibility, dynamic data entry, rule-driven architecture, audit-ability, and easy maintainability. These principles guide the implementation of attendance and work tracking functionality to ensure scalability and future-proofing.

## System Architecture

The system employs a modular architecture with clear separation of concerns across multiple specialized agents:

```mermaid
graph TB
subgraph "Core Modules"
AM[Attendance Module]
WM[Work Log Module]
PR[Payroll Rules Agent]
PE[Payroll Engine]
RM[Rule Manager]
end
subgraph "Data Layer"
DB[(Database)]
AL[Attendance Logs]
WL[Work Logs]
ER[Employee Records]
end
subgraph "User Interface"
EB[Employee Board]
EW[Employee Workspace]
PS[Payslip Preview]
end
AM --> DB
WM --> DB
PR --> RM
PE --> PR
RM --> DB
DB --> AL
DB --> WL
DB --> ER
EB --> AM
EB --> WM
EW --> AM
EW --> WM
PS --> PE
```

**Diagram sources**
- [AGENTS.md:155-284](file://AGENTS.md#L155-L284)

The architecture ensures that attendance and work tracking data flows seamlessly into the broader payroll ecosystem, with each module maintaining its own domain boundaries while sharing common data structures and audit trails.

## Attendance Module

The Attendance Module provides comprehensive time tracking capabilities for monthly staff employees, supporting check-in/check-out style input with sophisticated time calculation logic.

### Core Functionality

The attendance system manages four primary time-related metrics:

```mermaid
flowchart TD
Start([Attendance Entry]) --> CheckInOut["Check-in/Check-out Input"]
CheckInOut --> CalcDuration["Calculate Daily Duration"]
CalcDuration --> LateCalc["Calculate Late Minutes"]
CalcDuration --> EarlyLeave["Calculate Early Leave"]
LateCalc --> OTCheck{"OT Threshold Met?"}
EarlyLeave --> OTCheck
OTCheck --> |Yes| OTEnabled["Enable Overtime"]
OTCheck --> |No| NoOT["No Overtime"]
OTEnabled --> LWOPCheck{"LWOP Flag?"}
NoOT --> LWOPCheck
LWOPCheck --> |Yes| LWOPApply["Apply LWOP Deduction"]
LWOPCheck --> |No| ProcessComplete["Process Complete"]
LWOPApply --> ProcessComplete
ProcessComplete --> End([Attendance Recorded])
```

**Diagram sources**
- [AGENTS.md:322-328](file://AGENTS.md#L322-L328)

### Attendance Data Structure

The system captures detailed attendance information through the AttendanceLog entity, which includes:

- **Check-in/Check-out timestamps** for precise time tracking
- **Late minutes calculation** based on scheduled work hours minus actual arrival time
- **Early leave recording** measuring departure time minus scheduled end time
- **Overtime flag** indicating when work exceeds standard daily hours
- **LWOP (Without Pay Without Reason) flag** for unauthorized absences

### Time Calculation Logic

The attendance system implements sophisticated time calculation rules:

```mermaid
flowchart LR
subgraph "Time Inputs"
CI[Check-in Time]
CO[Check-out Time]
SCH[Schedule]
end
subgraph "Calculations"
DUR[Duration = CO - CI]
LATE[Late = MAX(0, CI - SCH.start)]
EARLY[Early = MAX(0, SCH.end - CO)]
OT[Overtime = MAX(0, DUR - SCH.hours_per_day)]
end
subgraph "Outputs"
LATE_MIN[Late Minutes]
EARLY_MIN[Early Minutes]
OT_MIN[Overtime Minutes]
LWOP[LWOP Flag]
end
CI --> DUR
CO --> DUR
SCH --> DUR
DUR --> LATE
DUR --> EARLY
DUR --> OT
LATE --> LATE_MIN
EARLY --> EARLY_MIN
OT --> OT_MIN
SCH --> LWOP
```

**Diagram sources**
- [AGENTS.md:454-471](file://AGENTS.md#L454-L471)

## Work Log Module

The Work Log Module serves freelancers and hybrid payroll modes with flexible time and quantity tracking capabilities.

### Work Log Types

The system supports multiple work log categories:

| Work Type | Measurement Unit | Description |
|-----------|------------------|-------------|
| **Layer Work** | Minutes/Seconds | Time-based work with tiered rate calculations |
| **Fixed Work** | Quantity | Pre-determined quantity-based work |
| **Service Work** | Hours | Professional service delivery |
| **Project Work** | Days | Project-based assignments |

### Data Capture Structure

```mermaid
erDiagram
WORK_LOG {
bigint id PK
bigint employee_id FK
date work_date
enum work_type
decimal qty
int minutes
int seconds
decimal layer
decimal rate
decimal amount
enum status
timestamp created_at
timestamp updated_at
}
EMPLOYEE {
bigint id PK
string name
enum payroll_mode
enum status
}
WORK_LOG_TYPE {
bigint id PK
string name
enum category
boolean is_active
}
EMPLOYEE ||--o{ WORK_LOG : "records"
WORK_LOG_TYPE ||--o{ WORK_LOG : "categorizes"
```

**Diagram sources**
- [AGENTS.md:329-337](file://AGENTS.md#L329-L337)

### Calculation Formulas

The work log system implements standardized calculation formulas:

**Freelance Layer Formula:**
- `duration_minutes = minute + (second / 60)`
- `amount = duration_minutes × rate_per_minute`

**Freelance Fixed Formula:**
- `amount = quantity × fixed_rate`

**Layer Rate Calculations:**
The system supports tiered rate structures where rates increase based on accumulated work hours or project milestones, allowing for progressive compensation structures.

## Payroll Integration

The attendance and work tracking systems integrate deeply with the payroll calculation engine, providing automated income and deduction calculations.

### Income Integration

```mermaid
sequenceDiagram
participant UI as User Interface
participant ATT as Attendance Module
participant WLOG as Work Log Module
participant RULE as Payroll Rules
participant CALC as Payroll Engine
participant SLIP as Payslip Generator
UI->>ATT : Submit Attendance Data
UI->>WLOG : Submit Work Log Entries
ATT->>RULE : Validate Attendance Rules
WLOG->>RULE : Validate Work Log Rules
RULE->>CALC : Apply Business Rules
CALC->>CALC : Calculate Overtime Pay
CALC->>CALC : Calculate Late Deductions
CALC->>CALC : Calculate Performance Bonuses
CALC->>SLIP : Generate Pay Components
SLIP->>UI : Display Payslip Preview
```

**Diagram sources**
- [AGENTS.md:338-343](file://AGENTS.md#L338-L343)

### Deduction Integration

The system automatically applies various deductions based on attendance patterns:

- **Late Deduction**: Calculated per minute of lateness with configurable grace periods
- **Early Leave Deduction**: Applied for early departures beyond allowed tolerance
- **LWOP Deduction**: Day-based or proportional salary deductions for unauthorized absences
- **Social Security**: Automatic SSO contributions based on configured rates and salary ceilings

### Pay Component Generation

The payroll engine generates comprehensive pay components:

```mermaid
flowchart TD
subgraph "Input Data"
ATT[Attendance Records]
WLOG[Work Log Entries]
SAL[Salary Profile]
end
subgraph "Processing"
RULE[Rule Application]
CALC[Calculation Engine]
end
subgraph "Output Components"
BASE[Base Salary]
OT[Overtime Pay]
DA[Diligence Allowance]
PB[Performance Bonus]
LD[Late Deduction]
LWOPD[LWOP Deduction]
SSO[Social Security]
OTHER[Other Deductions]
end
ATT --> RULE
WLOG --> RULE
SAL --> RULE
RULE --> CALC
CALC --> BASE
CALC --> OT
CALC --> DA
CALC --> PB
CALC --> LD
CALC --> LWOPD
CALC --> SSO
CALC --> OTHER
```

**Diagram sources**
- [AGENTS.md:440-444](file://AGENTS.md#L440-L444)

## Configuration Management

The system provides extensive configuration options through dedicated rule management interfaces.

### Attendance Configuration Rules

| Configuration Category | Parameters | Purpose |
|----------------------|------------|---------|
| **OT Rules** | Enable flag, threshold minutes, calculation method | Control overtime eligibility and payment |
| **Late Deduction Rules** | Fixed per minute rate, tier penalties, grace period | Manage lateness penalties |
| **LWOP Rules** | Day-based deduction, proportional salary deduction | Handle unauthorized absences |
| **Diligence Allowance** | Fixed amount, eligibility criteria | Reward punctuality |

### Rate Configuration

The system supports multiple rate configuration levels:

```mermaid
graph LR
subgraph "Rate Configuration Hierarchy"
SR[Standard Rates]
LR[Layer Rates]
BR[Bonus Rules]
TR[Threshold Rules]
end
subgraph "Application Levels"
EM[Employee Mode]
PM[Payroll Mode]
CM[Company-wide]
end
SR --> EM
LR --> PM
BR --> CM
TR --> PM
```

**Diagram sources**
- [AGENTS.md:344-353](file://AGENTS.md#L344-L353)

### Rule Management Features

- **Dynamic Rule Updates**: Rules can be modified without affecting historical payroll calculations
- **Effective Date Management**: Changes take effect from specified dates
- **Audit Trail**: All rule modifications are logged with change details
- **Validation**: Interdependent rules are validated before activation

## Data Models

The system maintains comprehensive data structures for attendance and work tracking:

### Attendance Log Schema

| Field | Type | Description | Constraints |
|-------|------|-------------|-------------|
| `id` | `bigint unsigned` | Primary key | Auto-increment |
| `employee_id` | `bigint unsigned` | Foreign key to employees | Required |
| `work_date` | `date` | Date of attendance | Required, unique per employee |
| `check_in_time` | `timestamp` | Actual check-in time | Nullable |
| `check_out_time` | `timestamp` | Actual check-out time | Nullable |
| `scheduled_check_in` | `time` | Scheduled start time | Required |
| `scheduled_check_out` | `time` | Scheduled end time | Required |
| `late_minutes` | `integer` | Minutes late | Default 0 |
| `early_minutes` | `integer` | Minutes early | Default 0 |
| `overtime_minutes` | `integer` | Overtime minutes | Default 0 |
| `lwop_flag` | `boolean` | LWOP indicator | Default false |
| `status` | `enum` | Record status | Default 'active' |
| `created_at` | `timestamp` | Creation timestamp | Auto-fill |
| `updated_at` | `timestamp` | Update timestamp | Auto-fill |

### Work Log Schema

| Field | Type | Description | Constraints |
|-------|------|-------------|-------------|
| `id` | `bigint unsigned` | Primary key | Auto-increment |
| `employee_id` | `bigint unsigned` | Foreign key to employees | Required |
| `work_date` | `date` | Date of work | Required |
| `work_type` | `enum` | Work category | Required |
| `qty` | `decimal(12,2)` | Quantity or amount | Nullable |
| `minutes` | `integer` | Minutes worked | Default 0 |
| `seconds` | `integer` | Seconds worked | Default 0 |
| `layer` | `decimal(12,2)` | Layer rating | Nullable |
| `rate` | `decimal(12,2)` | Rate per unit | Nullable |
| `amount` | `decimal(12,2)` | Total calculated amount | Default 0 |
| `status` | `enum` | Record status | Default 'active' |
| `created_at` | `timestamp` | Creation timestamp | Auto-fill |
| `updated_at` | `timestamp` | Update timestamp | Auto-fill |

## Business Rules

The system enforces comprehensive business rules governing attendance and work tracking calculations.

### Overtime Calculation Rules

```mermaid
flowchart TD
Start([Daily Work Entry]) --> CalcHours["Calculate Daily Hours"]
CalcHours --> Compare{"Hours > Standard?"}
Compare --> |No| NoOT["No Overtime"]
Compare --> |Yes| CalcOT["Calculate Overtime"]
CalcOT --> MinCheck{"OT >= Min Threshold?"}
MinCheck --> |No| NoOT
MinCheck --> |Yes| ApplyOT["Apply OT Rate"]
ApplyOT --> End([Overtime Recorded])
NoOT --> End
```

**Diagram sources**
- [AGENTS.md:454-460](file://AGENTS.md#L454-L460)

### Late Deduction Rules

The late deduction system implements configurable penalty structures:

- **Fixed Per Minute**: Straightforward per-minute penalty calculation
- **Tier Penalty**: Progressive penalties for increasing lateness
- **Grace Period**: Lateness within specified tolerance is ignored

### LWOP Handling

Unauthorized absences trigger automatic deductions:

- **Day-Based Deduction**: Full day salary reduction for unauthorized absence
- **Proportional Deduction**: Pro-rata reduction based on daily salary rate
- **Accumulation Tracking**: LWOP days tracked for annual reporting

### Diligence Allowance

The system provides performance-based allowances:

- **Eligibility Criteria**: Zero late minutes and zero LWOP days
- **Default Amount**: Configurable allowance amount
- **Automatic Application**: Applied when eligibility criteria are met

## UI/UX Considerations

The system provides intuitive interfaces for attendance and work tracking entry:

### Employee Workspace

The Employee Workspace consolidates all payroll-related activities:

```mermaid
graph TB
subgraph "Workspace Layout"
HDR[Header with Month Selector]
SUM[Summary Cards]
GRID[Main Payroll Grid]
INS[Detail Inspector]
PREV[Payslip Preview]
AUDIT[Audit Timeline]
end
subgraph "Grid Features"
ADD[Add Row]
DEL[Remove Row]
EDIT[Inline Editing]
DROP[Dropdown Categories]
AUTO[Auto Calculation]
MANUAL[Manual Override]
RECAL[Recalculation]
BADGE[Source Badges]
end
GRID --> ADD
GRID --> DEL
GRID --> EDIT
GRID --> DROP
GRID --> AUTO
GRID --> MANUAL
GRID --> RECAL
GRID --> BADGE
```

**Diagram sources**
- [AGENTS.md:310-321](file://AGENTS.md#L310-L321)

### Attendance Entry Interface

The interface supports efficient time entry with validation:

- **Check-in/Check-out Input Fields**: Real-time validation against schedule
- **Late/Early Detection**: Automatic calculation with visual indicators
- **Overtime Flag**: Clear indication when overtime applies
- **LWOP Option**: Simple flagging for unauthorized absences

### Work Log Entry Interface

Flexible work log entry supports multiple work types:

- **Date Selection**: Calendar-based date picker
- **Type Dropdown**: Work type selection with category filtering
- **Measurement Inputs**: Flexible quantity, time, or layer inputs
- **Rate Application**: Automatic rate application based on configurations
- **Amount Calculation**: Real-time amount calculation

## Audit and Compliance

The system maintains comprehensive audit trails for all attendance and work tracking activities.

### Audit Logging Requirements

```mermaid
flowchart TD
Action[User Action] --> Log[Create Audit Record]
Log --> Who[Record User Identity]
Log --> What[Record Entity Changed]
Log --> Field[Record Field Modified]
Log --> OldVal[Record Old Value]
Log --> NewVal[Record New Value]
Log --> When[Record Timestamp]
Log --> Reason[Record Reason/Notes]
Log --> Store[Store in Audit Log Table]
Store --> Review[Review in Audit Timeline]
Review --> Compliance[Compliance Reporting]
```

**Diagram sources**
- [AGENTS.md:576-595](file://AGENTS.md#L576-L595)

### High-Priority Audit Areas

The system focuses audit coverage on critical areas:

- **Employee Salary Profile Changes**: All modifications to base salary and rates
- **Payroll Item Amount Changes**: Significant adjustments to calculated amounts
- **Payslip Finalization**: Complete audit trail of finalization process
- **Rule Configuration Changes**: All modifications to business rules
- **Module Toggle Changes**: Activation/deactivation of system features
- **SSO Configuration Changes**: Social security benefit modifications

### Compliance Features

- **Change Management**: Five-question change approval process
- **Permission Control**: Role-based access to sensitive operations
- **Validation**: Built-in validation to prevent invalid data entry
- **Reporting**: Comprehensive audit reports for compliance purposes

## Implementation Guidelines

### Development Approach

The system follows established development guidelines:

```mermaid
graph LR
subgraph "Development Principles"
PRINC1[Record-based, not cell-based]
PRINC2[Single Source of Truth]
PRINC3[Rule-driven, not hardcoded]
PRINC4[Dynamic but controlled editing]
PRINC5[Maintainability first]
end
subgraph "Technical Requirements"
TECH1[PHP 8.2+]
TECH2[Laravel Framework]
TECH3[MySQL 8+]
TECH4[phpMyAdmin Compatible]
end
PRINC1 --> TECH1
PRINC2 --> TECH2
PRINC3 --> TECH3
PRINC4 --> TECH4
```

**Diagram sources**
- [AGENTS.md:23-31](file://AGENTS.md#L23-L31)

### Service Architecture

Recommended service classes for implementation:

- **AttendanceService**: Core attendance calculation and validation
- **WorkLogService**: Work log entry, validation, and calculation
- **PayrollCalculationService**: Integration with payroll engine
- **RuleManagerService**: Configuration and validation of business rules
- **AuditLogService**: Comprehensive audit trail management

### Testing Strategy

Minimum testing requirements include:

- **Payroll Mode Calculation Tests**: Verification of all payroll mode calculations
- **SSO Calculation Tests**: Social security contribution accuracy
- **Layer Rate Tests**: Tiered rate calculation correctness
- **Payslip Snapshot Tests**: Finalization and PDF generation accuracy
- **Audit Logging Tests**: Complete audit trail verification

## Conclusion

The xHR Payroll & Finance System provides a comprehensive solution for attendance and work tracking that balances user-friendly interfaces with robust business logic and strict compliance requirements. The system's modular architecture, rule-driven design, and extensive configuration options ensure it can adapt to various organizational needs while maintaining data integrity and auditability.

Key strengths of the system include:

- **Automated Calculations**: Reduces manual errors through standardized business rules
- **Flexible Configuration**: Adaptable to changing organizational policies and regulations
- **Comprehensive Audit**: Complete traceability of all changes and calculations
- **Scalable Architecture**: Modular design supports future enhancements
- **User Experience**: Intuitive interfaces that feel familiar yet maintain proper controls

The attendance and work tracking modules serve as foundational components that integrate seamlessly with the broader payroll ecosystem, providing the data foundation necessary for accurate compensation calculations and regulatory compliance reporting.