<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ijaraga oluvchilar (Tenants) - Ariza beruvchilar ma'lumotlari
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();

            // Asosiy ma'lumotlar
            $table->string('name');                          // Korxona/Shaxs nomi
            $table->enum('type', ['yuridik', 'jismoniy'])->default('yuridik'); // Shaxs turi
            $table->string('inn')->unique();                 // INN/STIR
            $table->string('director_name')->nullable();     // Direktor F.I.O
            $table->string('passport_serial')->nullable();   // Pasport seriya va raqami

            // Aloqa ma'lumotlari
            $table->string('phone');                         // Telefon raqami
            $table->string('email')->nullable();             // Elektron pochta
            $table->text('address');                         // Yuridik manzil

            // Bank rekvizitlari
            $table->string('bank_name')->nullable();         // Bank nomi
            $table->string('bank_account')->nullable();      // Hisob raqami
            $table->string('bank_mfo')->nullable();          // MFO
            $table->string('oked')->nullable();              // OKED

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
