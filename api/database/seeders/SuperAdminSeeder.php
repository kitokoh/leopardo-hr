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

        $email = env('SUPER_ADMIN_EMAIL', 'admin@leopardo-rh.com');
        $passwordFromEnv = env('SUPER_ADMIN_PASSWORD');

        // #7402 : en mode démo, ce compte est annoncé par `GET /api/v1/demo-users`
        // avec le mot de passe de `config/demo.php` (`password123` par défaut).
        // Le seeder doit donc poser EXACTEMENT ce mot de passe, sinon le
        // parcours « Accès démo » de la console admin échoue avec des
        // identifiants pourtant affichés à l'écran — et le vrai mot de passe
        // n'existait que dans la sortie console du seeder (perdue à la
        // fermeture du terminal).
        $demoPassword = $this->demoPassword();
        $password = $passwordFromEnv ?: ($demoPassword ?? ('CHANGER_EN_PROD_'.bin2hex(random_bytes(8))));
        $forceReset = filter_var(env('FORCE_SUPER_ADMIN_PASSWORD_RESET', false), FILTER_VALIDATE_BOOLEAN);

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

            // #7402 : ne JAMAIS réécrire un mot de passe existant en silence.
            // Si le compte est hors du contrat démo (mot de passe aléatoire
            // d'une installation antérieure, ou mot de passe changé par
            // l'opérateur), on le signale : l'écran démo doit cesser de mentir.
            if (! $forceReset && $demoPassword !== null && $passwordFromEnv === null
                && ! Hash::check($demoPassword, (string) $existing->password_hash)) {
                $this->command->warn(sprintf(
                    '⚠️  Super Admin %s hors contrat démo : son mot de passe ne correspond pas à DEMO_PASSWORD / config(demo.password). '
                    .'Le parcours « Accès démo » échouera. Remettre le mot de passe démo : FORCE_SUPER_ADMIN_PASSWORD_RESET=true SUPER_ADMIN_PASSWORD=<demo> php artisan db:seed --class=SuperAdminSeeder',
                    $email,
                ));
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

        if ($demoPassword !== null) {
            // Le mot de passe démo est public (il est servi par /demo-users) :
            // l'afficher ici ne fuite rien et évite le « où est le mot de passe ? ».
            $this->command->warn("   🔑 Mot de passe démo (DEMO_MODE_ENABLED) : {$password}");

            return;
        }

        if (app()->environment('local', 'development')) {
            $this->command->warn("   🔑 Mot de passe temporaire : {$password}");
            $this->command->warn('   🚨 Changer ce mot de passe IMMÉDIATEMENT en production !');
        }
    }

    /**
     * Mot de passe démo imposé par `config/demo.php`, uniquement quand le mode
     * démo est explicitement activé ET hors production (#7402).
     */
    private function demoPassword(): ?string
    {
        if (! config('app.demo_mode_enabled', false)) {
            return null;
        }

        if (app()->environment('production')) {
            return null;
        }

        $password = (string) config('demo.password', '');

        return $password === '' ? null : $password;
    }
}
