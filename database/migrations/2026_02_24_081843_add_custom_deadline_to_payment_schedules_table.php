<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->date('custom_oxirgi_muddat')->nullable()->after('oxirgi_muddat');
            $table->text('muddat_ozgarish_izoh')->nullable()->after('custom_oxirgi_muddat');
        });
    }

    public function down(): void
    {
        Schema::table('payment_schedules', function (Blueprint $table) {
            $table->dropColumn(['custom_oxirgi_muddat', 'muddat_ozgarish_izoh']);
        });
    }
};
