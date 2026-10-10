<?php

declare(strict_types=1);

namespace App\Core\Feature\Infrastructure\Services;

use App\Contracts\FeatureRegistryInterface;

/**
 * @deprecated BOS-015 (#8202) — Utiliser App\Core\Feature\Infrastructure\Services\ApiEndpointRegistry.
 * Alias conservé 1 release pour rétro-compatibilité.
 */
class FeatureRegistry extends ApiEndpointRegistry implements FeatureRegistryInterface
{
}
