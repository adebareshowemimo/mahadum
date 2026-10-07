<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DataTopupAdvertSeeder extends Seeder
{
    public function run(): void
    {
        (new AdvertPlacementSeeder)->seedDataTopup();
    }
}
