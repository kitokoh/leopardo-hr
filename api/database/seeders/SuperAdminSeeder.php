<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * SuperAdminSeeder — Crée le premier compte Super Admin
 *
 * ⚠️ SÉCURITÉ : Changer le mot de passe en production immédiatement
 * Le Super Admin utilise un guard Sanctum dédié (super_admin_tokens)
 * distinct des tokens employees (personal_access_tokens)
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement("SET search_path TO public");

        $email = env('SUPER_ADMIN_EMAIL', 'admin@leopardo-rh.com');
        $password = env('SUPER_ADMIN_PASSWORD', 'CHANGER_EN_PROD_' . bin2hex(random_bytes(8)));

        $existing = DB::table('super_admins')->where('email', $email)->first();

        if ($existing) {
            $this->command->warn("⚠️  Super Admin déjà existant : {$email} — non modifié");
            return;
        }

        DB::table('super_admins')->insert([
            'name'         => 'Super Administrateur',
            'email'        => $email,
            'password_hash' => Hash::make($password),
            'created_at'   => now(),
        ]);

        $this->command->info("✅ Super Admin créé : {$email}");

        if (app()->environment('local', 'development')) {
            $this->command->warn("   🔑 Mot de passe temporaire : {$password}");
            $this->command->warn("   🚨 Changer ce mot de passe IMMÉDIATEMENT en production !");
        }
    }
}
