<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Shops are added in the panel (or with shop:create),
     * because their keys must be real KadiApi keys.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        User::factory()->admin()->create([
            'name' => 'Test Admin',
            'email' => 'test@example.com',
        ]);
    }
}
