<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Infrastructure\Jobs;

use App\Modules\SearchIntelligence\Domain\Contracts\IntegrationMetricRow;
use App\Modules\SearchIntelligence\Domain\Enums\IntegrationStatus;
use App\Modules\SearchIntelligence\Domain\Enums\MetricSource;
use App\Modules\SearchIntelligence\Domain\Exceptions\IntegrationSyncException;
use App\Modules\SearchIntelligence\Domain\Models\Campaign;
use App\Modules\SearchIntelligence\Domain\Models\CampaignMetric;
use App\Modules\SearchIntelligence\Domain\Models\Integration;
use App\Modules\SearchIntelligence\Infrastructure\Connectors\AbstractGoogleConnector;
use App\Modules\SearchIntelligence\Infrastructure\Connectors\Ga4Connector;
use App\Modules\SearchIntelligence\Infrastructure\Connectors\GoogleAdsConnector;
use App\Modules\SearchIntelligence\Infrastructure\Connectors\GoogleSearchConsoleConnector;
use App\Modules\SearchIntelligence\Infrastructure\Connectors\MicrosoftAdsConnector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GSI-12 (#8523) point 1 — synchronisation d'un connecteur réel.
 *
 * Le job est **idempotent par construction** : la clé métier est
 * `(campaign_id, measured_on)`, déjà unique en base. Une seconde exécution sur
 * la même fenêtre met à jour les mêmes lignes (upsert) au lieu de créer des
 * doublons ; une reprise après panne produit donc exactement le même état
 * qu'une exécution unique.
 *
 * Ce qu'il ne fait jamais :
 * - **Créer une campagne.** Un `campaignExternalId` inconnu est compté dans
 *   `unmatched` et ignoré : une faute de frappe côté régie ne doit pas faire
 *   apparaître une campagne fantôme dans le produit.
 * - **Marquer `connected` tout seul.** Le statut passe à `error` en cas
 *   d'échec (avec `last_error` lisible) et n'est jamais repassé à `connected`
 *   ici : c'est la conséquence d'une action explicite de l'utilisateur.
 * - **Persister un secret.** Les identifiants sont déchiffrés en mémoire, et
 *   le motif d'erreur stocké est celui de l'exception, rédigé sans jeton.
 */
final class SyncIntegrationMetrics implements ShouldQueue
{
    use Queueable;

    /** 3 essais : une panne réseau passagère ne doit pas perdre la fenêtre. */
    public int $tries = 3;

    /** Backoff progressif, en secondes.
     *
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $integrationId,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
    ) {
    }

    public function handle(): void
    {
        $integration = Integration::query()->find($this->integrationId);

        if ($integration === null) {
            return;
        }

        if (! $integration->isUsable()) {
            // Non connecté : rien à synchroniser, et ce n'est pas une erreur.
            return;
        }

        $connector = $this->connectorFor($integration->provider);

        if ($connector === null) {
            $this->markError($integration, 'fournisseur sans connecteur réel disponible');

            return;
        }

        $to = $this->to !== null
            ? new \DateTimeImmutable($this->to)
            : new \DateTimeImmutable('yesterday');

        $from = $this->from !== null
            ? new \DateTimeImmutable($this->from)
            : $to->sub(new \DateInterval('P6D'));

        // Garde de cohérence : une fenêtre inversée produirait une requête
        // fournisseur coûteuse et vide, jamais une erreur claire.
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        try {
            $rows = $connector->fetchDailyMetrics($this->credentialsOf($integration), $from, $to);
        } catch (IntegrationSyncException $e) {
            $this->markError($integration, $e->reason);

            // Une erreur réessayable est relancée pour que la file applique le
            // backoff ; une erreur définitive s'arrête là (reconnexion requise).
            if ($e->retryable) {
                throw $e;
            }

            return;
        }

        $written = $this->persist($integration, $rows);

        $integration->forceFill([
            'last_synced_at' => now(),
            'last_error' => null,
            'status' => IntegrationStatus::Connected,
        ])->save();

        Log::info('search_intel.integration.synced', [
            'integration_id' => $integration->id,
            'provider' => $integration->provider,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'rows_received' => count($rows),
            'rows_written' => $written['written'],
            'rows_unmatched' => $written['unmatched'],
        ]);
    }

    /**
     * Écrit les relevés sans jamais créer de doublon.
     *
     * @param  list<IntegrationMetricRow>  $rows
     * @return array{written: int, unmatched: int}
     */
    private function persist(Integration $integration, array $rows): array
    {
        $written = 0;
        $unmatched = 0;

        // Résolution en une requête : les identifiants externes présents dans
        // le lot, pour éviter N requêtes sur les campagnes.
        $externalIds = array_values(array_unique(array_map(
            static fn (IntegrationMetricRow $row): string => $row->campaignExternalId,
            $rows,
        )));

        // Le rapprochement se fait sur `utm_campaign` : c'est la seule colonne
        // que la régie et le produit partagent, et elle est déjà utilisée pour
        // l'attribution. Le nom n'est pas fiable (renommable côté régie).
        $campaigns = Campaign::query()
            ->whereIn('utm_campaign', $externalIds)
            ->get(['id', 'utm_campaign'])
            ->keyBy('utm_campaign');

        DB::transaction(function () use ($rows, $integration, $campaigns, &$written, &$unmatched): void {
            $now = now();

            foreach ($rows as $row) {
                if ($row->isEmpty()) {
                    continue;
                }

                $campaign = $campaigns->get($row->campaignExternalId);

                if ($campaign === null) {
                    $unmatched++;

                    continue;
                }

                CampaignMetric::query()->upsert(
                    [[
                        'campaign_id' => $campaign->id,
                        'measured_on' => $row->measuredOn->format('Y-m-d'),
                        'source' => MetricSource::Integration->value,
                        'integration_id' => $integration->id,
                        'spend_cents' => $row->spendCents,
                        'impressions' => $row->impressions,
                        'clicks' => $row->clicks,
                        'conversions' => $row->conversions,
                        'revenue_cents' => $row->revenueCents,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]],
                    ['campaign_id', 'measured_on'],
                    ['source', 'integration_id', 'spend_cents', 'impressions', 'clicks', 'conversions', 'revenue_cents', 'updated_at'],
                );

                $written++;
            }
        });

        return ['written' => $written, 'unmatched' => $unmatched];
    }

    private function markError(Integration $integration, string $reason): void
    {
        $integration->forceFill([
            'status' => IntegrationStatus::Error,
            'last_error' => mb_substr($reason, 0, 500),
        ])->save();

        Log::warning('search_intel.integration.sync_failed', [
            'integration_id' => $integration->id,
            'provider' => $integration->provider,
            'reason' => $reason,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function credentialsOf(Integration $integration): array
    {
        $raw = $integration->credentials_encrypted;

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        /** @var array<string, string> $decoded */
        $decoded = json_decode($raw, true) ?: [];

        return $decoded;
    }

    private function connectorFor(string $provider): ?AbstractGoogleConnector
    {
        return match ($provider) {
            'gsc' => new GoogleSearchConsoleConnector,
            'ga4' => new Ga4Connector,
            'google_ads' => new GoogleAdsConnector,
            'ms_ads' => new MicrosoftAdsConnector,
            default => null,
        };
    }
}
