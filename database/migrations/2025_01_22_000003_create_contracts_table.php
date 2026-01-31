<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shartnomalar (Contracts) - Ijara shartnomalar
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();

            // Bog'lanishlar
            $table->foreignId('lot_id')->constrained('lots')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');

            // Shartnoma identifikatori
            $table->string('shartnoma_raqami')->unique();    // Shartnoma raqami
            $table->date('shartnoma_sanasi');                // Shartnoma sanasi

            // Auksion ma'lumotlari
            $table->date('auksion_sanasi');                  // Auksion o'tkazilgan sana
            $table->string('auksion_bayonnoma_raqami')->nullable(); // Bayonnoma raqami
            $table->decimal('auksion_xarajati', 15, 2)->default(0); // Auksion xarajati (1%)

            // Shartnoma shartlari
            $table->decimal('shartnoma_summasi', 15, 2);     // Jami shartnoma summasi
            $table->decimal('oylik_tolovi', 15, 2);          // Oylik to'lov (hisoblangan)
            $table->integer('shartnoma_muddati');            // Muddat (oylar)
            $table->date('boshlanish_sanasi');               // Shartnoma boshlanish sanasi
            $table->date('tugash_sanasi');                   // Shartnoma tugash sanasi

            // Birinchi to'lov
            $table->date('birinchi_tolov_sanasi');           // Birinchi to'lov sanasi (10 ish kuni)

            // Dalolatnoma
            $table->string('dalolatnoma_raqami')->nullable(); // Topshirish dalolatnoma raqami
            $table->date('dalolatnoma_sanasi')->nullable();   // Topshirish sanasi
            $table->enum('dalolatnoma_holati', [
                'kutilmoqda',                                 // Kutilmoqda
                'topshirilgan',                               // Topshirilgan
                'qaytarilgan'                                 // Qaytarilgan
            ])->default('kutilmoqda');

            // Shartnoma holati
            $table->enum('holat', [
                'faol',                                       // Faol
                'tugagan',                                    // Tugagan
                'bekor_qilingan',                            // Bekor qilingan
                'muzlatilgan'                                 // Muzlatilgan
            ])->default('faol');

            // Qo'shimcha
            $table->text('izoh')->nullable();                // Izoh/eslatma
            $table->json('qoshimcha_shartlar')->nullable();  // Qo'shimcha shartlar

            $table->timestamps();
            $table->softDeletes();

            // Indekslar
            $table->index(['holat', 'boshlanish_sanasi']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
