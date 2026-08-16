<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use Database\Seeders\DemoCompanyOnceSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #2646 / #3775 — le parcours super-admin démo doit fonctionner en mode démo
 * sans aucune variable d'environnement SUPER_ADMIN_EMAIL.
 *
 * Régression : config/demo.php fixait `super_admin_email` à `admin@example.com`
 * alors que SuperAdminSeeder crée `admin@leopardo-rh.com` par défaut. Le sync
 * du mot de passe démo (DemoCompanyOnceSeeder::syncDemoSuperAdmin) ciblait donc
 * un compte inexistant → no-op silencieux → INVALID_CREDENTIALS pour
 * admin@leopardo-rh.com / password123 en production.
 */
class DemoSuperAdminSyncTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_demo_super_admin_password_is_synced_to_the_default_email(): void
    {
        // Mode démo explicitement activé (comme en prod avec DEMO_MODE_ENABLED=true).
        config(['app.demo_mode_enabled' => true]);

        // Aucune variable SUPER_ADMIN_EMAIL : le seeder et la config doivent
        // converger sur le MÊME défaut (admin@leopardo-rh.com).
        putenv('SUPER_ADMIN_EMAIL');
        config(['demo.super_admin_email' => env('SUPER_ADMIN_EMAIL', 'admin@leopardo-rh.com')]);

        $this->seed(SuperAdminSeeder::class);

        $this->assertDatabaseHas('super_admins', ['email' => 'admin@leopardo-rh.com']);

        // DISABLE_DEMO_SEEDING=true : seul syncDemoSuperAdmin() s'exécute
        // (pas de création des compagnies démo — inutile ici).
        putenv('DISABLE_DEMO_SEEDING=true');
        config(['demo.password' => 'password123']);

        (new DemoCompanyOnceSeeder())->run();

        $row = DB::table('super_admins')->where('email', 'admin@leopardo-rh.com')->first();
        $this->assertNotNull($row);
        $this->assertTrue(
            Hash::check('password123', (string) $row->password_hash),
            'Le mot de passe démo doit être synchronisé sur admin@leopardo-rh.com.',
        );
    }

    public function test_demo_sync_targets_a_custom_super_admin_email_when_configured(): void
    {
        config(['app.demo_mode_enabled' => true]);
        config(['demo.super_admin_email' => 'custom-admin@leopardo-rh.com']);

        $this->seed(SuperAdminSeeder::class);

        // Crée le compte cible manuellement (comme le ferait SuperAdminSeeder
        // si SUPER_ADMIN_EMAIL était positionné à cette valeur).
        DB::table('super_admins')->updateOrInsert(
            ['email' => 'custom-admin@leopardo-rh.com'],
            ['name' => 'Custom Admin', 'password_hash' => Hash::make('whatever'), 'created_at' => now()],
        );

        putenv('DISABLE_DEMO_SEEDING=true');
        (new DemoCompanyOnceSeeder())->run();

        $row = DB::table('super_admins')->where('email', 'custom-admin@leopardo-rh.com')->first();
        $this->assertTrue(Hash::check('password123', (string) $row->password_hash));
    }

    public function test_demo_sync_does_not_touch_super_admin_when_demo_mode_is_off(): void
    {
        config(['app.demo_mode_enabled' => false]);
        config(['demo.super_admin_email' => 'admin@leopardo-rh.com']);

        $this->seed(SuperAdminSeeder::class);

        putenv('DISABLE_DEMO_SEEDING=true');
        (new DemoCompanyOnceSeeder())->run();

        $row = DB::table('super_admins')->where('email', 'admin@leopardo-rh.com')->first();
        $this->assertFalse(Hash::check('password123', (string) $row->password_hash));
    }
}
