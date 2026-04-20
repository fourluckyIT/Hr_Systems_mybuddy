# System Architecture

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

## Introduction

The xHR Payroll & Finance System is a comprehensive payroll and financial management solution designed to replace traditional Excel-based systems with a modern, database-driven approach. Built on PHP 8.2+ with Laravel framework and MySQL 8+, this system provides automated payroll calculation, employee management, financial reporting, and compliance tracking capabilities.

The system follows six core design principles: PHP-first development, MySQL/phpMyAdmin-friendly database design, dynamic data entry, rule-driven configuration, auditability, and maintainability. It supports multiple payroll modes including monthly staff, freelance workers, and content creators while maintaining strict separation of concerns through service-oriented architecture.

## Project Structure

The system is organized around a service-oriented architecture with clear separation between business logic, data persistence, and presentation layers. The recommended folder structure follows Laravel conventions while emphasizing maintainability and extensibility.

```mermaid
graph TB
subgraph "Application Layer"
Controllers["HTTP Controllers<br/>User Interface"]
Requests["Form Requests<br/>Validation"]
Policies["Authorization Policies"]
end
subgraph "Business Logic Layer"
Services["Service Classes<br/>Business Rules"]
Actions["Action Classes<br/>Workflow Orchestration"]
Enums["Enum Classes<br/>Constants"]
end
subgraph "Data Access Layer"
Models["Eloquent Models<br/>Database Entities"]
Repositories["Repository Pattern<br/>Data Access"]
Migrations["Database Migrations<br/>Schema Evolution"]
end
subgraph "Infrastructure Layer"
Database[(MySQL Database)]
PDFEngine["PDF Generation<br/>DomPDF/Snappy"]
Storage["File Storage<br/>Payslip Documents"]
end
Controllers --> Services
Services --> Models
Actions --> Services
Requests --> Services
Policies --> Controllers
Models --> Database
Services --> Database
Services --> PDFEngine
PDFEngine --> Storage
```

