<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Manifests;

use App\Modules\TravelAgency\Domain\Contracts\SolutionManifest;

/**
 * Manifest de la verticale TravelAgency (TRAVEL-106, issue #6011).
 *
 * Identité : code `travelagency` (feature flag companies.features.travelagency),
 * maturité `pilot`, modules requis rh/documents/notifications/crm, données
 * sensibles PII passagers + paiements, permissions travel.*.
 */
final class TravelAgencyManifest implements SolutionManifest
{
    public function code(): string
    {
        return 'travelagency';
    }

    public function name(): string
    {
        return 'TravelAgency';
    }

    public function maturity(): string
    {
        return 'pilot';
    }

    /**
     * @return array<int, string>
     */
    public function requiredModules(): array
    {
        return ['rh', 'documents', 'notifications', 'crm'];
    }

    /**
     * @return array<int, string>
     */
    public function optionalModules(): array
    {
        return ['accounting', 'marketing'];
    }

    /**
     * @return array<int, string>
     */
    public function sensitiveData(): array
    {
        return ['passenger_pii', 'payments'];
    }

    /**
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return [
            'travel.manage',
            'travel.agent',
            'travel.checkin',
            'travel.reports',
        ];
    }
}
