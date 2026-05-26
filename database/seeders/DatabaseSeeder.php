<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call the Building Material Store Seeder for realistic mock data
        $this->call([
            BuildingMaterialStoreSeeder::class,
        ]);
    }
}