**Diagram sources**
- [AGENTS.md:622-647](file://AGENTS.md#L622-L647)

The system emphasizes maintainability through clear component separation, with each service handling specific business domains while maintaining loose coupling and high cohesion.

**Section sources**
- [AGENTS.md:622-647](file://AGENTS.md#L622-L647)

## Core Components

### Payroll Calculation Engine

The heart of the system is the payroll calculation engine responsible for processing different payroll modes according to configurable business rules. The engine supports six distinct payroll modes with specialized calculation logic while maintaining common interfaces and data structures.

```mermaid
classDiagram
class PayrollCalculationService {
+calculateMonthlyStaff(employeeId, month)
+calculateFreelanceLayer(employeeId, month)
+calculateFreelanceFixed(employeeId, month)
+calculateYoutuberSalary(employeeId, month)
+calculateYoutuberSettlement(employeeId, month)
+calculateHybrid(employeeId, month)
+validateInputs(payrollData)
+applyRules(payrollItems)
+generateSnapshot()
}
class AttendanceService {
+processAttendance(employeeId, month)
+calculateOT(attendanceLogs)
+calculateLateDeductions(attendanceLogs)
+calculateLWOP(attendanceLogs)
}
class WorkLogService {
+processWorkLogs(employeeId, month)
+calculateLayerRate(workLogs)
+calculateFixedRate(workLogs)
+validateWorkLog(workLog)
}
class SocialSecurityService {
+calculateEmployeeContribution(employeeId, month)
+calculateEmployerContribution(employeeId, month)
+applySSOConfig(configDate)
+validateCeiling(salaryAmount)
}
class PayslipService {
+generatePayslip(employeeId, month)
+previewPayslip(payslipData)
+finalizePayslip(payslipData)
+exportPDF(payslipId)
+regenerateFromSnapshot(payslipId)
}
PayrollCalculationService --> AttendanceService : "uses"
PayrollCalculationService --> WorkLogService : "uses"
PayrollCalculationService --> SocialSecurityService : "uses"
PayrollCalculationService --> PayslipService : "generates"
```

**Diagram sources**
- [AGENTS.md:636-646](file://AGENTS.md#L636-L646)

### Employee Management System

The employee management module handles comprehensive personnel data including profiles, salary configurations, bank accounts, and employment status tracking. It maintains strict data integrity while providing flexible configuration options.

```mermaid
classDiagram
class Employee {
+employeeId : bigint
+personalInfo : EmployeeProfile
+salaryProfile : EmployeeSalaryProfile
+bankAccounts : EmployeeBankAccount[]
+employmentStatus : string
+payrollMode : string
+department : Department
+position : Position
}
class EmployeeProfile {
+firstName : string
+lastName : string
+email : string
+phone : string
+birthDate : date
+address : string
+ssn : string
}
class EmployeeSalaryProfile {
+baseSalary : decimal
+effectiveDate : date
+salaryType : string
+currency : string
+paymentFrequency : string
}
class EmployeeBankAccount {
+bankName : string
+accountNumber : string
+accountHolder : string
+branchCode : string
+isPrimary : boolean
}
Employee --> EmployeeProfile : "has one"
Employee --> EmployeeSalaryProfile : "has one"
Employee --> EmployeeBankAccount : "has many"
```

**Diagram sources**
- [AGENTS.md:132-149](file://AGENTS.md#L132-L149)

### Financial Reporting Module

The financial reporting system generates comprehensive reports including payslips, annual summaries, and company financial statements. It provides multiple output formats while maintaining audit trails and compliance requirements.

```mermaid
classDiagram
class CompanyFinanceService {
+generateMonthlyReport(month)
+generateAnnualSummary(employeeId)
+calculateProfitLoss(period)
+generateTaxReport()
+exportFinancialStatements(format)
}
class Payslip {
+payslipId : bigint
+employeeId : bigint
+month : date
+totalIncome : decimal
+totalDeduction : decimal
+netPay : decimal
+status : string
+items : PayslipItem[]
}
class CompanyMonthlySummary {
+summaryId : bigint
+month : date
+totalEmployees : int
+totalPayrollCost : decimal
+totalCompanyExpenses : decimal
+netProfit : decimal
+taxLiability : decimal
}
CompanyFinanceService --> Payslip : "generates"
CompanyFinanceService --> CompanyMonthlySummary : "aggregates"
```

**Diagram sources**
- [AGENTS.md:367-382](file://AGENTS.md#L367-L382)

**Section sources**
- [AGENTS.md:338-382](file://AGENTS.md#L338-L382)

## Architecture Overview

The xHR Payroll & Finance System follows a service-oriented architecture with clear separation of concerns and well-defined interfaces between components. The architecture emphasizes scalability, maintainability, and compliance through rule-driven configuration and comprehensive audit logging.

```mermaid
graph TB
subgraph "External Systems"
ThaiSSO["Thai Social Security<br/>External Integration"]
BankSystem["Bank Payment System<br/>Direct Debit"]
TaxAuthority["Tax Authority<br/>Report Submission"]
end
subgraph "Presentation Layer"
WebUI["Web Interface<br/>Blade Templates"]
API["REST API<br/>JSON Endpoints"]
Mobile["Mobile Interface<br/>Responsive Design"]
end
subgraph "Business Logic Layer"
PayrollEngine["Payroll Calculation Engine"]
RuleManager["Rule Management System"]
AuditSystem["Audit & Compliance"]
end
subgraph "Data Layer"
EmployeeDB["Employee Database"]
PayrollDB["Payroll Processing DB"]
ReportDB["Reporting Database"]
end
subgraph "Integration Layer"
PDFGenerator["PDF Generation<br/>DomPDF/Snappy"]
EmailService["Email Notifications"]
FileStorage["Document Storage"]
end
ThaiSSO --> PayrollEngine
BankSystem --> PayrollEngine
TaxAuthority --> AuditSystem
WebUI --> PayrollEngine
API --> PayrollEngine
Mobile --> PayrollEngine
PayrollEngine --> EmployeeDB
PayrollEngine --> PayrollDB
PayrollEngine --> ReportDB
PayrollEngine --> PDFGenerator
PayrollEngine --> EmailService
PDFGenerator --> FileStorage
AuditSystem --> EmployeeDB
AuditSystem --> PayrollDB
```

**Diagram sources**
- [AGENTS.md:102-118](file://AGENTS.md#L102-L118)

The architecture supports multiple integration patterns including direct database connections, REST API consumption, and event-driven communication with external systems like Thai Social Security.

**Section sources**
- [AGENTS.md:102-118](file://AGENTS.md#L102-L118)

## Detailed Component Analysis

### Payroll Mode Processing

Each payroll mode follows a standardized processing pipeline while accommodating specific business rules and calculations. The system maintains consistency through shared interfaces and data structures while allowing for mode-specific customization.

```mermaid
sequenceDiagram
participant Client as "Client Application"
participant Engine as "PayrollCalculationService"
participant Mode as "PayrollModeProcessor"
participant Rules as "RuleManager"
participant DB as "Database Layer"
participant PDF as "PDF Generator"
Client->>Engine : Process Payroll Request
Engine->>Mode : Select Mode Handler
Mode->>Rules : Load Mode-Specific Rules
Rules-->>Mode : Return Applied Rules
Mode->>DB : Fetch Employee Data
DB-->>Mode : Return Employee Records
Mode->>Mode : Apply Mode Calculations
Mode->>Mode : Aggregate Results
Mode->>Engine : Return Processed Data
Engine->>PDF : Generate Payslip
PDF-->>Engine : Return PDF Document
Engine-->>Client : Return Final Results
```

**Diagram sources**
- [AGENTS.md:440-487](file://AGENTS.md#L440-L487)

### Data Flow Architecture

The system maintains strict data flow principles with clear boundaries between master data, transaction data, and calculated results. This ensures auditability and prevents data corruption through unauthorized modifications.

```mermaid
flowchart TD
MasterData["Master Data<br/>Employee Profiles<br/>Rate Configurations<br/>SSO Settings"] --> Validation["Data Validation<br/>Input Sanitization"]
TransactionData["Transaction Data<br/>Attendance Logs<br/>Work Logs<br/>Manual Overrides"] --> Validation
Validation --> Processing["Processing Engine<br/>Rule Application<br/>Calculation Engine"]
Processing --> Results["Result Data<br/>Payroll Items<br/>Payslips<br/>Financial Reports"]
Results --> Storage["Storage Layer<br/>Database Persistence"]
Storage --> AuditTrail["Audit Trail<br/>Change Tracking<br/>Compliance Logging"]
Results --> Output["Output Generation<br/>PDF Payslips<br/>Reports<br/>Exports"]
AuditTrail --> Compliance["Compliance Monitoring<br/>Regulatory Reporting"]
```

**Diagram sources**
- [AGENTS.md:36-91](file://AGENTS.md#L36-L91)

### Rule-Driven Configuration System

The system implements a comprehensive rule management system that allows business rules to be configured dynamically without code changes. This enables rapid adaptation to changing regulations and business requirements.

```mermaid
classDiagram
class RuleManager {
+loadRules(ruleType)
+validateRule(ruleDefinition)
+applyRule(rule, context)
+updateRule(ruleId, newDefinition)
+rollbackRule(ruleId)
}
class RuleEngine {
+evaluateCondition(condition)
+calculateFormula(formula, parameters)
+applyLogicOperator(operator, operands)
+validateExpression(expression)
}
class RuleRegistry {
+registerRuleType(type, validator)
+getRuleType(type)
+getAllRuleTypes()
+validateRuleDefinition(type, definition)
}
class AuditTrail {
+logRuleChange(ruleId, changeDetails)
+getRuleHistory(ruleId)
+compareRuleVersions(ruleId, version1, version2)
}
RuleManager --> RuleEngine : "uses"
RuleManager --> RuleRegistry : "manages"
RuleEngine --> AuditTrail : "logs changes"
```

**Diagram sources**
- [AGENTS.md:61-74](file://AGENTS.md#L61-L74)

**Section sources**
- [AGENTS.md:438-506](file://AGENTS.md#L438-L506)

## Dependency Analysis

The system exhibits low coupling and high cohesion through well-defined interfaces and service boundaries. Dependencies flow primarily from presentation to business logic to data access layers, with clear inversion of control through dependency injection.

```mermaid
graph LR
subgraph "External Dependencies"
Laravel["Laravel Framework"]
MySQL["MySQL Database"]
DomPDF["DomPDF Library"]
AlpineJS["Alpine.js"]
end
subgraph "Internal Dependencies"
Controllers["Controllers"]
Services["Services"]
Models["Models"]
Repositories["Repositories"]
Enums["Enums"]
end
subgraph "Business Rules"
PayrollRules["Payroll Calculation Rules"]
SSORules["Social Security Rules"]
BonusRules["Bonus Calculation Rules"]
AttendanceRules["Attendance Rules"]
end
Laravel --> Controllers
Controllers --> Services
Services --> Models
Models --> Repositories
Services --> PayrollRules
Services --> SSORules
Services --> BonusRules
Services --> AttendanceRules
AlpineJS --> Controllers
DomPDF --> Services
MySQL --> Repositories
```

**Diagram sources**
- [AGENTS.md:104-110](file://AGENTS.md#L104-L110)

The dependency structure supports scalability through horizontal scaling of services and database sharding for large datasets. The rule-driven architecture enables easy modification of business logic without affecting core system components.

**Section sources**
- [AGENTS.md:104-118](file://AGENTS.md#L104-L118)

## Performance Considerations

### Database Optimization

The system employs several database optimization strategies including proper indexing, query optimization, and caching mechanisms. The MySQL schema design prioritizes performance with appropriate data types and normalization while maintaining phpMyAdmin compatibility.

### Caching Strategy

The architecture supports multiple caching layers including:
- Application-level caching for frequently accessed rules and configurations
- Database query result caching for expensive calculations
- Session-based caching for user-specific data
- Static asset caching for improved frontend performance

### Scalability Patterns

Horizontal scaling is achieved through:
- Microservice decomposition for independent scaling of major components
- Database read replicas for reporting and analytics
- Message queuing for asynchronous processing of heavy calculations
- CDN integration for static assets and generated documents

## Troubleshooting Guide

### Common Issues and Solutions

**Payroll Calculation Errors**
- Verify rule configurations match payroll mode requirements
- Check for missing or invalid employee data
- Review audit logs for recent changes that may affect calculations
- Validate database constraints and foreign key relationships

**Payslip Generation Problems**
- Ensure PDF generation library is properly configured
- Check file permissions for document storage
- Verify template consistency across different payroll modes
- Review memory limits for large document generation

**Performance Degradation**
- Monitor database query execution times
- Implement proper indexing strategies
- Review caching configuration effectiveness
- Analyze memory usage patterns during peak processing

**Integration Failures**
- Validate external system credentials and endpoints
- Check network connectivity and firewall configurations
- Review API response formats and error handling
- Monitor integration logs for failure patterns

**Section sources**
- [AGENTS.md:663-672](file://AGENTS.md#L663-L672)

## Conclusion

The xHR Payroll & Finance System represents a sophisticated approach to payroll automation that balances user experience with technical excellence. Through its service-oriented architecture, rule-driven configuration system, and comprehensive audit capabilities, the system provides a robust foundation for scalable payroll processing.

The PHP-first development approach combined with Laravel's powerful ecosystem ensures maintainability and extensibility while MySQL's proven reliability provides solid data persistence. The system's design accommodates future growth through modular architecture and clear separation of concerns.

Key strengths include:
- Comprehensive rule management enabling rapid adaptation to regulatory changes
- Audit-ready design supporting compliance and regulatory requirements
- Flexible payroll mode support accommodating diverse business models
- Scalable architecture supporting enterprise-level deployment
- User-friendly interface maintaining spreadsheet-like experience

The system successfully transforms traditional payroll processing from a manual, error-prone Excel-based approach into an automated, auditable, and scalable solution that maintains the familiar user experience while providing professional-grade functionality.