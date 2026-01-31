<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add avans (advance payment) tracking to payments
     */
    public function up(): void
    {
        // Add avans column to payments
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('avans', 15, 2)->default(0)->after('auksion_uchun')
                  ->comment('Advance payment - not yet applied to any schedule');
        });

        // Add avans_balans to contracts to track total credit
        Schema::table('contracts', function (Blueprint $table) {
            $table->decimal('avans_balans', 15, 2)->default(0)->after('joriy_yil')
                  ->comment('Total advance payment balance (credit)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('avans');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('avans_balans');
        });
    }
};
