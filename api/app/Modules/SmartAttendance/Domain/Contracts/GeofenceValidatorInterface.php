<?php

declare(strict_types=1);

namespace App\Modules\SmartAttendance\Domain\Contracts;

/**
 * Contrat pour la validation de zone géographique.
 * Implémenté par l'AttendanceGeofenceService existant (réutilisé, pas recréé).
 */
interface GeofenceValidatorInterface
{
    /**
     * @return array{
     *     configured: bool,
     *     inside: bool|null,
     *     distance_meters: int|null,
     *     radius_meters: int|null,
     *     source: string|null
     * }
     */
    public function evaluate(
        \App\Core\Tenant\Domain\Models\Company $company,
        \App\Core\Auth\Domain\Models\Employee $employee,
        ?float $lat,
        ?float $lng
    ): array;
}

