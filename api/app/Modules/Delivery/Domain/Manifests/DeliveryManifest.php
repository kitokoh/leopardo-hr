<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Manifests;

use App\Modules\Delivery\Domain\Contracts\SolutionManifest;

/**
 * Manifest du module Delivery (DELIVERY-101, issue #6282).
 *
 * Identité : code `delivery` (feature flag companies.features.delivery),
 * maturité `pilot`, modules requis rh/documents/notifications/crm/accounting
 * (livreurs = employés, POD = documents, notifications destinataire, contacts
 * CRM, encaissements COD = comptabilité), données sensibles PII clients +
 * paiements + localisation, permissions delivery.* (personas de la spec
 * SOLUTION_DELIVERY.md §1).
 *
 * BC-26 est un module de livraison dernier-kilomètre GÉNÉRIQUE : tout tenant
 * qui livre (agence, restaurant BC-25, retail BC-17, e-commerce BC-14, CRM
 * BC-11, pharmacie) active le même moteur via ce flag.
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

    /**
     * @return array<int, string>
     */
    public function requiredModules(): array
    {
        return ['rh', 'documents', 'notifications', 'crm', 'accounting'];
    }

    /**
     * @return array<int, string>
     */
    public function optionalModules(): array
    {
        return ['fleet', 'marketing'];
    }

    /**
     * @return array<int, string>
     */
    public function sensitiveData(): array
    {
        return ['customer_pii', 'payments', 'location'];
    }

    /**
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return [
            'delivery.admin',
            'delivery.dispatcher',
            'delivery.rider',
            'delivery.manager',
            'delivery.reports',
        ];
    }
}
