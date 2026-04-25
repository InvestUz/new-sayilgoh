<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     *
     * To'lov taqsimoti faqat `App\Services\PaymentApplicator` orqali
     * qo'lda chaqiriladi: `Api\PaymentController::store` va
     * `WebController::paymentsStore`. Eloquent observer qasddan ro'yxatdan
     * o'tkazilmaydi — aks holda har bir to'lov ikki marta qo'llanardi.
     */
    public function boot(): void
    {
    }
}
