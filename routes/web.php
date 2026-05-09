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
use App\Http\Controllers\OtRequestController;
use App\Http\Controllers\ExpenseTrackerController;
use App\Http\Controllers\ExtraIncomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\LeaveBalanceController;
use App\Http\Controllers\LeaveManagementController;

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Root: send authed users to welcome, otherwise to login
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('welcome')
        : redirect()->route('login');
});

// All authenticated routes
Route::middleware('auth')->group(function () {

Route::get('/welcome', [\App\Http\Controllers\WelcomeController::class, 'index'])->name('welcome');

Route::get('/my/workspace/{month?}/{year?}', [WorkspaceController::class, 'myWorkspace'])
    ->middleware('role:admin,owner')
    ->name('workspace.my');

// Employees Specific Routes
Route::prefix('employees')->name('employees.')->middleware('role:admin')->group(function () {
    Route::get('/generate-code', [EmployeeController::class, 'generateCode'])->name('generate-code');
    Route::patch('/{employee}/toggle-status', [EmployeeController::class, 'toggleStatus'])->name('toggle-status');
    Route::post('/{employee}/probation/pass', [EmployeeController::class, 'passProbation'])->name('probation.pass');
    Route::post('/{employee}/probation/fail', [EmployeeController::class, 'failProbation'])->name('probation.fail');
    Route::post('/{employee}/probation/extend', [EmployeeController::class, 'extendProbation'])->name('probation.extend');
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
        Route::post('/{employee}/{month}/{year}/proof', [WorkspaceController::class, 'uploadProof'])->name('proof.upload');
        Route::post('/{employee}/module/toggle', [WorkspaceController::class, 'toggleModule'])->name('module.toggle');
        Route::patch('/claims/{claim}/approve', [WorkspaceController::class, 'approveClaim'])->name('claims.approve');
        Route::delete('/claims/{claim}', [WorkspaceController::class, 'deleteClaim'])->name('claims.delete');
        Route::get('/claims/{claim}/print', [WorkspaceController::class, 'printClaim'])->name('claims.print');
        Route::patch('/{employee}/advance-ceiling', [WorkspaceController::class, 'updateAdvanceCeiling'])->name('updateAdvanceCeiling');
        Route::post('/worklog/{workLog}/toggle', [WorkspaceController::class, 'toggleWorkLog'])->name('toggleWorkLog');
    });
});

// Calendar
Route::get('/calendar/{month?}/{year?}', [CalendarController::class, 'index'])->name('calendar.index');

// Leave & Day-swap Requests — page list moved to portal; routes kept for form submit + cancel
Route::prefix('leave')->name('leave.')->group(function () {
    Route::get('/', fn() => redirect()->route('portal.index'))->name('index');  // legacy redirect
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


// OT Request (employee) + OT Inbox + Approve (admin)
Route::prefix('ot')->name('ot.')->group(function () {
    Route::get('/request', [OtRequestController::class, 'index'])->name('request');
    Route::post('/request', [OtRequestController::class, 'store'])->name('request.store');
    Route::post('/request/{otRequest}/cancel', [OtRequestController::class, 'cancel'])->name('request.cancel');

    Route::middleware('role:admin')->group(function () {
        Route::get('/inbox', [OtRequestController::class, 'inbox'])->name('inbox');
        Route::post('/request/{otRequest}/approve', [OtRequestController::class, 'approve'])->name('request.approve');
        Route::post('/request/{otRequest}/reject', [OtRequestController::class, 'reject'])->name('request.reject');
    });
});

// Expense & Revenue Tracker (admin) — analyst mode
Route::prefix('expense-tracker')->name('expense-tracker.')->middleware('role:admin')->group(function () {
    Route::get('/', [ExpenseTrackerController::class, 'index'])->name('index');
    Route::post('/entry', [ExpenseTrackerController::class, 'storeEntry'])->name('entry.store');
    Route::delete('/entry/{model}/{id}', [ExpenseTrackerController::class, 'destroyEntry'])->name('entry.delete');
    Route::post('/categories', [ExpenseTrackerController::class, 'storeCategory'])->name('categories.store');
    Route::delete('/categories/{category}', [ExpenseTrackerController::class, 'destroyCategory'])->name('categories.delete');
});

// Employee-initiated leave balance requests — go through approval flow
Route::prefix('leave-balance')->name('leave-balance.')->middleware('auth')->group(function () {
    Route::post('/{employee}/carryover/request', [LeaveBalanceController::class, 'requestCarryover'])->name('carryover.request');
    Route::post('/{employee}/encash/request', [LeaveBalanceController::class, 'requestEncash'])->name('encash.request');
});

// Leave balance management — carryover & encashment (admin only)
Route::prefix('leave-balance')->name('leave-balance.')->middleware('role:admin')->group(function () {
    Route::get('/{employee}/print-form', [LeaveBalanceController::class, 'printForm'])->name('print-form');
    Route::post('/{employee}/carryover', [LeaveBalanceController::class, 'carryover'])->name('carryover');
    Route::delete('/carryover/{carryover}', [LeaveBalanceController::class, 'deleteCarryover'])->name('carryover.delete');
    Route::post('/{employee}/encash', [LeaveBalanceController::class, 'encash'])->name('encash');
    Route::delete('/encashment/{encashment}', [LeaveBalanceController::class, 'deleteEncashment'])->name('encashment.delete');
});

// Centralized leave management dashboard (admin)
Route::prefix('leave-management')->name('leave-management.')->middleware('role:admin')->group(function () {
    Route::get('/', [LeaveManagementController::class, 'index'])->name('index');
    Route::post('/batch-carryover', [LeaveManagementController::class, 'batchCarryover'])->name('batch-carryover');
});

// Document Portal — replaces salary-advance. Admin sees all; employees see their own.
Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('/', [PortalController::class, 'index'])->name('index');
    Route::get('/{type}/{id}', [PortalController::class, 'show'])
        ->whereIn('type', ['leave', 'ot', 'swap', 'expense', 'carryover', 'encash'])
        ->name('show');
    Route::get('/{type}/{id}/print', [PortalController::class, 'print'])
        ->whereIn('type', ['leave', 'ot', 'swap', 'expense', 'carryover', 'encash'])
        ->name('print');
    Route::post('/{type}/{id}/attachments', [PortalController::class, 'uploadAttachment'])
        ->whereIn('type', ['leave', 'ot', 'swap', 'expense', 'carryover', 'encash'])
        ->name('attachments.store');
    Route::delete('/{type}/{id}/attachments/{attachment}', [PortalController::class, 'deleteAttachment'])
        ->whereIn('type', ['leave', 'ot', 'swap', 'expense', 'carryover', 'encash'])
        ->name('attachments.delete');

    Route::middleware('role:admin')->group(function () {
        Route::post('/{type}/{id}/approve', [PortalController::class, 'approve'])
            ->whereIn('type', ['leave', 'ot', 'swap', 'expense', 'carryover', 'encash'])
            ->name('approve');
        Route::post('/{type}/{id}/reject', [PortalController::class, 'reject'])
            ->whereIn('type', ['leave', 'ot', 'swap', 'expense', 'carryover', 'encash'])
            ->name('reject');
        Route::post('/bulk-export', [PortalController::class, 'bulkExport'])->name('bulk-export');
    });
});

