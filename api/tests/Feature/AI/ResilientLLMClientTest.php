<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\DTOs\AIResponse;
use App\AI\LLMClient;
use App\AI\Privacy\AiCloudPolicy;
use App\AI\Providers\FakeLLMClient;
use App\AI\Providers\GroqClient;
use App\AI\ResilientLLMClient;
use App\AI\Support\LLMCircuitBreaker;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use Illuminate\Support\Carbon;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-031 (#8221) — décorateur de résilience : retry, fallback, circuit
 * breaker, garde de politique cloud par candidat, dégradation explicite,
 * parité du flag OFF et pass-through `response_format`.
 *
 * Chaque comportement est éprouvé sur le décorateur directement, avec des
 * doubles scriptés (aucun appel réseau) : le contrat `LLMClient` est
 * inchangé pour les consommateurs.
 */
class ResilientLLMClientTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Aucune attente de backoff pendant les tests (le backoff est vérifié
        // par son effet sur le nombre de tentatives).
        config([
            'ai.resilience.retry.base_delay_ms' => 0,
            'ai.resilience.retry.max_delay_ms' => 0,
            'ai.resilience.retry.max_retries' => 2,
            'ai.resilience.circuit_breaker.failure_threshold' => 3,
            'ai.resilience.circuit_breaker.cooldown_seconds' => 60,
            'ai.resilience.enabled' => false,
            'ai.resilience.fallback_chain' => [],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Double scripté : consomme une réponse par appel, compte les appels et
     * mémorise les `response_format` reçus.
     *
     * @param  list<AIResponse>  $responses
     */
    private function client(string $provider, array $responses): ScriptedLLMClient
    {
        return new ScriptedLLMClient($provider, $responses);
    }

    private function page(): AIResponse
    {
        return new AIResponse(content: 'ok', model: 'test', provider: 'x');
    }

    /**
     * @param  list<LLMClient>  $fallbacks
     */
    private function decorator(LLMClient $primary, array $fallbacks = []): ResilientLLMClient
    {
        return new ResilientLLMClient(
            $primary,
            $fallbacks,
            app(LLMCircuitBreaker::class),
            app(AiCloudPolicy::class),
            app(TenantManager::class),
        );
    }

    private function failure(int $status, string $message = 'boom'): AIResponse
    {
        return new AIResponse(content: '', error: $message, status: $status, provider: 'x');
    }

    private function providerOf(string $name): LLMClient
    {
        return $name === 'fake' ? new FakeLLMClient : new GroqClient;
    }

    // ── Parité du flag OFF / ON (binding) ───────────────────────────────

    public function test_flag_off_binds_the_direct_client_without_decorator(): void
    {
        config(['ai.driver' => 'fake', 'ai.resilience.enabled' => false]);

        $client = app(LLMClient::class);

        $this->assertInstanceOf(FakeLLMClient::class, $client);
        $this->assertSame(FakeLLMClient::class, $client::class, 'le décorateur ne doit pas être branché quand le flag est OFF');
    }

    public function test_flag_on_wraps_the_driver_in_the_decorator(): void
    {
        config([
            'ai.driver' => 'fake',
            'ai.resilience.enabled' => true,
            'ai.resilience.fallback_chain' => ['groq'],
        ]);

        $client = app(LLMClient::class);

        $this->assertInstanceOf(ResilientLLMClient::class, $client);
        $this->assertSame('fake', $client->provider());
    }

    public function test_unknown_fallback_drivers_are_ignored_without_exception(): void
    {
        config([
            'ai.driver' => 'fake',
            'ai.resilience.enabled' => true,
            'ai.resilience.fallback_chain' => ['groq', 'aws-bedrock', 'groq'],
        ]);

        $client = app(LLMClient::class);

        $this->assertInstanceOf(ResilientLLMClient::class, $client);
        // Le driver inconnu est ignoré (log), la chaîne reste exploitable.
        $this->assertSame('fake', $client->provider());
    }

    // ── Fallback ────────────────────────────────────────────────────────

    public function test_primary_success_never_engages_the_fallback(): void
    {
        $primary = $this->client('fake', [$this->page()]);
        $fallback = $this->client('fake2', [$this->page()]);

        $response = $this->decorator($primary, [$fallback])->chat([['role' => 'user', 'content' => 'salut']]);

        $this->assertFalse($response->failed());
        $this->assertSame(1, $primary->calls);
        $this->assertSame(0, $fallback->calls);
    }

    public function test_fallback_serves_after_primary_failure_and_is_attributed(): void
    {
        $primary = $this->client('fake', [$this->failure(401, 'clé invalide')]);
        $fallback = $this->client('fake2', [new AIResponse(content: 'secours', provider: 'fake2')]);

        $response = $this->decorator($primary, [$fallback])->chat([]);

        $this->assertFalse($response->failed());
        $this->assertSame('secours', $response->content);
        $this->assertSame('fake2', $response->provider);
        $this->assertSame(1, $fallback->calls);
    }

    public function test_non_retryable_error_is_not_retried(): void
    {
        $primary = $this->client('fake', [$this->failure(401), $this->page()]);

        $response = $this->decorator($primary)->chat([]);

        $this->assertTrue($response->failed());
        $this->assertSame(1, $primary->calls, 'un 401 ne doit pas être réessayé');
    }

    public function test_retryable_error_is_retried_twice_before_fallback(): void
    {
        $primary = $this->client('fake', [
            $this->failure(429),
            $this->failure(503),
            $this->page(), // 3e tentative (2 retries) réussit
        ]);

        $response = $this->decorator($primary)->chat([]);

        $this->assertFalse($response->failed());
        $this->assertSame(3, $primary->calls);
    }

    public function test_retryable_error_exhausted_falls_back_then_degrades(): void
    {
        $primary = $this->client('fake', [$this->failure(500), $this->failure(500), $this->failure(500)]);

        $response = $this->decorator($primary)->chat([]);

        $this->assertSame(3, $primary->calls, '2 retries = 3 tentatives');
        $this->assertTrue($response->failed());
        $this->assertStringContainsString('Aucun fournisseur IA disponible', (string) $response->error);
    }

    // ── Circuit breaker ─────────────────────────────────────────────────

    public function test_circuit_opens_after_threshold_and_skips_the_candidate(): void
    {
        $primary = $this->client('fake', [$this->failure(401), $this->failure(401), $this->failure(401), $this->failure(401)]);
        // Échec NON retryable : une seule tentative par appel, donc un
        // échec compté par appel pour le circuit breaker.
        $fallback = $this->client('fake2', [$this->failure(401)]);
        $chain = $this->decorator($primary, [$fallback]);

        // 3 appels : le fallback échoue 3 fois → circuit ouvert.
        for ($i = 0; $i < 3; $i++) {
            $chain->chat([]);
        }

        $this->assertSame(3, $fallback->calls);
        $this->assertTrue(app(LLMCircuitBreaker::class)->isCircuitOpenNow('fake2'));

        // 4e appel : candidat sauté sans appel (fail-fast).
        $chain->chat([]);
        $this->assertSame(3, $fallback->calls, 'circuit ouvert = aucun appel supplémentaire');
    }

    public function test_success_resets_the_failure_counter(): void
    {
        $primary = $this->client('fake', [$this->failure(401)]);
        $fallback = $this->client('fake2', [$this->failure(500), $this->failure(500), new AIResponse(content: 'ok')]);

        $chain = $this->decorator($primary, [$fallback]);
        $chain->chat([]);
        $chain->chat([]);
        $chain->chat([]); // succès → compteur remis à 0

        $this->assertSame(0, app(LLMCircuitBreaker::class)->failures('fake2'));
        $this->assertFalse(app(LLMCircuitBreaker::class)->isCircuitOpenNow('fake2'));
    }

    public function test_half_open_probe_reopens_the_circuit_on_failure(): void
    {
        $primary = $this->client('fake', array_fill(0, 10, $this->failure(401)));
        $fallback = $this->client('fake2', array_fill(0, 10, $this->failure(401)));
        $chain = $this->decorator($primary, [$fallback]);

        for ($i = 0; $i < 3; $i++) {
            $chain->chat([]);
        }

        $this->assertSame(3, $fallback->calls);

        // Cooldown écoulé → half-open : exactement une sonde.
        Carbon::setTestNow(now()->addSeconds(61));
        $chain->chat([]);

        $this->assertSame(4, $fallback->calls, 'une seule sonde half-open');
        $this->assertTrue(app(LLMCircuitBreaker::class)->isCircuitOpenNow('fake2'), 'sonde KO → ré-ouverture');

        $chain->chat([]);
        $this->assertSame(4, $fallback->calls, 'ré-ouvert : plus aucune sonde avant le prochain cooldown');
    }

    public function test_half_open_probe_closes_the_circuit_on_success(): void
    {
        $primary = $this->client('fake', array_fill(0, 10, $this->failure(401)));
        $fallback = $this->client('fake2', [
            $this->failure(401),
            $this->failure(401),
            $this->failure(401),
            new AIResponse(content: 'sonde ok', provider: 'fake2'),
            new AIResponse(content: 'ok ensuite', provider: 'fake2'),
        ]);
        $chain = $this->decorator($primary, [$fallback]);

        for ($i = 0; $i < 3; $i++) {
            $chain->chat([]);
        }

        Carbon::setTestNow(now()->addSeconds(61));
        $this->assertFalse($chain->chat([])->failed());
        $this->assertSame(4, $fallback->calls);
        $this->assertSame(0, app(LLMCircuitBreaker::class)->failures('fake2'));

        // Circuit refermé : le candidat est de nouveau sollicité normalement.
        $this->assertFalse($chain->chat([])->failed());
        $this->assertSame(5, $fallback->calls);
    }

    // ── Politique cloud ─────────────────────────────────────────────────

    public function test_cloud_fallback_is_never_called_without_tenant_flag(): void
    {
        $primary = $this->client('fake', [$this->failure(401)]);
        $cloud = $this->client('groq', [new AIResponse(content: 'jamais', provider: 'groq')]);

        $response = $this->decorator($primary, [$cloud])->chat([]);

        $this->assertTrue($response->failed());
        $this->assertSame(0, $cloud->calls, 'fail-closed : aucun appel cloud sans ai_cloud_allowed');
        $this->assertStringContainsString('Aucun fournisseur IA disponible', (string) $response->error);
    }

    public function test_cloud_fallback_is_served_when_tenant_allows_cloud(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        $company->setFeature('ai_cloud_allowed', true);
        $company->save();
        $this->app->instance('current_company', $company);

        $primary = $this->client('fake', [$this->failure(401)]);
        $cloud = $this->client('groq', [new AIResponse(content: 'cloud', provider: 'groq')]);

        $response = $this->decorator($primary, [$cloud])->chat([]);

        $this->assertFalse($response->failed());
        $this->assertSame('cloud', $response->content);
        $this->assertSame(1, $cloud->calls);
    }

    public function test_non_cloud_fallback_stays_eligible_without_cloud_flag(): void
    {
        $primary = $this->client('groq', [$this->failure(401)]);
        $local = $this->client('fake', [new AIResponse(content: 'local', provider: 'fake')]);

        $response = $this->decorator($primary, [$local])->chat([]);

        $this->assertFalse($response->failed());
        $this->assertSame('local', $response->content);
    }

    // ── Divers ──────────────────────────────────────────────────────────

    public function test_response_format_is_forwarded_to_each_candidate(): void
    {
        $primary = $this->client('fake', [$this->failure(401)]);
        $fallback = $this->client('fake2', [new AIResponse(content: 'ok')]);
        $format = ['type' => 'json_object'];

        $this->decorator($primary, [$fallback])->chat([], [], $format);

        $this->assertSame([$format], $primary->responseFormats);
        $this->assertSame([$format], $fallback->responseFormats);
    }

    public function test_client_factory_maps_known_drivers(): void
    {
        $this->assertInstanceOf(FakeLLMClient::class, $this->providerOf('fake'));
        $this->assertSame('groq', $this->providerOf('groq')->provider());
    }
}

/**
 * Double de test scripté : consomme une réponse par appel, compte les appels
 * et mémorise les `response_format` reçus (propriétés typées → analyse L8).
 */
final class ScriptedLLMClient implements LLMClient
{
    public int $calls = 0;

    /** @var list<array<string, mixed>|null> */
    public array $responseFormats = [];

    /**
     * @param  list<AIResponse>  $responses
     */
    public function __construct(
        private readonly string $name,
        private readonly array $responses,
    ) {}

    public function chat(array $messages, array $tools = [], ?array $responseFormat = null): AIResponse
    {
        $this->responseFormats[] = $responseFormat;

        $response = $this->responses[min($this->calls, count($this->responses) - 1)];
        $this->calls++;

        return $response;
    }

    public function provider(): string
    {
        return $this->name;
    }
}
