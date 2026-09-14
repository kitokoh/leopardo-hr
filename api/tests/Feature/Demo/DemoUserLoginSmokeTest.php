<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use Database\Seeders\SuperAdminSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7402 — l'accès « démo » de la console admin annonçait `password123` alors
 * que `SuperAdminSeeder` seedait un mot de passe **aléatoire** quand
 * `SUPER_ADMIN_PASSWORD` était vide (cas par défaut de `.env.example`).
 *
 * Résultat : tous les profils du panneau « ACCÈS DÉMO » échouaient en
 * `INVALID_CREDENTIALS`, y compris `admin@leopardo-rh.com`.
 *
 * Ce test est le **smoke test manquant** exigé par l'issue : le persona
 * `admin-platform` servi par `GET /demo-users` doit réellement ouvrir une
 * session via `POST /platform/auth/login`.
 */
class DemoUserLoginSmokeTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_demo_seeded_super_admin_uses_the_published_demo_password(): void
    {
        config(['app.demo_mode_enabled' => true]);
        config(['demo.password' => 'password123']);

        $this->seed(SuperAdminSeeder::class);

        $row = DB::table('super_admins')->where('email', 'admin@leopardo-rh.com')->first();

        $this->assertNotNull($row, 'SuperAdminSeeder doit créer admin@leopardo-rh.com.');
        $this->assertTrue(
            Hash::check('password123', (string) $row->password_hash),
            'En mode démo, le compte doit porter le mot de passe publié par /demo-users (#7402).',
        );
    }

    public function test_demo_seeded_super_admin_keeps_a_random_password_when_demo_mode_is_off(): void
    {
        // Fail-closed : hors mode démo, rien ne change (mot de passe aléatoire).
        config(['app.demo_mode_enabled' => false]);

        $this->seed(SuperAdminSeeder::class);

        $row = DB::table('super_admins')->where('email', 'admin@leopardo-rh.com')->first();

        $this->assertNotNull($row);
        $this->assertFalse(
            Hash::check('password123', (string) $row->password_hash),
            'Hors mode démo, le mot de passe ne doit PAS être password123 (#7402).',
        );
    }

    public function test_admin_platform_persona_published_by_demo_users_can_log_in(): void
    {
        config(['app.demo_mode_enabled' => true]);
        config(['demo.password' => 'password123']);

        $this->seed(SuperAdminSeeder::class);

        $response = $this->getJson('/api/v1/demo-users')->assertOk();

        $superAdmin = $response->json('data.super_admin');
        $this->assertIsArray($superAdmin);
        $this->assertSame('admin-platform', $superAdmin['surface']);

        /** @var string $email */
        $email = (string) ($superAdmin['email'] ?? '');
        /** @var string $password */
        $password = (string) ($superAdmin['password'] ?? '');

        $this->assertNotSame('', $password, 'Le persona super-admin doit publier son mot de passe (mode démo).');

        // Le contrat annoncé par l'UI doit être vrai : le mot de passe publié
        // ouvre réellement une session plateforme.
        $login = $this->postJson('/api/v1/platform/auth/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => 'Demo login smoke test',
        ]);

        $login->assertOk();
        $this->assertNotEmpty($login->json('token'));
    }

    public function test_company_personas_are_not_admin_platform_surface(): void
    {
        // #7402 (2e défaut) : le panneau démo de l'admin rendait TOUS les
        // personas, y compris les profils `web-manager` — structurellement
        // inconnexibles depuis `/platform/auth/login` (guard super-admin).
        // L'API qualifie la surface pour que l'UI filtre (fait côté
        // front/admin-dashboard/src/views/auth/LoginView.vue).
        config(['app.demo_mode_enabled' => true]);

        $response = $this->getJson('/api/v1/demo-users')->assertOk();

        $companies = $response->json('data.companies');
        $this->assertIsArray($companies);

        $surfaces = [];
        foreach ($companies as $company) {
            if (! is_array($company) || ! is_array($company['users'] ?? null)) {
                continue;
            }
            foreach ($company['users'] as $user) {
                if (is_array($user) && isset($user['surface'])) {
                    $surfaces[(string) $user['surface']] = true;
                }
            }
        }

        $this->assertArrayHasKey('web-manager', $surfaces);
        $this->assertArrayNotHasKey('admin-platform', $surfaces);
    }
}
