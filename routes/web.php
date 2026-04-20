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
use App\Http\Controllers\BonusManagementController;
use App\Http\Controllers\AnnualSummaryController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\WorkCommandController;
use App\Http\Controllers\PayrollBatchController;

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// All authenticated routes
Route::middleware('auth')->group(function () {

Route::get('/', function () {
    $user = auth()->user();
    if ($user && $user->hasRole('owner') && !$user->hasRole('admin')) {
        return redirect()->route('workspace.my');
    }

    return redirect()->route('employees.index');
});

Route::get('/my/workspace/{month?}/{year?}', [WorkspaceController::class, 'myWorkspace'])
    ->middleware('role:admin,owner')
    ->name('workspace.my');

// Employees Specific Routes
Route::prefix('employees')->name('employees.')->middleware('role:admin')->group(function () {
    Route::get('/generate-code', [EmployeeController::class, 'generateCode'])->name('generate-code');
    Route::patch('/{employee}/toggle-status', [EmployeeController::class, 'toggleStatus'])->name('toggle-status');
});

// Employees CRUD
Route::resource('employees', EmployeeController::class)->middleware('role:admin');

// Workspace
Route::prefix('workspace')->name('workspace.')->group(function () {
    Route::get('/{employee}/{month}/{year}', [WorkspaceController::class, 'show'])
        ->middleware('role:admin,owner')
        ->name('show');

    Route::post('/{employee}/{month}/{year}/claims', [WorkspaceController::class, 'storeClaim'])
        ->middleware('role:admin,owner')
        ->name('claims.store');

    Route::get('/{employee}/{month}/{year}/grid-refresh', [WorkspaceController::class, 'getGridRefresh'])
        ->middleware('role:admin,owner')
        ->name('grid.refresh');

    Route::middleware('role:admin')->group(function () {
        Route::post('/{employee}/{month}/{year}/recalculate', [WorkspaceController::class, 'recalculate'])->name('recalculate');
        Route::post('/{employee}/{month}/{year}/attendance', [WorkspaceController::class, 'saveAttendance'])->name('saveAttendance');
        Route::post('/{employee}/{month}/{year}/attendance-row', [WorkspaceController::class, 'saveAttendanceRow'])->name('saveAttendanceRow');
        Route::post('/{employee}/{month}/{year}/worklogs', [WorkspaceController::class, 'saveWorkLogs'])->name('saveWorkLogs');
        Route::post('/{employee}/{month}/{year}/items', [WorkspaceController::class, 'updatePayrollItem'])->name('updateItem');
        Route::patch('/{employee}/{month}/{year}/payroll', [WorkspaceController::class, 'updatePayrollItem'])->name('payroll.update');
        Route::post('/{employee}/{month}/{year}/proof', [WorkspaceController::class, 'uploadProof'])->name('proof.upload');
        Route::post('/{employee}/module/toggle', [WorkspaceController::class, 'toggleModule'])->name('module.toggle');
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

// Leave & Day-swap Requests
Route::prefix('leave')->name('leave.')->group(function () {
    Route::get('/', [LeaveRequestController::class, 'index'])->name('index');
    Route::post('/store', [LeaveRequestController::class, 'storeLeave'])->name('store');
    Route::post('/swap', [LeaveRequestController::class, 'storeSwap'])->name('swap.store');
    Route::post('/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancelLeave'])->name('cancel');
    Route::post('/swap/{daySwapRequest}/cancel', [LeaveRequestController::class, 'cancelSwap'])->name('swap.cancel');
    // Admin-only review
    Route::middleware('role:admin')->group(function () {
        Route::patch('/{leaveRequest}/review', [LeaveRequestController::class, 'reviewLeave'])->name('review');
        Route::patch('/swap/{daySwapRequest}/review', [LeaveRequestController::class, 'reviewSwap'])->name('swap.review');
    });
});


// Payslip
Route::prefix('payslip')->name('payslip.')->group(function () {
    Route::get('/{employee}/{month}/{year}/preview', [PayslipController::class, 'preview'])
        ->middleware('role:admin,owner')
        ->name('preview');
    Route::get('/{employee}/{month}/{year}/pdf', [PayslipController::class, 'downloadPdf'])
        ->middleware('role:admin,owner')
        ->name('pdf');
    Route::post('/{employee}/{month}/{year}/finalize', [PayslipController::class, 'finalize'])
        ->middleware('role:admin')
        ->name('finalize');
    Route::post('/{employee}/{month}/{year}/unfinalize', [PayslipController::class, 'unfinalize'])
        ->middleware('role:admin')
        ->name('unfinalize');
});

// Company Finance
Route::get('/company/expenses', [CompanyFinanceController::class, 'expenses'])
    ->name('company.expenses')
    ->middleware('role:admin');
Route::prefix('company')->name('company.')->middleware('role:admin')->group(function () {
    Route::get('/finance', [CompanyFinanceController::class, 'index'])->name('finance');
    Route::post('/revenue', [CompanyFinanceController::class, 'storeRevenue'])->name('revenue.store');
    Route::patch('/revenue/{revenue}', [CompanyFinanceController::class, 'updateRevenue'])->name('revenue.update');
    Route::delete('/revenue/{revenue}', [CompanyFinanceController::class, 'deleteRevenue'])->name('revenue.delete');
    Route::post('/expense', [CompanyFinanceController::class, 'storeExpense'])->name('expense.store');
    Route::patch('/expense/{expense}', [CompanyFinanceController::class, 'updateExpense'])->name('expense.update');
    Route::delete('/expense/{expense}', [CompanyFinanceController::class, 'deleteExpense'])->name('expense.delete');
    Route::post('/subscription', [CompanyFinanceController::class, 'storeSubscription'])->name('subscription.store');
    Route::delete('/subscription/{subscription}', [CompanyFinanceController::class, 'deleteSubscription'])->name('subscription.delete');
});

// Annual Summary
Route::get('/annual', [AnnualSummaryController::class, 'index'])->name('annual.index')->middleware('role:admin');

// Payroll Batches (History)
Route::prefix('payroll-batches')->name('payroll-batches.')->middleware('role:admin')->group(function () {
    Route::get('/', [PayrollBatchController::class, 'index'])->name('index');
    Route::get('/{year}/{month}', [PayrollBatchController::class, 'show'])->name('show');
});

// Audit Log
Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index')->middleware('role:admin');

// WORK Command Center
Route::prefix('work')->name('work.')->middleware('role:admin')->group(function () {
    Route::get('/', [WorkCommandController::class, 'index'])->name('index');

    // Editing Jobs (Consolidated Pipeline)
    Route::post('/job', [WorkCommandController::class, 'storeEditingJob'])->name('editing-job.store');
    Route::patch('/job/{editingJob}', [WorkCommandController::class, 'updateEditingJob'])->name('editing-job.update');
    Route::delete('/job/{editingJob}', [WorkCommandController::class, 'deleteEditingJob'])->name('editing-job.delete');
});

Route::prefix('work')->name('work.')->middleware('role:admin,owner')->group(function () {
    Route::post('/job/{editingJob}/start', [WorkCommandController::class, 'startEditingJob'])->name('editing-job.start');
    Route::post('/job/{editingJob}/mark-ready', [WorkCommandController::class, 'markEditingJobReady'])->name('editing-job.mark-ready');
    Route::post('/job/{editingJob}/finalize', [WorkCommandController::class, 'finalizeEditingJob'])->name('editing-job.finalize');
});

// Settings & Rules
Route::prefix('settings')->name('settings.')->middleware('role:admin')->group(function () {
    Route::get('/rules', [SettingsController::class, 'rules'])->name('rules');
    Route::patch('/rules/{type}', [SettingsController::class, 'updateRule'])->name('rules.update');
    Route::get('/bonus', [BonusManagementController::class, 'index'])->name('bonus.index');
    Route::post('/bonus/cycles', [BonusManagementController::class, 'storeCycle'])->name('bonus.cycles.store');
    Route::patch('/bonus/cycles/{cycle}', [BonusManagementController::class, 'updateCycle'])->name('bonus.cycles.update');
    Route::post('/bonus/calculate', [BonusManagementController::class, 'calculate'])->name('bonus.calculate');
    Route::post('/bonus/batch-calculate', [BonusManagementController::class, 'batchCalculate'])->name('bonus.batch-calculate');
    Route::post('/bonus/approve', [BonusManagementController::class, 'approve'])->name('bonus.approve');
    Route::put('/bonus/cycles/{cycle}/months', [BonusManagementController::class, 'updateSelectedMonths'])->name('bonus.cycles.months.update');
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
        Route::post('/layer-rate-rules', [MasterDataController::class, 'storeLayerRateRule'])->name('layer-rate-rules.store');
        Route::patch('/layer-rate-rules/{layerRateRule}', [MasterDataController::class, 'updateLayerRateRule'])->name('layer-rate-rules.update');
        Route::delete('/layer-rate-rules/{layerRateRule}', [MasterDataController::class, 'deleteLayerRateRule'])->name('layer-rate-rules.delete');
        Route::patch('/workspace-access/{employee}', [MasterDataController::class, 'updateWorkspaceAccess'])->name('workspace-access.update');
        
        Route::post('/job-stages', [MasterDataController::class, 'storeJobStage'])->name('job-stages.store');
        Route::patch('/job-stages/{jobStage}', [MasterDataController::class, 'updateJobStage'])->name('job-stages.update');
        Route::delete('/job-stages/{jobStage}', [MasterDataController::class, 'deleteJobStage'])->name('job-stages.delete');
        // Games
        Route::post('/games', [MasterDataController::class, 'storeGame'])->name('games.store');
        Route::patch('/games/{game}', [MasterDataController::class, 'updateGame'])->name('games.update');
        Route::delete('/games/{game}', [MasterDataController::class, 'deleteGame'])->name('games.delete');
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
});

}); // End auth middleware
