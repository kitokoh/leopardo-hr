<?php

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    /**
     * #8125 — la matrice `checks` est réservée aux super admins plateforme.
     *
     * Ces tests datent d'avant #8125 : ils appelaient `/health` sans
     * authentification et attendaient `checks`, ce que le contrôleur ne renvoie
     * plus volontairement (la matrice cartographierait la stack pour un
     * attaquant). Le comportement minimal non authentifié est couvert par
     * `ProductionDeliveryGuardTest::test_health_public_response_is_minimal`.
     */
    private function actingAsSuperAdmin(): void
    {
        // `super_admins` vit dans le schéma partagé `public`, que
        // `RefreshTenantDatabase` ne réinitialise pas : un e-mail fixe heurte
        // `super_admins_email_unique` dès le deuxième test du run.
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin-health-'.Str::random(12).'@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');
    }

    public function test_health_returns_ok_with_checks_matrix(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'version',
            'checks' => [
                'database' => ['ok'],
                'redis',
                'storage' => ['ok'],
                'queue',
                'memory',
                'web' => ['ok', 'driver', 'app_key_set'],
            ],
            'timestamp',
        ]);

        $response->assertJson([
            'status' => 'ok',
            'checks' => [
                'database' => ['ok' => true],
                'storage' => ['ok' => true],
                'web' => ['ok' => true, 'app_key_set' => true],
            ],
        ]);
    }

    public function test_unversioned_health_alias_serves_the_same_probe(): void
    {
        // #7666 : les sondes externes essaient d'abord `/api/health` par
        // convention (vérifié 404 en prod le 2026-09-19 — elles concluaient
        // « API morte » alors qu'elle était up). L'alias non versionné doit
        // servir la MÊME sonde que la route canonique `/api/v1/health`.
        $this->actingAsSuperAdmin();

        $response = $this->getJson('/api/health');

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);
        $response->assertJsonStructure(['status', 'version', 'checks', 'timestamp']);
    }

    public function test_health_web_check_flags_missing_app_key(): void
    {
        // #6957 : une APP_KEY absente fait 500 sur toutes les routes web
        // (EncryptCookies/StartSession) sans affecter l'API — le check `web`
        // doit le signaler pour que l'observabilité alerte au lieu de subir
        // des 500 muets.
        $originalKey = config('app.key');
        config(['app.key' => null]);

        try {
            $this->actingAsSuperAdmin();

            $response = $this->getJson('/api/v1/health');

            $response->assertOk();
            $response->assertJsonPath('checks.web.ok', false);
            $response->assertJsonPath('checks.web.app_key_set', false);
            $response->assertJsonPath('checks.web.reason', 'app_key_missing');
        } finally {
            config(['app.key' => $originalKey]);
        }
    }
}
