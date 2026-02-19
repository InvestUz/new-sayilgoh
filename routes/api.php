<?php

use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\LotController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\PenaltyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ============================================
// IJARACHILAR (Tenants)
// ============================================
Route::prefix('tenants')->group(function () {
    Route::get('/', [TenantController::class, 'index']);
    Route::post('/', [TenantController::class, 'store']);
    Route::get('/dropdown', [TenantController::class, 'dropdown']);
    Route::get('/{tenant}', [TenantController::class, 'show']);
    Route::put('/{tenant}', [TenantController::class, 'update']);
    Route::delete('/{tenant}', [TenantController::class, 'destroy']);
});

// ============================================
// LOTLAR (Lots/Objects)
// ============================================
Route::prefix('lots')->group(function () {
    Route::get('/', [LotController::class, 'index']);
    Route::post('/', [LotController::class, 'store']);
    Route::get('/available', [LotController::class, 'available']);
    Route::get('/districts', [LotController::class, 'districts']);
    Route::get('/{lot}', [LotController::class, 'show']);
    Route::put('/{lot}', [LotController::class, 'update']);
    Route::delete('/{lot}', [LotController::class, 'destroy']);
});

// ============================================
// SHARTNOMALAR (Contracts)
// ============================================
Route::prefix('contracts')->group(function () {
    Route::get('/', [ContractController::class, 'index']);
    Route::post('/', [ContractController::class, 'store']);
    Route::get('/debtors', [ContractController::class, 'debtors']);
    Route::get('/statistics', [ContractController::class, 'statistics']);
    Route::get('/{contract}', [ContractController::class, 'show']);
    Route::put('/{contract}', [ContractController::class, 'update']);
    Route::get('/{contract}/payment-schedule', [ContractController::class, 'paymentSchedule']);
});

// ============================================
// TO'LOVLAR (Payments)
// ============================================
Route::prefix('payments')->group(function () {
    Route::get('/', [PaymentController::class, 'index']);
    Route::post('/', [PaymentController::class, 'store']);
    Route::post('/penalty', [PaymentController::class, 'storePenaltyPayment']);
    Route::get('/today', [PaymentController::class, 'today']);
    Route::get('/{payment}', [PaymentController::class, 'show']);
    Route::post('/{payment}/cancel', [PaymentController::class, 'cancel']);
    Route::get('/contract/{contract}', [PaymentController::class, 'byContract']);
});

// ============================================
// TO'LOV GRAFIGI (Payment Schedules)
// ============================================
Route::prefix('payment-schedules')->group(function () {
    Route::put('/{schedule}', [PaymentController::class, 'updateSchedule']);
    Route::delete('/{schedule}', [PaymentController::class, 'deleteSchedule']);
    Route::post('/contract/{contract}', [PaymentController::class, 'addSchedule']);
    Route::post('/contract/{contract}/bulk', [PaymentController::class, 'bulkAddSchedule']);
    Route::post('/contract/{contract}/regenerate', [PaymentController::class, 'regenerateSchedules']);
});

// ============================================
// PENYA (PENALTY) PAYMENTS
// ============================================
Route::post('/penalty-payments', [PaymentController::class, 'storePenaltyPayment']);

// Auth route (original)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// ============================================
// CALENDAR
// ============================================
Route::get('/calendar', [CalendarController::class, 'getPayments']);

// ============================================
// ANALYTICS
// ============================================
Route::get('/analytics/collection-rate', [AnalyticsController::class, 'getCollectionRate']);
Route::get('/analytics/monthly-comparison', [AnalyticsController::class, 'getMonthlyComparison']);

// ============================================
// PENALTY CALCULATOR & NOTIFICATIONS (Bildirg'inoma)
// ============================================
Route::prefix('penalty')->group(function () {
    // Calculator
    Route::post('/calculate', [PenaltyController::class, 'calculate']);

    // Notifications
    Route::post('/notification/generate', [PenaltyController::class, 'generateNotification']);
    Route::post('/notification/{notification}/pdf', [PenaltyController::class, 'generatePdf']);

    // Audit
    Route::get('/mismatches', [PenaltyController::class, 'getMismatches']);
});

// Schedule penalty details (for calculator auto-fill)
Route::get('/schedule/{schedule}/penalty-details', [PenaltyController::class, 'scheduleDetails']);

// Contract notifications
Route::get('/contracts/{contract}/notifications', [PenaltyController::class, 'contractNotifications']);
