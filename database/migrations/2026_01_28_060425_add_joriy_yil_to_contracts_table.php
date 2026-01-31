<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Shartnomaga joriy (faol) yil ustunini qo'shish
 * Bu ustun qaysi yilning to'lov grafigi hozirda faol ekanligini ko'rsatadi
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Joriy faol yil - qaysi yilning grafigi hozirda ko'rsatilishi kerak
            $table->integer('joriy_yil')->nullable()->after('holat');
        });

        // Mavjud shartnomalar uchun joriy_yil ni to'ldirish
        // Har bir shartnoma uchun eng so'nggi yilni olish
        DB::statement('
            UPDATE contracts
            SET joriy_yil = (
                SELECT MAX(yil)
                FROM payment_schedules
                WHERE payment_schedules.contract_id = contracts.id
            )
            WHERE EXISTS (
                SELECT 1 FROM payment_schedules
                WHERE payment_schedules.contract_id = contracts.id
            )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('joriy_yil');
        });
    }
};
