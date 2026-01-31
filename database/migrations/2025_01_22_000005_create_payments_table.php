<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * To'lovlar (Payments) - Amalga oshirilgan to'lovlar
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->foreignId('payment_schedule_id')->nullable()->constrained('payment_schedules')->onDelete('set null');

            // To'lov ma'lumotlari
            $table->string('tolov_raqami')->unique();         // To'lov hujjat raqami
            $table->date('tolov_sanasi');                     // To'lov sanasi
            $table->decimal('summa', 15, 2);                  // To'lov summasi

            // To'lov taqsimoti
            $table->decimal('asosiy_qarz_uchun', 15, 2)->default(0);  // Asosiy qarzga
            $table->decimal('penya_uchun', 15, 2)->default(0);        // Penyaga
            $table->decimal('auksion_uchun', 15, 2)->default(0);      // Auksionga

            // To'lov usuli
            $table->enum('tolov_usuli', [
                'bank_otkazmasi',                             // Bank o'tkazmasi
                'naqd',                                       // Naqd pul
                'karta',                                      // Plastik karta
                'onlayn'                                      // Onlayn to'lov
            ])->default('bank_otkazmasi');

            // Hujjat
            $table->string('hujjat_raqami')->nullable();      // Platojka/kvitansiya raqami
            $table->string('hujjat_fayl')->nullable();        // Yuklangan hujjat

            // Holat
            $table->enum('holat', [
                'kutilmoqda',                                 // Tasdiqlash kutilmoqda
                'tasdiqlangan',                               // Tasdiqlangan
                'rad_etilgan',                                // Rad etilgan
                'qaytarilgan'                                 // Qaytarilgan
            ])->default('tasdiqlangan');

            $table->text('izoh')->nullable();                 // Izoh
            $table->foreignId('tasdiqlagan_id')->nullable();  // Tasdiqlagan user
            $table->timestamp('tasdiqlangan_sana')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indekslar
            $table->index(['contract_id', 'tolov_sanasi']);
            $table->index('holat');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
