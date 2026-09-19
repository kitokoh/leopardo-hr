<?php

declare(strict_types=1);

namespace Tests\Feature\Communication;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Communication\Domain\Models\CommunicationIntegration;
use App\Modules\Communication\Domain\Support\CommunicationFeatures;
use App\Modules\Communication\Infrastructure\Services\GoogleGmailOAuthService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-29 COMMUNICATION (R1, #7686) — connexion Google par utilisateur :
 * flow OAuth serveur (state anti-CSRF a usage unique), tokens chiffres au
 * repos (jamais en clair en base ni dans les payloads API), refresh
 * transparent, revocation propre (Google + purge), RBAC (boite personnelle,
 * revocation principal/rh) et isolation tenant.
 *
 * Tous les appels Google sont mockes via Http::fake — AUCUN appel reseau
 * reel (exigence issue).
 */
class CommunicationIntegrationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.google.client_id', 'test-client-id.apps.googleusercontent.com');
        config()->set('services.google.client_secret', 'test-client-secret');
        config()->set(
            'services.google.communication_redirect',
            'https://api.leopardo.test/api/v1/communication/integrations/google/callback'
        );

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $company->setFeature(CommunicationFeatures::COMMUNICATION, true);
        $company->save();
        $this->company = $company;

        $this->employee = $this->makeEmployee($this->company);
    }

    private function makeEmployee(Company $company, string $role = 'employee', ?string $managerRole = null): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'role' => $role,
            'manager_role' => $managerRole,
        ]);

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeIntegration(Employee $employee, array $attributes = []): CommunicationIntegration
    {
        $integration = new CommunicationIntegration;

        $integration->forceFill(array_merge([
            'company_id' => (string) $employee->company_id,
            'employee_id' => $employee->id,
            'provider' => CommunicationIntegration::PROVIDER_GOOGLE,
            'email' => 'mailbox-'.$employee->id.'@gmail.com',
            'scopes' => GoogleGmailOAuthService::DEFAULT_SCOPES,
            'access_token' => 'plain-access-token-'.$employee->id,
            'refresh_token' => 'plain-refresh-token-'.$employee->id,
            'expires_at' => now()->addHour(),
            'status' => CommunicationIntegration::STATUS_ACTIVE,
            'connected_at' => now(),
        ], $attributes));

        $integration->save();

        return $integration;
    }

    // ── Connexion (state anti-CSRF + URL de consentement) ────────────────

    public function test_connect_returns_google_authorization_url_with_offline_consent_and_state(): void
    {
        Sanctum::actingAs($this->employee);

        $response = $this->postJson('/api/v1/communication/integrations/google')
            ->assertStatus(200);

        $url = $response->json('data.authorization_url');
        $this->assertIsString($url);
        $this->assertStringStartsWith(GoogleGmailOAuthService::AUTHORIZATION_ENDPOINT, $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('test-client-id.apps.googleusercontent.com', $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        // access_type=offline + prompt=consent : refresh_token garanti.
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('consent', $query['prompt']);
        // Scopes minimaux : identifier la boite + lecture Gmail, rien d'autre.
        $this->assertSame('openid email https://www.googleapis.com/auth/gmail.readonly', $query['scope']);

        // Le state anti-CSRF porte l'identite du demandeur, a usage unique.
        $state = $query['state'];
        $this->assertIsString($state);
        $this->assertSame(40, mb_strlen($state));
        $this->assertSame([
            'employee_id' => $this->employee->id,
            'company_id' => (string) $this->company->id,
        ], Cache::get('communication:oauth:state:'.$state));
    }

    public function test_connect_returns_503_when_google_oauth_is_not_configured(): void
    {
        config()->set('services.google.client_secret', null);

        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/communication/integrations/google')
            ->assertStatus(503)
            ->assertJsonPath('error', 'GOOGLE_OAUTH_UNAVAILABLE');
    }

    public function test_connect_is_gated_by_the_module_feature_flag(): void
    {
        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        Sanctum::actingAs($this->makeEmployee($other));

        $this->postJson('/api/v1/communication/integrations/google')
            ->assertStatus(403)
            ->assertJsonPath('error', 'FEATURE_NOT_ENABLED');
    }

    // ── Callback (echange du code, stockage chiffre) ─────────────────────

    public function test_callback_rejects_unknown_or_replayed_state(): void
    {
        Http::fake();

        $this->getJson('/api/v1/communication/integrations/google/callback?state=unknown&code=abc')
            ->assertStatus(400)
            ->assertJsonPath('error', 'OAUTH_STATE_INVALID');

        // Aucun echange tente sans state valide (anti-CSRF).
        Http::assertNothingSent();
    }

    public function test_callback_exchanges_code_and_stores_tokens_encrypted(): void
    {
        Http::fake([
            GoogleGmailOAuthService::TOKEN_ENDPOINT => Http::response([
                'access_token' => 'google-access-token',
                'refresh_token' => 'google-refresh-token',
                'expires_in' => 3600,
                'scope' => 'openid email https://www.googleapis.com/auth/gmail.readonly',
                'token_type' => 'Bearer',
            ]),
            GoogleGmailOAuthService::USERINFO_ENDPOINT => Http::response([
                'email' => 'user@gmail.com',
                'email_verified' => true,
            ]),
        ]);

        $state = $this->issueState($this->employee);

        $this->getJson('/api/v1/communication/integrations/google/callback?state='.$state.'&code=auth-code')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.provider', 'google')
            ->assertJsonPath('data.email', 'user@gmail.com');

        /** @var CommunicationIntegration $integration */
        $integration = CommunicationIntegration::query()
            ->withoutGlobalScope('company')
            ->where('employee_id', $this->employee->id)
            ->firstOrFail();

        $this->assertSame((string) $this->company->id, (string) $integration->company_id);
        $this->assertSame(CommunicationIntegration::STATUS_ACTIVE, $integration->status);
        // Les casts dechiffrent a la lecture...
        $this->assertSame('google-access-token', $integration->access_token);
        $this->assertSame('google-refresh-token', $integration->refresh_token);
        $this->assertNotNull($integration->expires_at);

        // ... mais AU REPOS, la colonne ne contient JAMAIS le token en clair
        // (critere d'acceptation : aucun token en clair en base).
        $raw = DB::table('communication_integrations')
            ->where('id', $integration->id)
            ->first();
        $this->assertNotNull($raw);
        $this->assertNotSame('google-access-token', $raw->access_token);
        $this->assertNotSame('google-refresh-token', $raw->refresh_token);
        $this->assertStringNotContainsString('google-access-token', (string) $raw->access_token);
        $this->assertStringNotContainsString('google-refresh-token', (string) $raw->refresh_token);

        // Le state est a usage unique : un rejeu du callback echoue.
        $this->getJson('/api/v1/communication/integrations/google/callback?state='.$state.'&code=auth-code')
            ->assertStatus(400)
            ->assertJsonPath('error', 'OAUTH_STATE_INVALID');
    }

    public function test_callback_denies_consent_error_without_storing_anything(): void
    {
        Http::fake();

        $state = $this->issueState($this->employee);

        $this->getJson('/api/v1/communication/integrations/google/callback?state='.$state.'&error=access_denied')
            ->assertStatus(400)
            ->assertJsonPath('error', 'OAUTH_CONSENT_DENIED');

        $this->assertSame(0, CommunicationIntegration::query()->withoutGlobalScope('company')->count());
        Http::assertNothingSent();
    }

    public function test_callback_fails_closed_when_module_was_disabled_after_state_was_issued(): void
    {
        Http::fake();

        $state = $this->issueState($this->employee);

        $this->company->setFeature(CommunicationFeatures::COMMUNICATION, false);
        $this->company->save();

        $this->getJson('/api/v1/communication/integrations/google/callback?state='.$state.'&code=auth-code')
            ->assertStatus(403)
            ->assertJsonPath('error', 'FEATURE_NOT_ENABLED');

        Http::assertNothingSent();
    }

    public function test_callback_returns_502_when_google_rejects_the_code(): void
    {
        Http::fake([
            GoogleGmailOAuthService::TOKEN_ENDPOINT => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $state = $this->issueState($this->employee);

        $this->getJson('/api/v1/communication/integrations/google/callback?state='.$state.'&code=bad-code')
            ->assertStatus(502)
            ->assertJsonPath('error', 'OAUTH_EXCHANGE_FAILED');

        $this->assertSame(0, CommunicationIntegration::query()->withoutGlobalScope('company')->count());
    }

    // ── Lecture (boite personnelle, jamais de token expose) ──────────────

    public function test_index_lists_only_the_callers_integrations_and_never_exposes_tokens(): void
    {
        $integration = $this->makeIntegration($this->employee);
        $colleague = $this->makeEmployee($this->company);
        $this->makeIntegration($colleague);

        Sanctum::actingAs($this->employee);

        $response = $this->getJson('/api/v1/communication/integrations')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $integration->id)
            ->assertJsonPath('data.0.provider', 'google')
            ->assertJsonPath('data.0.status', 'active');

        // Aucun token, meme chiffre, ne sort de l'API (minimisation).
        $payload = (string) $response->getContent();
        $this->assertStringNotContainsString('access_token', $payload);
        $this->assertStringNotContainsString('refresh_token', $payload);
        $this->assertStringNotContainsString('plain-access-token', $payload);
    }

    // ── Revocation ────────────────────────────────────────────────────────

    public function test_owner_revocation_calls_google_and_purges_tokens(): void
    {
        Http::fake([
            GoogleGmailOAuthService::REVOKE_ENDPOINT => Http::response([], 200),
        ]);

        $integration = $this->makeIntegration($this->employee);

        Sanctum::actingAs($this->employee);

        $this->deleteJson('/api/v1/communication/integrations/'.$integration->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'revoked');

        // Revocation cote Google avec le refresh_token (invalide la grappe).
        Http::assertSent(function ($request): bool {
            return $request->url() === GoogleGmailOAuthService::REVOKE_ENDPOINT
                && $request['token'] === 'plain-refresh-token-'.$this->employee->id;
        });

        // Purge locale : plus aucun token en base, statut revoked.
        $raw = DB::table('communication_integrations')->where('id', $integration->id)->first();
        $this->assertNotNull($raw);
        $this->assertNull($raw->access_token);
        $this->assertNull($raw->refresh_token);
        $this->assertSame(CommunicationIntegration::STATUS_REVOKED, $raw->status);
        $this->assertNotNull($raw->revoked_at);
    }

    public function test_revocation_succeeds_even_if_google_already_invalidated_the_token(): void
    {
        Http::fake([
            GoogleGmailOAuthService::REVOKE_ENDPOINT => Http::response(['error' => 'invalid_token'], 400),
        ]);

        $integration = $this->makeIntegration($this->employee);

        Sanctum::actingAs($this->employee);

        // Best effort : un token deja mort cote Google ne bloque pas la purge.
        $this->deleteJson('/api/v1/communication/integrations/'.$integration->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'revoked');

        $raw = DB::table('communication_integrations')->where('id', $integration->id)->first();
        $this->assertNotNull($raw);
        $this->assertNull($raw->refresh_token);
    }

    public function test_a_colleague_cannot_revoke_someone_elses_mailbox(): void
    {
        Http::fake();

        $integration = $this->makeIntegration($this->employee);

        Sanctum::actingAs($this->makeEmployee($this->company));

        $this->deleteJson('/api/v1/communication/integrations/'.$integration->id)
            ->assertStatus(403);

        Http::assertNothingSent();
        $this->assertSame(
            CommunicationIntegration::STATUS_ACTIVE,
            CommunicationIntegration::query()->withoutGlobalScope('company')->findOrFail($integration->id)->status
        );
    }

    public function test_principal_can_revoke_a_collaborators_mailbox(): void
    {
        Http::fake([
            GoogleGmailOAuthService::REVOKE_ENDPOINT => Http::response([], 200),
        ]);

        $integration = $this->makeIntegration($this->employee);

        Sanctum::actingAs($this->makeEmployee($this->company, 'manager', 'principal'));

        // Offboarding / incident : principal (ou rh) revoque la boite d'un
        // collaborateur.
        $this->deleteJson('/api/v1/communication/integrations/'.$integration->id)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'revoked');
    }

    public function test_cross_tenant_revocation_is_a_404(): void
    {
        Http::fake();

        $integration = $this->makeIntegration($this->employee);

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $other->setFeature(CommunicationFeatures::COMMUNICATION, true);
        $other->save();

        Sanctum::actingAs($this->makeEmployee($other, 'manager', 'principal'));

        // Isolation tenant : le scope global company_id rend l'integration
        // d'un autre tenant introuvable (404, jamais 403 — pas d'oracle).
        $this->deleteJson('/api/v1/communication/integrations/'.$integration->id)
            ->assertStatus(404);

        Http::assertNothingSent();
    }

    // ── Refresh transparent ───────────────────────────────────────────────

    public function test_expired_access_token_is_refreshed_transparently(): void
    {
        Http::fake([
            GoogleGmailOAuthService::TOKEN_ENDPOINT => Http::response([
                'access_token' => 'refreshed-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
        ]);

        $integration = $this->makeIntegration($this->employee, [
            'expires_at' => now()->subMinute(),
        ]);

        $service = app(GoogleGmailOAuthService::class);

        $this->assertSame('refreshed-access-token', $service->ensureValidAccessToken($integration));

        $integration->refresh();
        $this->assertSame('refreshed-access-token', $integration->access_token);
        // Le refresh_token d'origine est conserve (Google n'en renvoie pas).
        $this->assertSame('plain-refresh-token-'.$this->employee->id, $integration->refresh_token);
        $this->assertTrue($integration->expires_at?->isFuture() ?? false);

        // La requete de refresh utilise bien le grant refresh_token.
        Http::assertSent(function ($request): bool {
            return $request->url() === GoogleGmailOAuthService::TOKEN_ENDPOINT
                && $request['grant_type'] === 'refresh_token'
                && $request['refresh_token'] === 'plain-refresh-token-'.$this->employee->id;
        });
    }

    public function test_valid_access_token_is_reused_without_calling_google(): void
    {
        Http::fake();

        $integration = $this->makeIntegration($this->employee, [
            'expires_at' => now()->addHour(),
        ]);

        $service = app(GoogleGmailOAuthService::class);

        $this->assertSame(
            'plain-access-token-'.$this->employee->id,
            $service->ensureValidAccessToken($integration)
        );

        Http::assertNothingSent();
    }

    public function test_invalid_grant_marks_the_integration_in_error_and_purges_the_access_token(): void
    {
        Http::fake([
            GoogleGmailOAuthService::TOKEN_ENDPOINT => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $integration = $this->makeIntegration($this->employee, [
            'expires_at' => now()->subMinute(),
        ]);

        $service = app(GoogleGmailOAuthService::class);

        $this->assertNull($service->ensureValidAccessToken($integration));

        $integration->refresh();
        $this->assertSame(CommunicationIntegration::STATUS_ERROR, $integration->status);
        $this->assertNull($integration->access_token);
        $this->assertSame('invalid_grant', $integration->last_error);
    }

    public function test_revoked_integration_never_calls_google_for_a_token(): void
    {
        Http::fake();

        $integration = $this->makeIntegration($this->employee, [
            'status' => CommunicationIntegration::STATUS_REVOKED,
            'access_token' => null,
            'refresh_token' => null,
        ]);

        $service = app(GoogleGmailOAuthService::class);

        $this->assertNull($service->ensureValidAccessToken($integration));
        Http::assertNothingSent();
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Pose un state anti-CSRF comme le ferait POST /integrations/google.
     */
    private function issueState(Employee $employee): string
    {
        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/communication/integrations/google')->assertStatus(200);

        parse_str(
            (string) parse_url((string) $response->json('data.authorization_url'), PHP_URL_QUERY),
            $query
        );

        // Le callback est public : on repart d'un client anonyme.
        app('auth')->forgetGuards();

        $state = $query['state'] ?? null;

        if (! is_string($state)) {
            $this->fail("state manquant dans l'URL d'autorisation Google.");
        }

        return $state;
    }
}
