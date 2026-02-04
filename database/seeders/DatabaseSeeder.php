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
        // Use real data from dataset.csv
        $this->call([
            DatasetSeeder::class,
            SheraliFactSeeder::class,
        ]);
    }
}
