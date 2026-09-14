<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Manifests;

use App\Core\Solutions\Contracts\SolutionManifest;

/**
 * Manifest de la verticale TravelAgency (TRAVEL-106, issue #6011).
 *
 * Identité : code `travelagency` (feature flag companies.features.travelagency),
 * maturité `pilot`, modules requis rh/documents/notifications/crm, données
 * sensibles PII passagers + paiements, permissions travel.*.
 *
 * #7220-bis (audit 2026-09-14) — le manifest implémentait un contrat LOCAL au
 * module (`App\Modules\TravelAgency\Domain\Contracts\SolutionManifest`)
 * incompatible avec le contrat core consommé par le `SolutionCatalogue` :
 * `description()` manquait (or `SolutionSurveyController` l'appelle → fatal)
 * et `permissions()` retournait une liste au lieu d'une map `code => libellé`.
 * Résultat : la verticale ne pouvait pas être enregistrée au catalogue, donc
 * `POST /api/v1/trial/signup` avec `solutions:["travelagency"]` répondait
 * 422 `INVALID_SOLUTION` — un propriétaire d'agence de voyage ne pouvait
 * jamais activer sa verticale à l'inscription. Le manifest implémente
 * désormais le contrat core, comme RestaurantManifest/FuelStationManifest.
 *
 * @see \App\Core\Solutions\SolutionCatalogue
 * @see \App\Modules\TravelAgency\Providers\TravelAgencyServiceProvider
 */
final class TravelAgencyManifest implements SolutionManifest
{
    /** Code du manifest = clé d'allowlist du catalogue = feature flag tenant. */
    public const CODE = 'travelagency';

    public function code(): string
    {
        return self::CODE;
    }

    public function name(): string
    {
        return 'TravelAgency';
    }

    public function maturity(): string
    {
        return 'pilot';
    }

    public function description(): string
    {
        return 'Gestion opérationnelle d\'agence de voyage : réseau de routes et escales, trajets datés, tarifs par classe, sièges, billetterie et e-billets, boutique publique, locations, fidélité voyageur et règlements.';
    }

    /** @return list<string> */
    public function requiredModules(): array
    {
        return ['rh', 'documents', 'notifications', 'crm'];
    }

    /** @return list<string> */
    public function optionalModules(): array
    {
        return ['accounting', 'marketing'];
    }

    /** @return list<string> */
    public function sensitiveData(): array
    {
        return ['passenger_pii', 'payments'];
    }

    /**
     * Permissions déclarées par la solution.
     *
     * @return array<string, string>
     */
    public function permissions(): array
    {
        return [
            'travel.manage' => 'Administration de l\'agence (réseau, trajets, tarifs, publication)',
            'travel.agent' => 'Vente au guichet : réservations, encaissement et émission de billets',
            'travel.checkin' => 'Embarquement : check-in et contrôle des billets',
            'travel.reports' => 'Rapports d\'exploitation internes de l\'agence',
        ];
    }
}
