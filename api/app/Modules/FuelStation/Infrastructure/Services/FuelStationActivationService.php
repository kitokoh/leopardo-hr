<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Manifest\FuelStationManifest;
use Illuminate\Support\Facades\Log;

/**
 * Activation tenant de FuelStation — Issue #5795 (FUEL-001).
 *
 * Règles :
 *   - idempotente : activer deux fois ne produit pas d'erreur ;
 *   - dépendances manquantes refusées : si une dépendance REQUIRED est
 *     explicitement désactivée sur le tenant (companies.features), l'activation
 *     est refusée (FuelStationActivationException) ;
 *   - le CRM commercial plateforme n'est jamais touché (aucune écriture
 *     Platform/Marketing).
 */
class FuelStationActivationService
{
    public function activate(Company $company): bool
    {
        $this->assertDependencies($company);

        $alreadyActive = $company->hasFeature(FuelStationManifest::KEY);
        $company->setFeature(FuelStationManifest::KEY, true);

        Log::info('fuel_station: activation tenant', [
            'company_id' => $company->id,
            'was_active' => $alreadyActive,
        ]);

        return ! $alreadyActive;
    }

    public function isActive(Company $company): bool
    {
        return $company->hasFeature(FuelStationManifest::KEY);
    }

    private function assertDependencies(Company $company): void
    {
        $features = is_array($company->features) ? $company->features : [];

        foreach (FuelStationManifest::REQUIRED_DEPENDENCIES as $dependency) {
            // Dépendance explicitement désactivée → refus.
            if (array_key_exists($dependency, $features) && ! $features[$dependency]) {
                throw new FuelStationActivationException(
                    "Dépendance requise désactivée : {$dependency}"
                );
            }
        }

        if ($company->status !== 'active' && $company->status !== 'trial') {
            throw new FuelStationActivationException(
                "Tenant non actif (statut : {$company->status})"
            );
        }
    }
}
