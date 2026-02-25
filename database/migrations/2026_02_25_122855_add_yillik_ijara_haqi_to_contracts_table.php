<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Annual rent amount (shartnoma_summasi will store this too, but explicit field for clarity)
            $table->decimal('yillik_ijara_haqi', 15, 2)->nullable()->after('shartnoma_summasi');

            // Add comment column to explain contract structure
            $table->text('shartnoma_izohi')->nullable()->after('yillik_ijara_haqi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['yillik_ijara_haqi', 'shartnoma_izohi']);
        });
    }
};
