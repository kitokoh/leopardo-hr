<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Manifests;

use App\Core\Solutions\Contracts\SolutionManifest;
use App\Core\Solutions\Enums\SolutionIndustry;

/**
 * Manifest du module Delivery (DELIVERY-101, issue #6282).
 *
 * Identité : code `delivery` (feature flag companies.features.delivery),
 * industrie `delivery_logistics`, maturité `pilot`, modules requis
 * rh/documents/notifications/crm/accounting (livreurs = employés, POD =
 * documents, notifications destinataire, contacts CRM, encaissements COD =
 * comptabilité), données sensibles PII clients + paiements + localisation,
 * permissions delivery.* (personas de la spec SOLUTION_DELIVERY.md §1).
 *
 * BC-26 est un module de livraison dernier-kilomètre GÉNÉRIQUE : tout tenant
 * qui livre (agence, restaurant BC-25, retail BC-17, e-commerce BC-14, CRM
 * BC-11, pharmacie) active le même moteur via ce flag.
 *
 * BOS-014 (#8201) — le manifest implémente désormais le contrat CORE v2
 * (`industry()`, `description()`, permissions en map `code => libellé`) et
 * est enregistré au `SolutionCatalogue` (clé `delivery`) : fin du contrat
 * local dupliqué (anti-pattern #7220-bis, même leçon que TravelAgency).
 * L'ancien contrat local `App\Modules\Delivery\Domain\Contracts\SolutionManifest`
 * est conservé DEPRECATED en attendant la validation des activations
 * (rollback note de l'issue) — il n'est plus implémenté ni bindé.
 *
 * @see \App\Core\Solutions\SolutionCatalogue
 * @see \App\Modules\Delivery\Providers\DeliveryServiceProvider
 */
final class DeliveryManifest implements SolutionManifest
{
    public function code(): string
    {
        return 'delivery';
    }

    public function name(): string
    {
        return 'Delivery';
    }

    public function maturity(): string
    {
        return 'pilot';
    }

    public function industry(): SolutionIndustry
    {
        return SolutionIndustry::DeliveryLogistics;
    }

    public function description(): string
    {
        return 'Livraison dernier-kilomètre générique : enlèvements, tournées et livreurs, preuve de livraison (POD), encaissements COD et suivi destinataire.';
    }

    /**
     * @return list<string>
     */
    public function requiredModules(): array
    {
        return ['rh', 'documents', 'notifications', 'crm', 'accounting'];
    }

    /**
     * @return list<string>
     */
    public function optionalModules(): array
    {
        return ['fleet', 'marketing'];
    }

    /**
     * @return list<string>
     */
    public function sensitiveData(): array
    {
        return ['customer_pii', 'payments', 'location'];
    }

    /**
     * Permissions / rôles spécifiques installés par la solution (personas de
     * la spec SOLUTION_DELIVERY.md §1) — installés à l'activation via le
     * socle de grants (BOS-013).
     *
     * @return array<string, string>
     */
    public function permissions(): array
    {
        return [
            'delivery.admin' => 'Administration de la livraison : paramètres, tarifs, zones',
            'delivery.dispatcher' => 'Répartition : affectation des courses et des tournées',
            'delivery.rider' => 'Livreur : ses courses, POD et encaissements de sa session',
            'delivery.manager' => 'Pilotage opérationnel : suivi des tournées et traitement des exceptions',
            'delivery.reports' => 'Rapports de livraison : volumes, délais, COD',
        ];
    }
}
