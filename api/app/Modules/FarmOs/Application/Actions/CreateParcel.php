<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\AreaSource;
use App\Modules\FarmOs\Domain\Enums\AreaUnit;
use App\Modules\FarmOs\Domain\Models\FarmSite;
use App\Modules\FarmOs\Domain\Models\Parcel;
use App\Modules\FarmOs\Domain\Services\FarmAreaGuard;

/**
 * Farm OS — Création d'une parcelle (1er geste agricole après le provisioning).
 *
 * La superficie passe OBLIGATOIREMENT par {@see FarmAreaGuard} : valeur et
 * unité ensemble, provenance par défaut `declared`, et `measured` refusé sans
 * géométrie (ADR-FARM-0003).
 */
final class CreateParcel
{
    public function __construct(
        private readonly FarmAreaGuard $areaGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(Company $company, FarmSite $site, array $payload, ?int $employeeId = null): Parcel
    {
        $areaValue = $payload['area_value'] ?? null;
        $areaUnit = isset($payload['area_unit']) ? AreaUnit::tryFrom((string) $payload['area_unit']) : null;
        $requestedSource = isset($payload['area_source']) ? AreaSource::tryFrom((string) $payload['area_source']) : null;
        $geometryWkt = $payload['geometry_wkt'] ?? null;
        $hasGeometry = is_string($geometryWkt) && $geometryWkt !== '';

        $this->areaGuard->assertConsistent($areaValue, $areaUnit, $requestedSource, $hasGeometry);

        return Parcel::query()->create([
            'company_id' => $company->id,
            'farm_site_id' => $site->id,
            'name' => (string) $payload['name'],
            'reference' => (string) ($payload['reference'] ?? $this->nextReference($site)),
            'area_value' => $areaValue,
            'area_unit' => $areaUnit?->value,
            'area_source' => $this->areaGuard->resolveSource($areaValue, $requestedSource)?->value,
            'geometry_wkt' => $hasGeometry ? (string) $geometryWkt : null,
            'geometry_source' => $hasGeometry ? (string) ($payload['geometry_source'] ?? 'drawn') : null,
            'soil_type' => $payload['soil_type'] ?? null,
            'irrigation_type' => $payload['irrigation_type'] ?? null,
            'status' => (string) ($payload['status'] ?? 'active'),
            'notes' => $payload['notes'] ?? null,
            'created_by' => $employeeId,
        ]);
    }

    private function nextReference(FarmSite $site): string
    {
        $count = Parcel::query()->where('farm_site_id', $site->id)->count();

        return sprintf('P-%04d', $count + 1);
    }
}
