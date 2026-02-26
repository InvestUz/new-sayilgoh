<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Import ONLY real data from CSV files
        $this->call([
            DokonlarSeeder::class,      // 1. Import lots/contracts from Дўконлар тўғрисида маълумотлар.csv
            SheraliFactSeeder::class,   // 2. Import payments from sayilgoh_fakt_cv.csv (applies to schedules via FIFO)
        ]);
    }
}
