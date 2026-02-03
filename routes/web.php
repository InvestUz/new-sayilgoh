<?php

use App\Http\Controllers\WebController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Pure Blade (No JavaScript/API)
|--------------------------------------------------------------------------
*/

// Dashboard - Data Center view
Route::get('/', [WebController::class, 'dataCenter'])->name('dashboard');
Route::get('/data-center', [WebController::class, 'dataCenter'])->name('data-center');

// Import Statistics - Ma'lumotlar import holati
Route::get('/import-stats', [WebController::class, 'importStats'])->name('import-stats');

// Unified Registry - All CRUD in one place
Route::prefix('registry')->group(function () {
    // Main registry page
    Route::get('/', [WebController::class, 'registryIndex'])->name('registry');

    // Tenants CRUD
    Route::get('/tenants/create', [WebController::class, 'tenantsCreate'])->name('registry.tenants.create');
    Route::post('/tenants', [WebController::class, 'tenantsStore'])->name('registry.tenants.store');
    Route::get('/tenants/{tenant}', [WebController::class, 'tenantsShow'])->name('registry.tenants.show');
    Route::get('/tenants/{tenant}/edit', [WebController::class, 'tenantsEdit'])->name('registry.tenants.edit');
    Route::put('/tenants/{tenant}', [WebController::class, 'tenantsUpdate'])->name('registry.tenants.update');
    Route::delete('/tenants/{tenant}', [WebController::class, 'tenantsDestroy'])->name('registry.tenants.destroy');

    // Lots CRUD
    Route::get('/lots/create', [WebController::class, 'lotsCreate'])->name('registry.lots.create');
    Route::post('/lots', [WebController::class, 'lotsStore'])->name('registry.lots.store');
    Route::get('/lots/{lot}', [WebController::class, 'lotsShow'])->name('registry.lots.show');
    Route::get('/lots/{lot}/edit', [WebController::class, 'lotsEdit'])->name('registry.lots.edit');
    Route::put('/lots/{lot}', [WebController::class, 'lotsUpdate'])->name('registry.lots.update');
    Route::delete('/lots/{lot}', [WebController::class, 'lotsDestroy'])->name('registry.lots.destroy');

    // Contracts CRUD
    Route::get('/contracts/create', [WebController::class, 'contractsCreate'])->name('registry.contracts.create');
    Route::post('/contracts', [WebController::class, 'contractsStore'])->name('registry.contracts.store');
    Route::get('/contracts/{contract}', [WebController::class, 'contractsShow'])->name('registry.contracts.show');
    Route::get('/contracts/{contract}/edit', [WebController::class, 'contractsEdit'])->name('registry.contracts.edit');
    Route::put('/contracts/{contract}', [WebController::class, 'contractsUpdate'])->name('registry.contracts.update');

    // Payments CRUD
    Route::get('/payments/create', [WebController::class, 'paymentsCreate'])->name('registry.payments.create');
    Route::post('/payments', [WebController::class, 'paymentsStore'])->name('registry.payments.store');
});

