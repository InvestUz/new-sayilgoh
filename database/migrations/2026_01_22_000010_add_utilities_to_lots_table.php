<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lotlarga kommunikatsiyalar (utilities) maydonlarini qo'shish
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            // Kommunikatsiyalar (utilities)
            $table->boolean('has_elektr')->default(false)->after('rasmlar');       // Elektr
            $table->boolean('has_gaz')->default(false)->after('has_elektr');        // Gaz
            $table->boolean('has_suv')->default(false)->after('has_gaz');           // Suv
            $table->boolean('has_kanalizatsiya')->default(false)->after('has_suv'); // Kanalizatsiya
            $table->boolean('has_internet')->default(false)->after('has_kanalizatsiya'); // Internet
            $table->boolean('has_isitish')->default(false)->after('has_internet');  // Isitish tizimi
            $table->boolean('has_konditsioner')->default(false)->after('has_isitish'); // Konditsioner

            // Qo'shimcha ma'lumotlar
            $table->integer('xonalar_soni')->nullable()->after('has_konditsioner'); // Xonalar soni
            $table->integer('qavat')->nullable()->after('xonalar_soni');            // Qavat
            $table->integer('qavatlar_soni')->nullable()->after('qavat');           // Binodagi qavatlar
            $table->string('kadastr_raqami')->nullable()->after('qavatlar_soni');   // Kadastr raqami

            // Xarita uchun
            $table->string('map_url')->nullable()->after('longitude');              // Google/Yandex xarita URL
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropColumn([
                'has_elektr', 'has_gaz', 'has_suv', 'has_kanalizatsiya',
                'has_internet', 'has_isitish', 'has_konditsioner',
                'xonalar_soni', 'qavat', 'qavatlar_soni', 'kadastr_raqami', 'map_url'
            ]);
        });
    }
};
