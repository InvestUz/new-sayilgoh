<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * To'lov Grafigi (Payment Schedules) - Oyma-oy to'lov rejalari
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');

            // To'lov davri
            $table->integer('oy_raqami');                     // Nechanchi oy (1, 2, 3...)
            $table->integer('yil');                           // Yil
            $table->integer('oy');                            // Oy (1-12)
            $table->date('tolov_sanasi');                     // To'lashi kerak bo'lgan sana
            $table->date('oxirgi_muddat');                    // Penyasiz to'lash muddati

            // Summalar
            $table->decimal('tolov_summasi', 15, 2);         // To'lanishi kerak summa
            $table->decimal('tolangan_summa', 15, 2)->default(0);  // To'langan summa
            $table->decimal('qoldiq_summa', 15, 2);          // Qolgan summa

            // Penya
            $table->decimal('penya_summasi', 15, 2)->default(0);   // Hisoblangan penya
            $table->decimal('tolangan_penya', 15, 2)->default(0);  // To'langan penya
            $table->integer('kechikish_kunlari')->default(0);      // Kechikish kunlari

            // Holat
            $table->enum('holat', [
                'kutilmoqda',                                 // Hali muddati kelmagan
                'tolanmagan',                                 // Muddati o'tgan, to'lanmagan
                'qisman_tolangan',                           // Qisman to'langan
                'tolangan'                                    // To'liq to'langan
            ])->default('kutilmoqda');

            $table->timestamps();

            // Unikal constraint
            $table->unique(['contract_id', 'yil', 'oy']);
            $table->index(['holat', 'tolov_sanasi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_schedules');
    }
};
