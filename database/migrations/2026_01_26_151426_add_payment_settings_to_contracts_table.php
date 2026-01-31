<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // To'lov kuni (har oyning qaysi kunida to'lov)
            $table->unsignedTinyInteger('tolov_kuni')->default(10)->after('birinchi_tolov_sanasi');
            // Penya muddati (to'lov kunidan necha kun keyin penya boshlanadi)
            $table->unsignedTinyInteger('penya_muddati')->default(10)->after('tolov_kuni');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['tolov_kuni', 'penya_muddati']);
        });
    }
};
