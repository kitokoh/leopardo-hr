<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Contracts;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;

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
        Company $company,
        Employee $employee,
        ?float $lat,
        ?float $lng
    ): array;
}
