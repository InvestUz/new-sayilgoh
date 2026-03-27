<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table) {
            // Asli to'lov summasi (o'zgarish oldida)
            $table->decimal('original_tolov_summasi', 15, 2)->nullable()->after('tolov_summasi')->comment('Original payment amount before any adjustments');
            // 14% oshirish bazi masofa qo'llandi
            $table->boolean('price_increased_14_percent')->default(false)->after('original_tolov_summasi')->comment('Whether 14% price increase was applied');
        });
    }

    public function down(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->dropColumn(['original_tolov_summasi', 'price_increased_14_percent']);
        });
    }
};
