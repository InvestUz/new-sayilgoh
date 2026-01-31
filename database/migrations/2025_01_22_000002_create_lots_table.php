<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lotlar (Objects/Properties) - Ijara obyektlari ma'lumotlari
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots', function (Blueprint $table) {
            $table->id();

            // Lot identifikatori
            $table->string('lot_raqami')->unique();          // Lot raqami

            // Obyekt ma'lumotlari
            $table->string('obyekt_nomi');                   // Obyekt nomi
            $table->text('manzil');                          // Manzil (to'liq)
            $table->string('tuman')->nullable();             // Tuman
            $table->string('kocha')->nullable();             // Ko'cha
            $table->string('uy_raqami')->nullable();         // Uy raqami
            $table->decimal('maydon', 12, 2);                // Maydon (kv.m)

            // Obyekt tavsifi
            $table->text('tavsif')->nullable();              // Qo'shimcha tavsif
            $table->enum('obyekt_turi', [                    // Obyekt turi
                'savdo',                                      // Savdo obyekti
                'xizmat',                                     // Xizmat ko'rsatish
                'ishlab_chiqarish',                          // Ishlab chiqarish
                'ombor',                                      // Ombor
                'ofis',                                       // Ofis
                'boshqa'                                      // Boshqa
            ])->default('savdo');

            // Lokatsiya
            $table->decimal('latitude', 10, 8)->nullable();  // Kenglik
            $table->decimal('longitude', 11, 8)->nullable(); // Uzunlik

            // Rasmlar (JSON array)
            $table->json('rasmlar')->nullable();             // Rasmlar yo'llari

            // Boshlang'ich narx (agar kerak bo'lsa)
            $table->decimal('boshlangich_narx', 15, 2)->nullable();

            // Holat
            $table->enum('holat', [
                'bosh',                                       // Bo'sh
                'ijarada',                                    // Ijarada
                'band',                                       // Band (shartnoma kutilmoqda)
                'tamirlashda'                                 // Ta'mirlashda
            ])->default('bosh');

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};
