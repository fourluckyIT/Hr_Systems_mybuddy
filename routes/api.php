<?php

use App\Http\Controllers\BonusController;
use App\Http\Controllers\EditingJobController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('bonus')->group(function () {
        Route::post('/calculate', [BonusController::class, 'calculate']);
        Route::post('/batch-calculate', [BonusController::class, 'batchCalculate']);
        Route::get('/employee/{employee}/history', [BonusController::class, 'employeeHistory']);
        Route::post('/approve', [BonusController::class, 'approve']);
        Route::get('/cycle/{cycle}/summary', [BonusController::class, 'cycleSummary']);
        Route::put('/cycle/{cycle}/months', [BonusController::class, 'selectCycleMonths']);
        Route::get('/cycle/{cycle}/months', [BonusController::class, 'cycleMonths']);
    });

    // Editing Jobs Workflow
    Route::prefix('jobs')->group(function () {
        Route::get('/', [EditingJobController::class, 'index']);
        Route::post('/create', [EditingJobController::class, 'store']);
        Route::get('/overdue', [EditingJobController::class, 'overdue']);
        Route::get('/{job}', [EditingJobController::class, 'show']);
        Route::post('/{job}/start', [EditingJobController::class, 'start']);
        Route::post('/{job}/mark-ready', [EditingJobController::class, 'markReady']);
        Route::post('/{job}/finalize', [EditingJobController::class, 'finalize']);
        Route::put('/{job}/reassign', [EditingJobController::class, 'reassign']);
        Route::put('/{job}/update', [EditingJobController::class, 'update']);
        Route::delete('/{job}', [EditingJobController::class, 'destroy']);
    });

    Route::get('/performance/{employee}', [EditingJobController::class, 'performance']);
});
