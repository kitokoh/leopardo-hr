<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers;

use App\Modules\SearchIntelligence\Domain\Enums\IntegrationStatus;
use App\Modules\SearchIntelligence\Domain\Models\Integration;
use Illuminate\Http\JsonResponse;

/**
 * GSI-8 (#8519) — état des intégrations externes.
 *
 * `GET /api/v1/platform/search-intel/integrations` — garde
 * `platform.permission:search_intel.view`.
 *
 * Cette route expose l'ÉTAT des connecteurs, jamais leurs secrets : le modèle
 * `Integration` cache `credentials_encrypted`, et la sérialisation ci-dessous
 * n'expose qu'un booléen `has_credentials`. Une intégration jamais configurée
 * apparaît `not_connected` — l'écran montre donc les quatre connecteurs
 * attendus plutôt qu'un vide ambigu.
 */
final class SearchIntelIntegrationController
{
    public function index(): JsonResponse
    {
        /** @var array<string, Integration> $byProvider */
        $byProvider = Integration::query()->get()->keyBy('provider')->all();

        $rows = [];

        foreach (['gsc', 'ga4', 'google_ads', 'ms_ads'] as $provider) {
            /** @var Integration|null $integration */
            $integration = $byProvider[$provider] ?? null;

            $rows[] = [
                'provider' => $provider,
                'status' => $integration?->status->value ?? IntegrationStatus::NotConnected->value,
                'status_label' => ($integration->status ?? IntegrationStatus::NotConnected)->label(),
                'has_credentials' => $integration !== null
                    && is_string($integration->getRawOriginal('credentials_encrypted'))
                    && $integration->getRawOriginal('credentials_encrypted') !== '',
                'last_synced_at' => $integration?->last_synced_at?->toIso8601String(),
                'last_error' => $integration?->last_error,
            ];
        }

        return new JsonResponse(['data' => $rows]);
    }
}
