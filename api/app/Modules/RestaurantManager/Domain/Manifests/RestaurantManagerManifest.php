<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Manifests;

use App\Modules\RestaurantManager\Domain\Contracts\SolutionManifest;

/**
 * Manifest de la verticale RestaurantManager (RESTO-106, issue #6163).
 *
 * Identité : code `restaurantmanager` (feature flag
 * companies.features.restaurantmanager), maturité `pilot`, modules requis
 * rh/documents/notifications/crm, données sensibles PII clients + paiements,
 * permissions restaurant.* (personas de la spec §1.2).
 */
final class RestaurantManagerManifest implements SolutionManifest
{
    public function code(): string
    {
        return 'restaurantmanager';
    }

    public function name(): string
    {
        return 'RestaurantManager';
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
        return ['customer_pii', 'payments'];
    }

    /**
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return [
            'restaurant.manage',
            'restaurant.manager',
            'restaurant.server',
            'restaurant.kitchen',
            'restaurant.rider',
            'restaurant.reports',
        ];
    }
}