// Backwards compatibility — old /salary-advance URLs redirect to the portal
Route::redirect('/salary-advance', '/portal?type=expense')->name('salary-advance.index');

// Extra income entries per employee (admin)
Route::prefix('workspace')->name('workspace.')->middleware('role:admin')->group(function () {
    Route::post('/{employee}/{month}/{year}/extra-income', [ExtraIncomeController::class, 'store'])->name('extra-income.store');
    Route::delete('/extra-income/{entry}', [ExtraIncomeController::class, 'destroy'])->name('extra-income.delete');
    Route::post('/{employee}/{month}/{year}/fl-layer-rates', [WorkspaceController::class, 'saveFreelanceLayerRates'])->name('fl-layer-rates.save');
    Route::patch('/work-log/{workLog}/rate', [WorkspaceController::class, 'updateWorkLogRate'])->name('work-log.rate.update');
});

// Notifications (all auth users)
Route::prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::post('/{notification}/read', [NotificationController::class, 'markRead'])->name('read');
    Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
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
    Route::post('/{employee}/{month}/{year}/update-payment-date', [PayslipController::class, 'updatePaymentDate'])
        ->middleware('role:admin')
        ->name('update-payment-date');
});

// Company Finance
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

    // Recording Sessions (YouTuber filming tracker)
    Route::get('/recording-sessions', [\App\Http\Controllers\RecordingSessionController::class, 'index'])->name('recording-sessions.index');
    Route::post('/recording-sessions', [\App\Http\Controllers\RecordingSessionController::class, 'store'])->name('recording-sessions.store');
    Route::patch('/recording-sessions/{recordingSession}', [\App\Http\Controllers\RecordingSessionController::class, 'update'])->name('recording-sessions.update');
    Route::delete('/recording-sessions/{recordingSession}', [\App\Http\Controllers\RecordingSessionController::class, 'destroy'])->name('recording-sessions.destroy');
});

Route::prefix('work')->name('work.')->group(function () {
    Route::post('/job/{editingJob}/start', [WorkCommandController::class, 'startEditingJob'])->name('editing-job.start')->middleware('role:admin,owner,editor');
    Route::post('/job/{editingJob}/mark-ready', [WorkCommandController::class, 'markEditingJobReady'])->name('editing-job.mark-ready')->middleware('role:admin,owner,editor');
    Route::post('/job/{editingJob}/finalize', [WorkCommandController::class, 'finalizeEditingJob'])->name('editing-job.finalize')->middleware('role:admin,owner');
    Route::post('/job/{editingJob}/direct-finalize', [WorkCommandController::class, 'directFinalizeEditingJob'])->name('editing-job.direct-finalize')->middleware('role:admin,owner');
});

