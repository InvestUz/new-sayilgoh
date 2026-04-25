<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payment_schedules')->delete();

        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::statement('ALTER TABLE payment_schedules DROP FOREIGN KEY payment_schedules_contract_id_foreign');
            DB::statement('ALTER TABLE payment_schedules DROP INDEX payment_schedules_contract_id_yil_oy_unique');
            DB::statement('ALTER TABLE payment_schedules ADD UNIQUE INDEX payment_schedules_contract_oy_raqami_unique (contract_id, oy_raqami)');
            DB::statement('ALTER TABLE payment_schedules ADD CONSTRAINT payment_schedules_contract_id_foreign FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE');
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
        // SQLite (test) muhitida bu migratsiya kerakmas — schema toza
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::statement('ALTER TABLE payment_schedules DROP FOREIGN KEY payment_schedules_contract_id_foreign');
            DB::statement('ALTER TABLE payment_schedules DROP INDEX payment_schedules_contract_oy_raqami_unique');
            DB::statement('ALTER TABLE payment_schedules ADD UNIQUE INDEX payment_schedules_contract_id_yil_oy_unique (contract_id, yil, oy)');
            DB::statement('ALTER TABLE payment_schedules ADD CONSTRAINT payment_schedules_contract_id_foreign FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE');
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
};
