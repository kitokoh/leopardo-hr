<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Infrastructure\Connectors;

use App\Modules\SearchIntelligence\Domain\Contracts\IntegrationMetricRow;
use App\Modules\SearchIntelligence\Domain\Exceptions\IntegrationSyncException;
use Illuminate\Support\Facades\Http;

/**
 * GSI-12 (#8523) point 1 — base commune aux quatre connecteurs réels.
 *
 * Ce que cette classe garantit une seule fois pour GSC / GA4 / Google Ads /
 * MS Ads :
 *
 * - **Les jetons ne fuient pas.** Un `refresh_token` est échangé contre un
 *   jeton d'accès à chaque appel ; l'URL d'échange le porte en POST, jamais
 *   en query string, et aucune exception ne recopie la valeur reçue.
 * - **Les erreurs sont typées.** 401/403 → `unauthorized` (non réessayable :
 *   il faut reconnecter), 429 → `quotaExceeded` (réessayable), 5xx et erreurs
 *   réseau → `transport` (réessayable), tout le reste → `malformed`.
 * - **Rien n'est inventé.** Une réponse sans les clés attendues lève
 *   `malformed` ; elle ne devient jamais une liste vide silencieuse.
 */
abstract class AbstractGoogleConnector
{
    /** Délai maximum d'un appel fournisseur (secondes). */
    protected const TIMEOUT_SECONDS = 20;

    public function __construct(
        private readonly ?string $tokenEndpoint = null,
    ) {
    }

    /**
     * Fournisseur servi par ce connecteur (`gsc`, `ga4`, `google_ads`, `ms_ads`).
     */
    abstract public function provider(): string;

    /** Provenance portée par les relevés produits. */
    abstract public function source(): \App\Modules\SearchIntelligence\Domain\Enums\MetricSource;

    /** @param array<string, string> $credentials */
    protected function accessToken(array $credentials): string
    {
        $endpoint = $this->tokenEndpoint ?? 'https://oauth2.googleapis.com/token';

        foreach (['client_id', 'client_secret', 'refresh_token'] as $key) {
            if (($credentials[$key] ?? '') === '') {
                throw IntegrationSyncException::unauthorized($this->provider());
            }
        }

        $response = Http::asForm()
            ->timeout(self::TIMEOUT_SECONDS)
            ->post($endpoint, [
                'grant_type' => 'refresh_token',
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'refresh_token' => $credentials['refresh_token'],
            ]);

        if ($response->status() === 401 || $response->status() === 400) {
            throw IntegrationSyncException::unauthorized($this->provider());
        }

        if ($response->status() === 429) {
            throw IntegrationSyncException::quotaExceeded($this->provider());
        }

        if ($response->serverError()) {
            throw IntegrationSyncException::transport($this->provider(), 'HTTP '.$response->status());
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw IntegrationSyncException::malformed($this->provider(), 'access_token absent de la réponse OAuth');
        }

        return $token;
    }

    /**
     * GET JSON authentifié, avec la même politique d'erreurs que ci-dessus.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function getJson(string $url, string $accessToken, array $query = []): array
    {
        $response = Http::withToken($accessToken)
            ->timeout(self::TIMEOUT_SECONDS)
            ->acceptJson()
            ->get($url, $query);

        if ($response->status() === 401 || $response->status() === 403) {
            throw IntegrationSyncException::unauthorized($this->provider());
        }

        if ($response->status() === 429) {
            throw IntegrationSyncException::quotaExceeded($this->provider());
        }

        if ($response->serverError()) {
            throw IntegrationSyncException::transport($this->provider(), 'HTTP '.$response->status());
        }

        if (! $response->successful()) {
            throw IntegrationSyncException::malformed($this->provider(), 'HTTP '.$response->status());
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw IntegrationSyncException::malformed($this->provider(), 'réponse non JSON');
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    /** Convertit une valeur monétaire décimale (« 12.34 ») en centimes entiers. */
    protected function toCents(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 0;
        }

        return (int) round(((float) $value) * 100);
    }

    /** Convertit un compteur fournisseur en entier sûr. */
    protected function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Une ligne micros (Google Ads) vers des centimes.
     *
     * Google Ads exprime le coût en micros : 1 000 000 micros = 1 unité
     * monétaire. Diviser par 10 000 donne directement des centimes.
     */
    protected function microsToCents(mixed $value): int
    {
        return is_numeric($value) ? (int) round(((float) $value) / 10_000) : 0;
    }

    /**
     * Relevés normalisés sur une fenêtre de dates (bornes incluses).
     *
     * @param  array<string, string>  $credentials
     * @return list<IntegrationMetricRow>
     */
    abstract public function fetchDailyMetrics(array $credentials, \DateTimeImmutable $from, \DateTimeImmutable $to): array;
}
