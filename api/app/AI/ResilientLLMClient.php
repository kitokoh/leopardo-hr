<?php

declare(strict_types=1);

namespace App\AI;

use App\AI\DTOs\AIResponse;
use App\AI\Privacy\AiCloudPolicy;
use App\AI\Support\LLMCircuitBreaker;
use App\Core\Tenant\TenantManager;
use Illuminate\Support\Facades\Log;

/**
 * BOS-031 (#8221) — décorateur de résilience du client LLM.
 *
 * Chaîne : primaire (`ai.driver`) puis `ai.resilience.fallback_chain`, avec
 *   - **retry** (2 par défaut, backoff exponentiel déterministe) sur les
 *     échecs retryables uniquement (`AIResponse::isRetryable()` : 429/5xx) ;
 *   - **circuit breaker** par provider ({@see LLMCircuitBreaker}) : un
 *     provider ouvert est sauté sans appel ;
 *   - **garde de politique cloud** sur chaque candidat de fallback :
 *     `AiCloudPolicy::cloudAllowed(TenantManager::current())` — tenant de
 *     **session uniquement**, `null` ⇒ refus (fail-closed). Le provider
 *     PRIMAIRE n'est jamais gaté ici : son admission reste portée par les
 *     consommateurs (`Orchestrator`), comportement inchangé ;
 *   - **dégradation explicite** : tous candidats épuisés/sautés ⇒ `AIResponse`
 *     en erreur (jamais de 500 muet, jamais de contenu inventé).
 *
 * Le décorateur n'est branché que si `ai.resilience.enabled` est vrai
 * (`AppServiceProvider`) : flag OFF = client direct, parité stricte.
 */
final class ResilientLLMClient implements LLMClient
{
    /**
     * @param  list<LLMClient>  $fallbacks
     */
    public function __construct(
        private readonly LLMClient $primary,
        private readonly array $fallbacks,
        private readonly LLMCircuitBreaker $breaker,
        private readonly AiCloudPolicy $cloudPolicy,
        private readonly TenantManager $tenants,
    ) {}

    public function provider(): string
    {
        return $this->primary->provider();
    }

    public function chat(array $messages, array $tools = [], ?array $responseFormat = null): AIResponse
    {
        /** @var list<string> $attempted */
        $attempted = [];
        $last = null;

        $response = $this->attemptWithRetries($this->primary, $messages, $tools, $responseFormat, 'primary');

        if (! $response->failed()) {
            return $response;
        }

        $last = $response;
        $attempted[] = $this->primary->provider();

        foreach ($this->fallbacks as $candidate) {
            $provider = $candidate->provider();

            if ($this->blockedByCloudPolicy($provider)) {
                Log::warning('ai.llm.fallback.skipped_cloud_policy', [
                    'provider' => $provider,
                    'primary' => $this->provider(),
                ]);

                continue;
            }

            if ($this->breaker->isOpen($provider)) {
                Log::warning('ai.llm.fallback.skipped_circuit_open', [
                    'provider' => $provider,
                    'primary' => $this->provider(),
                ]);

                continue;
            }

            Log::warning('ai.llm.fallback.engaged', [
                'provider' => $provider,
                'primary' => $this->provider(),
                'attempt' => count($attempted) + 1,
            ]);

            $response = $this->attemptWithRetries($candidate, $messages, $tools, $responseFormat, 'fallback');

            if (! $response->failed()) {
                return $response;
            }

            $last = $response;
            $attempted[] = $provider;
        }

        return new AIResponse(
            content: '',
            error: 'Aucun fournisseur IA disponible pour cette requête (chaîne essayée : '
                .implode(' → ', $attempted)
                .'). Vérifiez la configuration des providers et la politique cloud du tenant.',
            status: $last->status,
            provider: $this->provider(),
        );
    }

    /**
     * Une tentative + ses retries, avec alimentation du circuit breaker.
     *
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @param  array<string, mixed>|null  $responseFormat
     */
    private function attemptWithRetries(
        LLMClient $client,
        array $messages,
        array $tools,
        ?array $responseFormat,
        string $role,
    ): AIResponse {
        $provider = $client->provider();
        $maxRetries = max(0, (int) config('ai.resilience.retry.max_retries', 2));
        $response = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            if ($attempt > 0) {
                $this->sleepBackoff($attempt - 1);

                Log::warning('ai.llm.retry', [
                    'provider' => $provider,
                    'role' => $role,
                    'attempt' => $attempt,
                    'previous_status' => $response->status,
                ]);
            }

            $response = $client->chat($messages, $tools, $responseFormat);

            if (! $response->failed()) {
                $this->breaker->recordSuccess($provider);

                return $response;
            }

            if (! $response->isRetryable()) {
                break;
            }
        }

        /** @var AIResponse $response */
        $this->breaker->recordFailure($provider);

        return $response;
    }

    /**
     * Backoff exponentiel déterministe : `base × 2^n`, plafonné par
     * `max_delay_ms`. 0 ms en configuration de test (aucune attente).
     */
    private function sleepBackoff(int $retryIndex): void
    {
        $base = max(0, (int) config('ai.resilience.retry.base_delay_ms', 200));
        $max = max(0, (int) config('ai.resilience.retry.max_delay_ms', 2000));

        $delayMs = min($base * (2 ** $retryIndex), $max);

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }

    /**
     * Fail-closed : un candidat cloud n'est admissible que si le tenant de
     * session a explicitement `ai_cloud_allowed`. Tenant absent ⇒ refus.
     */
    private function blockedByCloudPolicy(string $provider): bool
    {
        if (! $this->cloudPolicy->isCloudDriver($provider)) {
            return false;
        }

        return ! $this->cloudPolicy->cloudAllowed($this->tenants->current());
    }
}
