<?php

declare(strict_types=1);

namespace Tests\Feature\SearchIntelligence;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\SearchIntelligence\Domain\Models\Integration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * GSI-8 (#8519) — intégrations GSC/GA4/Google Ads/MS Ads.
 *
 * L'issue exigeait trois garanties, vérifiées ici une par une :
 *  1. le secret n'est JAMAIS en clair en base ni dans une réponse ;
 *  2. l'état après un « test » est HONNÊTE (pas de faux « connecté ») ;
 *  3. la révocation efface réellement les identifiants et laisse une trace.
 */
final class IntegrationTest extends TestCase
{
    use RefreshTenantDatabase;

    private const VALID_GSC = [
        'client_id' => 'gsc-client-id-1234',
        'client_secret' => 'super-secret-value-should-never-leak',
        'refresh_token' => 'refresh-token-should-never-leak',
        'site_url' => 'https://leopardo-rh.com/',
    ];

    private function actingAsPlatform(PlatformRole $role = PlatformRole::Admin): SuperAdmin
    {
        $admin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'platform-'.uniqid().'@leopardo.test',
        ]);

        $admin->forceFill([
            'password_hash' => Hash::make('x'),
            'platform_role' => $role->value,
        ])->save();

        Sanctum::actingAs($admin->refresh(), ['*'], 'super_admin_api');

        return $admin;
    }

    public function test_index_lists_the_four_supported_providers_as_not_connected(): void
    {
        $this->actingAsPlatform();

        $response = $this->getJson('/api/v1/platform/search-intel/integrations');

        $response->assertOk();
        $this->assertSame(
            ['gsc', 'ga4', 'google_ads', 'ms_ads'],
            array_column($response->json('data'), 'provider'),
        );

        foreach ($response->json('data') as $row) {
            $this->assertSame('not_connected', $row['status']);
            $this->assertFalse($row['has_credentials']);
        }
    }

    public function test_secret_is_encrypted_at_rest_and_never_returned(): void
    {
        $this->actingAsPlatform();

        $response = $this->putJson('/api/v1/platform/search-intel/integrations/gsc', [
            'credentials' => self::VALID_GSC,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.provider', 'gsc')
            ->assertJsonPath('data.has_credentials', true);

        // 1. Jamais de secret dans la réponse HTTP.
        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringNotContainsString(self::VALID_GSC['client_secret'], $body);
        $this->assertStringNotContainsString(self::VALID_GSC['refresh_token'], $body);
        $this->assertStringNotContainsString('credentials_encrypted', $body);

        // 2. Jamais de secret en clair en base — la colonne est chiffrée.
        /** @var object|null $row */
        $row = DB::table('integrations')->where('provider', 'gsc')->first();
        $this->assertNotNull($row);
        $this->assertIsString($row->credentials_encrypted);
        $this->assertStringNotContainsString(
            self::VALID_GSC['client_secret'],
            $row->credentials_encrypted,
        );

        // 3. Mais le secret est bien récupérable applicativement (chiffrement
        // réversible : un connecteur doit pouvoir s'authentifier).
        /** @var Integration $integration */
        $integration = Integration::query()->where('provider', 'gsc')->firstOrFail();
        $this->assertStringContainsString(
            self::VALID_GSC['refresh_token'],
            (string) $integration->credentials_encrypted,
        );

        // 4. La sérialisation JSON du modèle ne fuit pas non plus.
        $this->assertStringNotContainsString(
            self::VALID_GSC['client_secret'],
            json_encode($integration->toArray()) ?: '',
        );
    }

    public function test_configuring_does_not_claim_the_connector_is_connected(): void
    {
        $this->actingAsPlatform();

        $response = $this->putJson('/api/v1/platform/search-intel/integrations/ga4', [
            'credentials' => [
                'client_id' => 'ga4-client',
                'client_secret' => 'ga4-secret',
                'refresh_token' => 'ga4-refresh',
                'property_id' => '123456789',
            ],
        ]);

        // Avoir stocké des identifiants ne prouve AUCUNE autorisation.
        $response->assertOk()->assertJsonPath('data.status', 'not_connected');
    }

    public function test_test_endpoint_stays_honest_and_never_fakes_connected(): void
    {
        $this->actingAsPlatform();

        $this->putJson('/api/v1/platform/search-intel/integrations/gsc', [
            'credentials' => self::VALID_GSC,
        ])->assertOk();

        $response = $this->postJson('/api/v1/platform/search-intel/integrations/gsc/test');

        $response->assertOk()
            // État réel : toujours pas connecté, aucun appel fournisseur n'a eu
            // lieu — l'issue interdit explicitement de simuler.
            ->assertJsonPath('result.status', 'not_connected')
            ->assertJsonPath('result.provider_authorization_verified', false);

        $this->assertNotEmpty($response->json('result.message'));

        /** @var Integration $integration */
        $integration = Integration::query()->where('provider', 'gsc')->firstOrFail();
        $this->assertNotSame('connected', $integration->status->value);
    }

    public function test_test_without_credentials_reports_it_instead_of_pretending(): void
    {
        $this->actingAsPlatform();

        $response = $this->postJson('/api/v1/platform/search-intel/integrations/ms_ads/test');

        $response->assertOk()
            ->assertJsonPath('result.status', 'not_connected')
            ->assertJsonPath('result.provider_authorization_verified', false);

        $this->assertStringContainsString(
            'Aucun identifiant',
            (string) $response->json('result.message'),
        );
    }

    public function test_revocation_erases_the_secret_and_audits_it(): void
    {
        $admin = $this->actingAsPlatform();

        $this->putJson('/api/v1/platform/search-intel/integrations/gsc', [
            'credentials' => self::VALID_GSC,
        ])->assertOk();

        $response = $this->deleteJson('/api/v1/platform/search-intel/integrations/gsc');

        $response->assertOk()
            ->assertJsonPath('data.has_credentials', false)
            ->assertJsonPath('data.status', 'not_connected');

        // Les identifiants sont EFFACÉS, pas seulement marqués.
        /** @var Integration $integration */
        $integration = Integration::query()->where('provider', 'gsc')->firstOrFail();
        $this->assertNull($integration->getRawOriginal('credentials_encrypted'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'search_intel.integration.revoked',
            'module' => 'search_intel',
        ]);

        /** @var AuditLog|null $log */
        $log = AuditLog::query()
            ->where('action', 'search_intel.integration.revoked')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->metadata['actor_super_admin_id'] ?? null);
        // L'audit ne contient jamais le secret.
        $this->assertStringNotContainsString(
            self::VALID_GSC['client_secret'],
            json_encode($log->toArray()) ?: '',
        );
    }

    public function test_unknown_credential_key_is_rejected(): void
    {
        $this->actingAsPlatform();

        // Un champ mal nommé serait persisté puis ignoré à la synchro : mieux
        // vaut un 422 explicite.
        $this->putJson('/api/v1/platform/search-intel/integrations/gsc', [
            'credentials' => ['apiKey' => 'typo-should-be-rejected'],
        ])->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_CREDENTIALS');
    }

    public function test_unsupported_provider_is_not_found(): void
    {
        $this->actingAsPlatform();

        $this->putJson('/api/v1/platform/search-intel/integrations/tiktok', [
            'credentials' => ['client_id' => 'x'],
        ])->assertStatus(404);
    }

    public function test_read_only_platform_role_cannot_mutate_integrations(): void
    {
        // `ops` porte `search_intel.view` mais PAS `search_intel.manage`
        // (cf. PlatformRole::permissions()) : les identifiants d'un connecteur
        // sont des secrets, la mutation doit rester réservée. Le rôle
        // `marketing` porte volontairement les deux (prestataire SEO) — ce
        // n'est donc pas le bon rôle pour ce test.
        $this->actingAsPlatform(PlatformRole::Ops);

        $this->getJson('/api/v1/platform/search-intel/integrations')->assertOk();

        $this->putJson('/api/v1/platform/search-intel/integrations/gsc', [
            'credentials' => self::VALID_GSC,
        ])->assertStatus(403);
    }

    public function test_reconfiguring_replaces_credentials_and_audits_the_change(): void
    {
        $this->actingAsPlatform();

        $this->putJson('/api/v1/platform/search-intel/integrations/gsc', [
            'credentials' => self::VALID_GSC,
        ])->assertOk();

        $this->putJson('/api/v1/platform/search-intel/integrations/gsc', [
            'credentials' => array_merge(self::VALID_GSC, [
                'refresh_token' => 'rotated-refresh-token',
            ]),
        ])->assertOk();

        /** @var Integration $integration */
        $integration = Integration::query()->where('provider', 'gsc')->firstOrFail();
        $this->assertStringContainsString(
            'rotated-refresh-token',
            (string) $integration->credentials_encrypted,
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'search_intel.integration.reconfigured',
        ]);

        // Un seul connecteur par fournisseur (contrainte d'unicité tenue).
        $this->assertSame(
            1,
            Integration::query()->where('provider', 'gsc')->count(),
        );
    }
}
