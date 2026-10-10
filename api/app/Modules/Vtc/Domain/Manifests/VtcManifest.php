<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Manifests;

use App\Core\Solutions\Contracts\SolutionManifest;
use App\Core\Solutions\Enums\SolutionIndustry;

/**
 * Manifest de la verticale VTC/taxi (VTC-01, issue #8357, BC-34 VTC).
 *
 * Identité : code `vtc` (feature flag companies.features.vtc), industrie
 * `mobility` (nouvelle case du registre fermé SolutionIndustry), maturité
 * `pilot`, module requis `geo` (BC-33 — le dispatch au chauffeur le plus
 * proche et les estimations passent EXCLUSIVEMENT par le core géospatial,
 * spec docs/specifications/MODULE_GEOCORE_ET_VERTICAL_VTC.md §5), données
 * sensibles PII passagers + localisation + paiements, permissions vtc.*.
 *
 * Dépendances volontairement minimales : seul `geo` est REQUIS (dur) ;
 * `notifications`, `fleet` et `billing` sont optionnels — la verticale doit
 * rester activable sur un tenant frais et dégrader gracieusement (leçon du
 * flag `notifications` non enregistré au registre de flags).
 *
 * Enregistré au `SolutionCatalogue` (clé `vtc`) dans VtcServiceProvider —
 * pattern BOS-014 (#8201), même leçon que DeliveryManifest (#6282).
 *
 * @see \App\Core\Solutions\SolutionCatalogue
 * @see \App\Modules\Vtc\Providers\VtcServiceProvider
 */
final class VtcManifest implements SolutionManifest
{
    public function code(): string
    {
        return 'vtc';
    }

    public function name(): string
    {
        return 'VTC & Taxi';
    }

    public function maturity(): string
    {
        return 'pilot';
    }

    public function industry(): SolutionIndustry
    {
        return SolutionIndustry::Mobility;
    }

    public function description(): string
    {
        return 'Réservation de courses VTC/taxi : estimation de prix, dispatch au chauffeur disponible le plus proche (core géospatial), cycle de course complet et suivi.';
    }

    /**
     * @return list<string>
     */
    public function requiredModules(): array
    {
        return ['geo'];
    }

    /**
     * @return list<string>
     */
    public function optionalModules(): array
    {
        return ['notifications', 'fleet', 'billing'];
    }

    /**
     * @return list<string>
     */
    public function sensitiveData(): array
    {
        return ['customer_pii', 'location', 'payments'];
    }

    /**
     * Permissions / rôles installés à l'activation (personas de la spec
     * §5.5) — deny-by-default, matrice api/docs/architecture/VTC_RBAC.md
     * (VTC-06).
     *
     * @return array<string, string>
     */
    public function permissions(): array
    {
        return [
            'vtc.admin' => 'Administration VTC : profils tarifaires, véhicules, chauffeurs',
            'vtc.dispatcher' => 'Répartition : suivi des courses et des chauffeurs en temps réel',
            'vtc.driver' => 'Chauffeur : offres, transitions de course, positions',
            'vtc.reports' => 'Rapports VTC : volumes, revenus, ponctualité',
        ];
    }
}
