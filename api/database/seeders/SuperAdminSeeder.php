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
        DB::statement('SET search_path TO public');

        $email = config('deployment.super_admin.email', 'admin@leopardo-rh.com');
        $passwordFromEnv = config('deployment.super_admin.password');

        // #7402 — en mode démo, le mot de passe seedé DOIT être celui que
        // `GET /api/v1/demo-users` annonce (`config('demo.password')`, défaut
        // `password123`). Sans cela, `/demo-users` publiait un identifiant qui
        // ne correspondait à rien et l'accès « rapide » de l'admin échouait
        // systématiquement. Hors mode démo : mot de passe aléatoire, inchangé.
        // Lecture par `config()` (et non `env()`) pour rester cohérent avec
        // `DemoCompanyOnceSeeder` et rester testable.
        $demoMode = (bool) config('app.demo_mode_enabled', false);
        // #7696 : plus de fallback « password123 » — sans DEMO_PASSWORD le mode
        // démo retombe sur un mot de passe aléatoire (et /demo-users répond 503).
        $demoPassword = config('demo.password');
        $password = $passwordFromEnv
            ?: ($demoMode && is_string($demoPassword)
                ? $demoPassword
                : 'CHANGER_EN_PROD_'.bin2hex(random_bytes(8)));
        // Audit architecture 2026-11-26 : `config()` et non `env()` —
        // `docker-entrypoint.sh` exécute `config:cache` avant `db:seed`, après
        // quoi `env()` ne renvoie plus que ce que le SAPI expose. Une garde
        // de sécurité lue par `env()` n'était donc pas fiable.
        $forceReset = (bool) config('deployment.super_admin.force_password_reset', false);

        $existing = DB::table('super_admins')->where('email', $email)->first();

        if ($existing) {
            if ($forceReset && $passwordFromEnv) {
                DB::table('super_admins')
                    ->where('email', $email)
                    ->update([
                        'password_hash' => Hash::make($passwordFromEnv),
                    ]);
                $this->command->info("✅ Mot de passe Super Admin réinitialisé : {$email}");
                if (app()->environment('local', 'development')) {
                    $this->command->warn("   🔑 Nouveau mot de passe : {$passwordFromEnv}");
                }

                return;
            }

            $this->command->warn("⚠️  Super Admin déjà existant : {$email} — non modifié");

            return;
        }

        DB::table('super_admins')->insert([
            'name' => 'Super Administrateur',
            'email' => $email,
            'password_hash' => Hash::make($password),
            'created_at' => now(),
        ]);

        $this->command->info("✅ Super Admin créé : {$email}");

        if ($demoMode) {
            $this->command->warn('   ⚠️  Mode démo : mot de passe = config(demo.password) — identique à /api/v1/demo-users.');
        }

        if (app()->environment('local', 'development')) {
            $this->command->warn("   🔑 Mot de passe temporaire : {$password}");
            $this->command->warn('   🚨 Changer ce mot de passe IMMÉDIATEMENT en production !');
        }
    }
}
