<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Payment;
use App\Observers\PaymentObserver;
use App\Services\PenaltyCalculatorService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register PenaltyCalculatorService as singleton
        $this->app->singleton(PenaltyCalculatorService::class, function ($app) {
            return new PenaltyCalculatorService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Payment observer for automatic FIFO application
        Payment::observe(PaymentObserver::class);
    }
}
