<?php

declare(strict_types=1);

namespace App\AI\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * BOS-031 (#8221) — circuit breaker par provider LLM.
 *
 * État en Cache (store par défaut ; Redis en production) :
 *   `{failures: int, opened_at: ?int}` par provider.
 *
 * Comportement (spec §5.3) :
 *   - `failure_threshold` (défaut 3) échecs consécutifs → circuit **ouvert**
 *     pendant `cooldown_seconds` (défaut 60 s) : le candidat est sauté sans
 *     appel ;
 *   - cooldown écoulé → **half-open** : exactement une sonde est admise
 *     (revendication atomique `Cache::add`) ; sonde OK → circuit fermé
 *     (compteur 0), sonde KO → ré-ouverture pour un nouveau cooldown ;
 *   - tout succès remet le compteur à 0.
 *
 * **Best-effort assumé** : si le Cache lève une exception (Redis indisponible),
 * le circuit est traité comme FERMÉ — une panne de cache ne doit jamais
 * empêcher un appel LLM. Ce n'est pas une garantie transactionnelle
 * distribuée, seulement une protection de charge.
 */
final class LLMCircuitBreaker
{
    public const PROVIDERS = ['fake', 'groq', 'openai', 'claude'];

    private const KEY_PREFIX = 'ai:llm:circuit:';

    private const PROBE_SUFFIX = ':probe';

    private const STATE_TTL_SECONDS = 86_400;

    /**
     * Le candidat doit-il être sauté (circuit ouvert) ?
     *
     * `false` couvre les deux cas d'admission : circuit fermé, ou half-open
     * dont la sonde unique a été revendiquée par cet appel.
     */
    public function isOpen(string $provider): bool
    {
        $state = $this->state($provider);

        if ($state['opened_at'] === null) {
            return false;
        }

        $cooldown = $this->cooldownSeconds();

        if ((int) now()->timestamp - (int) $state['opened_at'] < $cooldown) {
            return true;
        }

        // Half-open : une seule sonde doit passer.
        return ! $this->claimProbe($provider);
    }

    public function recordSuccess(string $provider): void
    {
        $this->forget($provider);
    }

    public function recordFailure(string $provider): void
    {
        $state = $this->state($provider);
        $failures = (int) $state['failures'] + 1;
        $openedAt = $failures >= $this->failureThreshold() ? (int) now()->timestamp : null;

        try {
            Cache::put($this->key($provider), ['failures' => $failures, 'opened_at' => $openedAt], self::STATE_TTL_SECONDS);
        } catch (\Throwable $e) {
            Log::warning('ai.llm.circuit_breaker.cache_unavailable', [
                'operation' => 'record_failure',
                'provider' => $provider,
                'exception' => $e->getMessage(),
            ]);
        }

        if ($openedAt !== null) {
            Log::warning('ai.llm.circuit_breaker.opened', [
                'provider' => $provider,
                'failures' => $failures,
                'cooldown_seconds' => $this->cooldownSeconds(),
            ]);
        }

        // La sonde éventuelle est libérée : le prochain appel après cooldown
        // pourra re-tester le provider.
        $this->releaseProbe($provider);
    }

    /**
     * Nombre d'échecs consécutifs (observabilité / tests).
     */
    public function failures(string $provider): int
    {
        return $this->state($provider)['failures'];
    }

    public function isCircuitOpenNow(string $provider): bool
    {
        return $this->state($provider)['opened_at'] !== null;
    }

    /**
     * @return array{failures: int, opened_at: int|null}
     */
    private function state(string $provider): array
    {
        try {
            $raw = Cache::get($this->key($provider));
        } catch (\Throwable $e) {
            Log::warning('ai.llm.circuit_breaker.cache_unavailable', [
                'operation' => 'read',
                'provider' => $provider,
                'exception' => $e->getMessage(),
            ]);

            return ['failures' => 0, 'opened_at' => null];
        }

        if (! is_array($raw)) {
            return ['failures' => 0, 'opened_at' => null];
        }

        $failures = $raw['failures'] ?? 0;
        $openedAt = $raw['opened_at'] ?? null;

        return [
            'failures' => is_int($failures) ? $failures : 0,
            'opened_at' => is_int($openedAt) ? $openedAt : null,
        ];
    }

    /**
     * Revendique la sonde half-open (atomique). `true` = cet appel est la sonde.
     */
    private function claimProbe(string $provider): bool
    {
        try {
            $claimed = Cache::add($this->key($provider).self::PROBE_SUFFIX, now()->timestamp, $this->cooldownSeconds());

            if ($claimed) {
                Log::warning('ai.llm.circuit_breaker.half_open_probe', ['provider' => $provider]);
            }

            return (bool) $claimed;
        } catch (\Throwable $e) {
            Log::warning('ai.llm.circuit_breaker.cache_unavailable', [
                'operation' => 'claim_probe',
                'provider' => $provider,
                'exception' => $e->getMessage(),
            ]);

            // Best-effort : cache indisponible → on laisse passer l'appel.
            return true;
        }
    }

    private function releaseProbe(string $provider): void
    {
        try {
            Cache::forget($this->key($provider).self::PROBE_SUFFIX);
        } catch (\Throwable $e) {
            Log::warning('ai.llm.circuit_breaker.cache_unavailable', [
                'operation' => 'release_probe',
                'provider' => $provider,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function forget(string $provider): void
    {
        try {
            Cache::forget($this->key($provider));
            Cache::forget($this->key($provider).self::PROBE_SUFFIX);
        } catch (\Throwable $e) {
            Log::warning('ai.llm.circuit_breaker.cache_unavailable', [
                'operation' => 'forget',
                'provider' => $provider,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function key(string $provider): string
    {
        return self::KEY_PREFIX.$provider;
    }

    private function failureThreshold(): int
    {
        return max(1, (int) config('ai.resilience.circuit_breaker.failure_threshold', 3));
    }

    private function cooldownSeconds(): int
    {
        return max(1, (int) config('ai.resilience.circuit_breaker.cooldown_seconds', 60));
    }
}
