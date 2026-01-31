<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Delete existing payment schedules
        DB::table('payment_schedules')->delete();

        // Drop foreign key first, then unique index, then recreate
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Drop foreign key constraint that depends on the index
        DB::statement('ALTER TABLE payment_schedules DROP FOREIGN KEY payment_schedules_contract_id_foreign');

        // Now drop the unique index
        DB::statement('ALTER TABLE payment_schedules DROP INDEX payment_schedules_contract_id_yil_oy_unique');

        // Add new unique index
        DB::statement('ALTER TABLE payment_schedules ADD UNIQUE INDEX payment_schedules_contract_oy_raqami_unique (contract_id, oy_raqami)');

        // Recreate foreign key
        DB::statement('ALTER TABLE payment_schedules ADD CONSTRAINT payment_schedules_contract_id_foreign FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE');

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('ALTER TABLE payment_schedules DROP FOREIGN KEY payment_schedules_contract_id_foreign');
        DB::statement('ALTER TABLE payment_schedules DROP INDEX payment_schedules_contract_oy_raqami_unique');
        DB::statement('ALTER TABLE payment_schedules ADD UNIQUE INDEX payment_schedules_contract_id_yil_oy_unique (contract_id, yil, oy)');
        DB::statement('ALTER TABLE payment_schedules ADD CONSTRAINT payment_schedules_contract_id_foreign FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
