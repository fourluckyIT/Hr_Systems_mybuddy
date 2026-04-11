<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\CompanyFinanceController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\WorkManagerController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\AnnualSummaryController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\WorkCommandController;

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// All authenticated routes
Route::middleware('auth')->group(function () {

Route::get('/', function () {
    $user = auth()->user();
    if ($user && $user->hasAnyRole(['employee', 'viewer']) && !$user->hasAnyRole(['admin', 'hr', 'manager'])) {
        return redirect()->route('workspace.my');
    }

    return redirect()->route('employees.index');
});

Route::get('/my/workspace/{month?}/{year?}', [WorkspaceController::class, 'myWorkspace'])
    ->middleware('role:employee,viewer')
    ->name('workspace.my');

// Employees CRUD
Route::resource('employees', EmployeeController::class)->middleware('role:admin,hr,manager');

// Workspace
Route::prefix('workspace')->name('workspace.')->group(function () {
    Route::get('/{employee}/{month}/{year}', [WorkspaceController::class, 'show'])
        ->middleware('role:admin,hr,manager,employee,viewer')
        ->name('show');

    Route::middleware('role:admin,hr,manager')->group(function () {
        Route::post('/{employee}/{month}/{year}/recalculate', [WorkspaceController::class, 'recalculate'])->name('recalculate');
        Route::post('/{employee}/{month}/{year}/attendance', [WorkspaceController::class, 'saveAttendance'])->name('saveAttendance');
        Route::post('/{employee}/{month}/{year}/attendance-row', [WorkspaceController::class, 'saveAttendanceRow'])->name('saveAttendanceRow');
        Route::post('/{employee}/{month}/{year}/worklogs', [WorkspaceController::class, 'saveWorkLogs'])->name('saveWorkLogs');
        Route::post('/{employee}/{month}/{year}/items', [WorkspaceController::class, 'updatePayrollItem'])->name('updateItem');
        Route::patch('/{employee}/{month}/{year}/payroll', [WorkspaceController::class, 'updatePayrollItem'])->name('payroll.update');
        Route::post('/{employee}/{month}/{year}/proof', [WorkspaceController::class, 'uploadProof'])->name('proof.upload');
        Route::post('/{employee}/module/toggle', [WorkspaceController::class, 'toggleModule'])->name('module.toggle');
        Route::post('/{employee}/{month}/{year}/claims', [WorkspaceController::class, 'storeClaim'])->name('claims.store');
        Route::post('/{employee}/{month}/{year}/performance', [WorkspaceController::class, 'storePerformanceRecord'])->name('performance.store');
        Route::delete('/performance/{record}', [WorkspaceController::class, 'deletePerformanceRecord'])->name('performance.delete');
        Route::patch('/claims/{claim}/approve', [WorkspaceController::class, 'approveClaim'])->name('claims.approve');
        Route::delete('/claims/{claim}', [WorkspaceController::class, 'deleteClaim'])->name('claims.delete');
        Route::patch('/{employee}/advance-ceiling', [WorkspaceController::class, 'updateAdvanceCeiling'])->name('updateAdvanceCeiling');
        Route::post('/worklog/{workLog}/toggle', [WorkspaceController::class, 'toggleWorkLog'])->name('toggleWorkLog');
    });
});

// Calendar
Route::get('/calendar/{month?}/{year?}', [CalendarController::class, 'index'])->name('calendar.index');

// Employees
Route::prefix('employees')->name('employees.')->group(function () {
    Route::get('/', [EmployeeController::class, 'index'])->name('index');
    Route::get('/create', [EmployeeController::class, 'create'])->name('create');
    Route::post('/', [EmployeeController::class, 'store'])->name('store');
    Route::patch('/{employee}/toggle-status', [EmployeeController::class, 'toggleStatus'])->name('toggle-status');
})->middleware('role:admin,hr,manager');

// Payslip
Route::prefix('payslip')->name('payslip.')->group(function () {
    Route::get('/{employee}/{month}/{year}/preview', [PayslipController::class, 'preview'])->name('preview');
    Route::post('/{employee}/{month}/{year}/finalize', [PayslipController::class, 'finalize'])->name('finalize');
    Route::post('/{employee}/{month}/{year}/unfinalize', [PayslipController::class, 'unfinalize'])->name('unfinalize');
    Route::get('/{employee}/{month}/{year}/pdf', [PayslipController::class, 'downloadPdf'])->name('pdf');
})->middleware('role:admin,hr,manager');

// Company Finance
Route::get('/company/expenses', [CompanyFinanceController::class, 'expenses'])->name('company.expenses');
Route::prefix('company')->name('company.')->group(function () {
    Route::get('/finance', [CompanyFinanceController::class, 'index'])->name('finance');
    Route::post('/revenue', [CompanyFinanceController::class, 'storeRevenue'])->name('revenue.store');
    Route::patch('/revenue/{revenue}', [CompanyFinanceController::class, 'updateRevenue'])->name('revenue.update');
    Route::delete('/revenue/{revenue}', [CompanyFinanceController::class, 'deleteRevenue'])->name('revenue.delete');
    Route::post('/expense', [CompanyFinanceController::class, 'storeExpense'])->name('expense.store');
    Route::patch('/expense/{expense}', [CompanyFinanceController::class, 'updateExpense'])->name('expense.update');
    Route::delete('/expense/{expense}', [CompanyFinanceController::class, 'deleteExpense'])->name('expense.delete');
    Route::post('/subscription', [CompanyFinanceController::class, 'storeSubscription'])->name('subscription.store');
    Route::delete('/subscription/{subscription}', [CompanyFinanceController::class, 'deleteSubscription'])->name('subscription.delete');
})->middleware('role:admin,hr');

// Annual Summary
Route::get('/annual', [AnnualSummaryController::class, 'index'])->name('annual.index')->middleware('role:admin,hr');

// Audit Log
Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index')->middleware('role:admin,hr');

// WORK Command Center
Route::prefix('work')->name('work.')->group(function () {
    Route::get('/', [WorkCommandController::class, 'index'])->name('index');
    // Recording
    Route::post('/recording', [WorkCommandController::class, 'storeRecording'])->name('recording.store');
    Route::patch('/recording/{recording}/status', [WorkCommandController::class, 'updateRecordingStatus'])->name('recording.status');
    Route::patch('/recording/{recording}/schedule', [WorkCommandController::class, 'updateRecordingSchedule'])->name('recording.schedule');
    Route::delete('/recording/{recording}', [WorkCommandController::class, 'deleteRecording'])->name('recording.delete');
    Route::post('/recording/{recording}/assign', [WorkCommandController::class, 'assignToRecording'])->name('recording.assign');
    Route::delete('/recording/assignee/{assignee}', [WorkCommandController::class, 'removeRecordingAssignee'])->name('recording.assignee.remove');
    // Resources
    Route::post('/resource', [WorkCommandController::class, 'storeResource'])->name('resource.store');
    Route::patch('/resource/{resource}/status', [WorkCommandController::class, 'updateResourceStatus'])->name('resource.status');
    Route::delete('/resource/{resource}', [WorkCommandController::class, 'deleteResource'])->name('resource.delete');
    // Edit Jobs
    Route::post('/edit-job', [WorkCommandController::class, 'storeEditJob'])->name('edit-job.store');
    Route::patch('/edit-job/{editJob}/status', [WorkCommandController::class, 'updateEditJobStatus'])->name('edit-job.status');
    Route::delete('/edit-job/{editJob}', [WorkCommandController::class, 'deleteEditJob'])->name('edit-job.delete');
    // Approved Output
    Route::post('/approved-output', [WorkCommandController::class, 'storeApprovedOutput'])->name('approved-output.store');
})->middleware('role:admin,hr');

// Settings & Rules
Route::prefix('settings')->name('settings.')->group(function () {
    Route::get('/rules', [SettingsController::class, 'rules'])->name('rules');
    Route::patch('/rules/{type}', [SettingsController::class, 'updateRule'])->name('rules.update');
    Route::post('/holidays', [SettingsController::class, 'addHoliday'])->name('holidays.add');
    Route::post('/holidays/load-legal', [SettingsController::class, 'loadLegalHolidays'])->name('holidays.load-legal');
    Route::delete('/holidays/{holiday}', [SettingsController::class, 'deleteHoliday'])->name('holidays.delete');
    Route::get('/company', [SettingsController::class, 'company'])->name('company');
    Route::post('/company', [SettingsController::class, 'updateCompany'])->name('company.update');

    // Master Data
    Route::get('/master-data', [MasterDataController::class, 'index'])->name('master-data');
    Route::prefix('master-data')->name('master-data.')->group(function () {
        Route::post('/payroll-item-types', [MasterDataController::class, 'storePayrollItemType'])->name('payroll-item-types.store');
        Route::patch('/payroll-item-types/{payrollItemType}', [MasterDataController::class, 'updatePayrollItemType'])->name('payroll-item-types.update');
        Route::delete('/payroll-item-types/{payrollItemType}', [MasterDataController::class, 'deletePayrollItemType'])->name('payroll-item-types.delete');
        Route::post('/departments', [MasterDataController::class, 'storeDepartment'])->name('departments.store');
        Route::patch('/departments/{department}', [MasterDataController::class, 'updateDepartment'])->name('departments.update');
        Route::delete('/departments/{department}', [MasterDataController::class, 'deleteDepartment'])->name('departments.delete');
        Route::post('/positions', [MasterDataController::class, 'storePosition'])->name('positions.store');
        Route::patch('/positions/{position}', [MasterDataController::class, 'updatePosition'])->name('positions.update');
        Route::delete('/positions/{position}', [MasterDataController::class, 'deletePosition'])->name('positions.delete');
        Route::patch('/workspace-access/{employee}', [MasterDataController::class, 'updateWorkspaceAccess'])->name('workspace-access.update');
        
        Route::post('/job-stages', [MasterDataController::class, 'storeJobStage'])->name('job-stages.store');
        Route::patch('/job-stages/{jobStage}', [MasterDataController::class, 'updateJobStage'])->name('job-stages.update');
        Route::delete('/job-stages/{jobStage}', [MasterDataController::class, 'deleteJobStage'])->name('job-stages.delete');
    });

    Route::prefix('works')->name('works.')->group(function () {
        Route::get('/', [WorkManagerController::class, 'index'])->name('index');
        Route::post('/', [WorkManagerController::class, 'store'])->name('store');
        Route::patch('/{workLogType}', [WorkManagerController::class, 'update'])->name('update');
        Route::patch('/{workLogType}/toggle', [WorkManagerController::class, 'toggle'])->name('toggle');
        Route::delete('/{workLogType}', [WorkManagerController::class, 'destroy'])->name('delete');
        Route::post('/assignments', [WorkManagerController::class, 'storeAssignment'])->name('assignments.store');
        Route::patch('/assignments/{workAssignment}', [WorkManagerController::class, 'updateAssignment'])->name('assignments.update');
        Route::delete('/assignments/{workAssignment}', [WorkManagerController::class, 'deleteAssignment'])->name('assignments.delete');
    });
})->middleware('role:admin');

}); // End auth middleware
