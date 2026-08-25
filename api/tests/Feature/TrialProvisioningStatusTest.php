<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProvisionDemoTenantJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class TrialProvisioningStatusTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // La table publique trial_provisionings n'existe que via la migration
        // 2026_08_15_000001 — RefreshTenantDatabase exécute les migrations.
    }

    public function test_guided_trial_signup_returns_provisioning_token(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/trial/signup', [
            'email' => 'prospect@demo.com',
            'company' => 'Demo Prospect',
            'country' => 'DZ',
            'requestedWorkflow' => 'guided_trial',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'provisioning_sandbox')
            ->assertJsonStructure([
                'success',
                'data' => ['email', 'status', 'provisioning_token'],
            ]);

        $token = $response->json('data.provisioning_token');
        $this->assertSame(64, strlen($token));

        $this->assertDatabaseHas('trial_provisionings', [
            'email' => 'prospect@demo.com',
            'provisioning_token' => $token,
            'status' => 'pending',
        ]);

        Queue::assertPushed(ProvisionDemoTenantJob::class);
    }

    public function test_status_returns_pending_before_provisioning(): void
    {
        DB::table('trial_provisionings')->insert([
            'email' => 'prospect@demo.com',
            'provisioning_token' => str_repeat('a', 64),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/v1/trial/status?token='.str_repeat('a', 64))
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonMissingPath('data.login_url');
    }

    public function test_status_returns_ready_with_login_url(): void
    {
        // Valeur FIXE : la version initiale comparait deux now() (insertion
        // vs assertion) — course temporelle microseconde (timestampTz) →
        // échec intermittent ~2/3 des runs. Déterministe désormais.
        $provisionedAt = \Illuminate\Support\Carbon::parse('2026-01-15T10:00:00+00:00');

        DB::table('trial_provisionings')->insert([
            'email' => 'prospect@demo.com',
            'provisioning_token' => str_repeat('b', 64),
            'status' => 'ready',
            'company_id' => '00000000-0000-0000-0000-000000000001',
            'login_url' => '/auth/login',
            'provisioned_at' => $provisionedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/v1/trial/status?token='.str_repeat('b', 64))
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.login_url', '/auth/login')
            ->assertJsonPath('data.provisioned_at', $provisionedAt->toIso8601String());
    }

    public function test_status_returns_failed_with_generic_message(): void
    {
        // Le défaut app est « en » (.env.example) — fixer la locale FR pour
        // asserter le message générique quelle que soit la config d'env.
        $this->withHeader('Accept-Language', 'fr');

        DB::table('trial_provisionings')->insert([
            'email' => 'prospect@demo.com',
            'provisioning_token' => str_repeat('c', 64),
            'status' => 'failed',
            'error' => 'mailer not configured',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/v1/trial/status?token='.str_repeat('c', 64))
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.message', 'La création de votre espace d\'essai a échoué. Veuillez réessayer ou nous contacter.')
            // L'erreur technique interne ne doit jamais fuiter côté client.
            ->assertJsonMissingPath('data.error');
    }

    public function test_status_rejects_invalid_token(): void
    {
        $this->getJson('/api/v1/trial/status?token='.str_repeat('d', 64))
            ->assertStatus(404)
            ->assertJsonPath('error', 'PROVISIONING_TOKEN_INVALID');
    }

    public function test_status_requires_token(): void
    {
        // #4931 : token absent ou malformé → 404 PROVISIONING_TOKEN_INVALID
        // (jamais 422 : l'endpoint ne doit pas révéler l'existence d'un token,
        // et le contrat d'erreur est unique) — aligné sur
        // test_status_rejects_invalid_token (issue #5201).
        $this->getJson('/api/v1/trial/status')
            ->assertStatus(404)
            ->assertJsonPath('error', 'PROVISIONING_TOKEN_INVALID');
    }
}