// Settings & Rules
Route::prefix('settings')->name('settings.')->middleware('role:admin')->group(function () {
    Route::get('/rules', [SettingsController::class, 'rules'])->name('rules');
    Route::patch('/rules/{type}', [SettingsController::class, 'updateRule'])->name('rules.update');
    Route::get('/bonus', [BonusManagementController::class, 'index'])->name('bonus.index');
    Route::post('/bonus/cycles', [BonusManagementController::class, 'storeCycle'])->name('bonus.cycles.store');
    Route::patch('/bonus/cycles/{cycle}', [BonusManagementController::class, 'updateCycle'])->name('bonus.cycles.update');
    Route::delete('/bonus/cycles/{cycle}', [BonusManagementController::class, 'destroyCycle'])->name('bonus.cycles.destroy');
    Route::post('/bonus/calculate', [BonusManagementController::class, 'calculate'])->name('bonus.calculate');
    Route::post('/bonus/batch-calculate', [BonusManagementController::class, 'batchCalculate'])->name('bonus.batch-calculate');
    Route::post('/bonus/approve', [BonusManagementController::class, 'approve'])->name('bonus.approve');
    Route::put('/bonus/cycles/{cycle}/months', [BonusManagementController::class, 'updateSelectedMonths'])->name('bonus.cycles.months.update');
    Route::post('/bonus/cycles/{cycle}/transition', [BonusManagementController::class, 'transitionCycle'])->name('bonus.cycles.transition');
    Route::get('/bonus/cycles/{cycle}/preview-payslip-post', [BonusManagementController::class, 'previewPayslipPost'])->name('bonus.cycles.preview-payslip-post');
    Route::post('/bonus/preview', [BonusManagementController::class, 'preview'])->name('bonus.preview');
    Route::get('/bonus/cycles/{cycle}/employees/{employee}/metrics', [BonusManagementController::class, 'metricsForEmployee'])->name('bonus.metrics');
    Route::post('/bonus/calculations/{calculation}/recalculate', [BonusManagementController::class, 'recalculateRow'])->name('bonus.calculations.recalculate');
    Route::delete('/bonus/calculations/{calculation}', [BonusManagementController::class, 'destroyRow'])->name('bonus.calculations.destroy');
    
    // Performance Tiers
    Route::get('/tiers', [\App\Http\Controllers\PerformanceTierController::class, 'index'])->name('tiers.index');
    Route::post('/tiers', [\App\Http\Controllers\PerformanceTierController::class, 'store'])->name('tiers.store');
    Route::patch('/tiers/{tier}', [\App\Http\Controllers\PerformanceTierController::class, 'update'])->name('tiers.update');
    Route::delete('/tiers/{tier}', [\App\Http\Controllers\PerformanceTierController::class, 'destroy'])->name('tiers.destroy');

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
        Route::post('/layer-rate-templates', [MasterDataController::class, 'storeLayerRateTemplate'])->name('layer-rate-templates.store');
        Route::patch('/layer-rate-templates/{layerRateTemplate}', [MasterDataController::class, 'updateLayerRateTemplate'])->name('layer-rate-templates.update');
        Route::delete('/layer-rate-templates/{layerRateTemplate}', [MasterDataController::class, 'deleteLayerRateTemplate'])->name('layer-rate-templates.delete');
        Route::patch('/workspace-access/{employee}', [MasterDataController::class, 'updateWorkspaceAccess'])->name('workspace-access.update');
        
        Route::post('/job-stages', [MasterDataController::class, 'storeJobStage'])->name('job-stages.store');
        Route::patch('/job-stages/{jobStage}', [MasterDataController::class, 'updateJobStage'])->name('job-stages.update');
        Route::delete('/job-stages/{jobStage}', [MasterDataController::class, 'deleteJobStage'])->name('job-stages.delete');
        // Games
        Route::post('/games', [MasterDataController::class, 'storeGame'])->name('games.store');
        Route::patch('/games/{game}', [MasterDataController::class, 'updateGame'])->name('games.update');
        Route::delete('/games/{game}', [MasterDataController::class, 'deleteGame'])->name('games.delete');

        // Leave Policies (Master Data)
        Route::post('/leave-policies', [MasterDataController::class, 'storeLeavePolicy'])->name('leave-policies.store');
        Route::patch('/leave-policies/{leavePolicy}', [MasterDataController::class, 'updateLeavePolicy'])->name('leave-policies.update');
        Route::delete('/leave-policies/{leavePolicy}', [MasterDataController::class, 'destroyLeavePolicy'])->name('leave-policies.delete');

        // Holiday Types (Master Data)
        Route::post('/holiday-types', [MasterDataController::class, 'storeHolidayType'])->name('holiday-types.store');
        Route::patch('/holiday-types/{holidayType}', [MasterDataController::class, 'updateHolidayType'])->name('holiday-types.update');
        Route::delete('/holiday-types/{holidayType}', [MasterDataController::class, 'deleteHolidayType'])->name('holiday-types.delete');
    });

/*
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
*/
});

}); // End auth middleware
