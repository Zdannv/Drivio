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
        // Admin user
        User::factory()->create([
            'name'     => 'Admin KNDI',
            'email'    => 'admin@kyodo-i.com',
            'role'     => 'admin',
            'avatar'   => '/avatars/1.png',
            'password' => Hash::make('password'),
        ]);

        // Driver users
        User::factory()->create([
            'name'     => 'Farkhan',
            'email'    => 'farkhan@kyodo-i.com',
            'role'     => 'driver',
            'avatar'   => '/avatars/1.png',
            'password' => Hash::make('password'),
        ]);

        User::factory()->create([
            'name'     => 'Wawan',
            'email'    => 'wawan@kyodo-i.com',
            'role'     => 'driver',
            'avatar'   => '/avatars/1.png',
            'password' => Hash::make('password'),
        ]);

        User::factory()->create([
            'name'     => 'Trisno',
            'email'    => 'trisno@kyodo-i.com',
            'role'     => 'driver',
            'avatar'   => '/avatars/1.png',
            'password' => Hash::make('password'),
        ]);

        User::factory()->create([
            'name'     => 'Aries',
            'email'    => 'aries@kyodo-i.com',
            'role'     => 'driver',
            'avatar'   => '/avatars/1.png',
            'password' => Hash::make('password'),
        ]);
    }
}