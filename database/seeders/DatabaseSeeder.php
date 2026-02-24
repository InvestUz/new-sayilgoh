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
        // Use real data from Дўконлар тўғрисида маълумотлар.csv
        $this->call([
            DokonlarSeeder::class,      // Import shops/lots/contracts
            SheraliFactSeeder::class,   // Import payments from sayilgoh_fakt_cv.csv
        ]);
    }
}
