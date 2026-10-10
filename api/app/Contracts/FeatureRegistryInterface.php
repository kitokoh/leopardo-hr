<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * @deprecated BOS-015 (#8202) — Utiliser App\Contracts\ApiEndpointRegistryInterface à la place.
 * Alias conservé 1 release pour rétro-compatibilité.
 */
interface FeatureRegistryInterface extends ApiEndpointRegistryInterface
{
}
