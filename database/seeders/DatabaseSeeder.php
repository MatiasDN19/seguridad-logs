<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Admin de prueba: puede ver el panel de seguridad
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'is_admin' => true,
        ]);

        // Usuario común de prueba: puede loguearse pero NO ver el panel (403)
        User::factory()->create([
            'name' => 'Usuario Comun',
            'email' => 'comun@example.com',
        ]);
    }
}
