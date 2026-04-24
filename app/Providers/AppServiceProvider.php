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
        // NOTE: PaymentObserver is intentionally NOT registered here.
        //
        // Historically Payment::observe(PaymentObserver::class) was used, but the
        // two main controller paths (Api\PaymentController::store and
        // WebController::paymentsStore) already invoke applyPaymentFIFO() right
        // after Payment::create(). Registering the observer caused every new
        // payment to be applied to the schedules TWICE, inflating tolangan_summa
        // and tolangan_penya. Allocation is now owned by the controllers only.
    }
}
