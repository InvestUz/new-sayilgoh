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

// Tenants CRUD
Route::prefix('tenants')->name('tenants.')->group(function () {
    Route::get('/', [WebController::class, 'tenantsIndex'])->name('index');
    Route::get('/create', [WebController::class, 'tenantsCreate'])->name('create');
    Route::post('/', [WebController::class, 'tenantsStore'])->name('store');
    Route::get('/{tenant}', [WebController::class, 'tenantsShow'])->name('show');
    Route::get('/{tenant}/edit', [WebController::class, 'tenantsEdit'])->name('edit');
    Route::put('/{tenant}', [WebController::class, 'tenantsUpdate'])->name('update');
    Route::delete('/{tenant}', [WebController::class, 'tenantsDestroy'])->name('destroy');
});

// Lots CRUD
Route::prefix('lots')->name('lots.')->group(function () {
    Route::get('/', [WebController::class, 'lotsIndex'])->name('index');
    Route::get('/create', [WebController::class, 'lotsCreate'])->name('create');
    Route::post('/', [WebController::class, 'lotsStore'])->name('store');
    Route::get('/{lot}', [WebController::class, 'lotsShow'])->name('show');
    Route::get('/{lot}/edit', [WebController::class, 'lotsEdit'])->name('edit');
    Route::put('/{lot}', [WebController::class, 'lotsUpdate'])->name('update');
    Route::delete('/{lot}', [WebController::class, 'lotsDestroy'])->name('destroy');
});

// Contracts
Route::prefix('contracts')->name('contracts.')->group(function () {
    Route::get('/', [WebController::class, 'contractsIndex'])->name('index');
    Route::get('/create', [WebController::class, 'contractsCreate'])->name('create');
    Route::post('/', [WebController::class, 'contractsStore'])->name('store');
    Route::get('/{contract}', [WebController::class, 'contractsShow'])->name('show');
    Route::get('/{contract}/edit', [WebController::class, 'contractsEdit'])->name('edit');
    Route::put('/{contract}', [WebController::class, 'contractsUpdate'])->name('update');
});

// Payments
Route::prefix('payments')->name('payments.')->group(function () {
    Route::get('/', [WebController::class, 'paymentsIndex'])->name('index');
    Route::get('/create', [WebController::class, 'paymentsCreate'])->name('create');
    Route::post('/', [WebController::class, 'paymentsStore'])->name('store');
});

// Data Center Monitoring Dashboard
Route::get('/data-center', [WebController::class, 'dataCenter'])->name('data-center');
