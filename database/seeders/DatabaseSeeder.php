<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // An admin account so the Filament panel is reachable at all. The
        // password is local-only; spec §9.7 says never show stored passwords.
        // firstOrCreate keeps `db:seed` re-runnable.
        User::firstOrCreate(
            ['email' => 'admin@olivarcalc.test'],
            [
                'name' => 'Administrador',
                'password' => 'password',
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
            ],
        );

        $this->call([
            ParameterSetSeeder::class,
            OilPriceSeeder::class,
            ContentSeeder::class,
        ]);
    }
}
