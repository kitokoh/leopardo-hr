<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Exceptions\FuelStationDependencyMissingException;
use App\Modules\FuelStation\Domain\Models\FuelStationActivation;
use App\Modules\FuelStation\Infrastructure\Services\FuelStationManifestService;
use Illuminate\Support\Facades\Log;

/**
 * Cas d'usage : activer la solution FuelStation sur le tenant courant
 * (issue #5795).
 *
 * - Manifest validé (allowlist) avant toute activation.
 * - Dépendances (modules de base) vérifiées sur la matrice features du
 *   tenant : une dépendance manquante refuse l'activation (422, liste).
 * - Activation idempotente : upsert par company_id + feature flag
 *   `fuelstation` posé sur la company ; rejouer l'activation ne crée rien
 *   de neuf.
 * - Le CRM commercial plateforme (Platform/Marketing) n'est jamais touché.
 */
final class ActivateFuelStation
{
    public function __construct(private readonly FuelStationManifestService $manifestService) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $manifest = $this->manifestService->manifest();
        $company = currentCompany();

        $missing = $this->missingDependencies($company, $manifest['dependencies']);
        if ($missing !== []) {
            throw new FuelStationDependencyMissingException($missing);
        }

        FuelStationActivation::query()->updateOrCreate(
            ['company_id' => $company->id],
            [
                'manifest_version' => (string) $manifest['version'],
                'status' => 'active',
                'activated_at' => now(),
            ],
        );

        if (! $company->hasFeature('fuelstation')) {
            $company->setFeature('fuelstation', true);
            $company->save();
        }

        Log::info('FuelStation: solution activated', [
            'company_id' => $company->id,
            'manifest_version' => $manifest['version'],
        ]);

        return [
            'key' => $manifest['key'],
            'name' => $manifest['name'],
            'version' => $manifest['version'],
            'maturity' => $manifest['maturity'],
            'status' => 'active',
            'activated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, bool>  $required
     * @return array<int, string>
     */
    private function missingDependencies(Company $company, array $required): array
    {
        $missing = [];
        foreach ($required as $module => $needed) {
            if ($needed && ! $company->hasFeature($module)) {
                $missing[] = $module;
            }
        }

        return $missing;
    }
}
