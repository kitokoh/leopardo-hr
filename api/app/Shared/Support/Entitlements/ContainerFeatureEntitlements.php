<?php

declare(strict_types=1);

namespace App\Shared\Support\Entitlements;

use App\Modules\Billing\Domain\Services\EntitlementGuard;
use App\Shared\Contracts\Entitlements\FeatureEntitlements;

/**
 * Adaptateur du contrat partagé {@see FeatureEntitlements} vers
 * l'implémentation réelle {@see EntitlementGuard}.
 *
 * Ce fichier vit dans `Shared` et **non** dans un module : c'est le seul
 * endroit autorisé à connaître les deux côtés du pont (même pattern que
 * `PublicTenantResolver`). Un module ne référence que l'interface.
 */
final class ContainerFeatureEntitlements implements FeatureEntitlements
{
    public function __construct(private readonly EntitlementGuard $guard) {}

    public function isFeatureEnabled(string $companyId, string $featureKey): bool
    {
        return $this->guard->isFeatureEnabled($companyId, $featureKey);
    }

    public function featureLimit(string $companyId, string $featureKey): ?int
    {
        return $this->guard->featureLimit($companyId, $featureKey);
    }
}
