<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Contracts\FeatureRegistryInterface;
use App\Modules\Billing\Domain\Models\Feature;
use App\Modules\FuelStation\Domain\Manifest\FuelStationManifest;
use Illuminate\Support\Facades\Log;

/**
 * Enregistrement du catalogue FuelStation dans le Feature Registry
 * (table `features`) — Issue #5795 (FUEL-001).
 *
 * Idempotent : `FeatureRegistry::registerFeature()` fait un upsert par `key`.
 * Le manifest est validé par allowlist : toute clé hors catalogue est refusée
 * (FuelStationManifest::validKey()) — « Manifest validé par allowlist ».
 */
class FuelStationFeatureRegistrar
{
    public function __construct(private readonly FeatureRegistryInterface $registry)
    {
    }

    /**
     * Enregistre toutes les fonctionnalités du catalogue FuelStation.
     *
     * @return int nombre de fonctionnalités enregistrées
     */
    public function registerAll(): int
    {
        $count = 0;

        foreach (FuelStationManifest::FEATURES as $key => $definition) {
            if (! FuelStationManifest::validKey($key)) {
                Log::warning('fuel_station: clé hors catalogue refusée', ['key' => $key]);
                continue;
            }

            $feature = new Feature([
                'key' => $key,
                'title' => $definition['title'],
                'description' => $definition['title'],
                'endpoint' => $definition['endpoint'],
                'http_methods' => $definition['methods'],
                'permissions' => $definition['permissions'],
                'api_version' => 'v1',
                'status' => 'active',
                'metadata' => [
                    'module' => FuelStationManifest::KEY,
                    'maturity' => FuelStationManifest::MATURITY,
                ],
            ]);

            $this->registry->registerFeature($feature);
            $count++;
        }

        return $count;
    }
}
