<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * BOS-015 (#8202) — Interface du registre d'endpoints API (manifeste mobile).
 *
 * Renommé depuis FeatureRegistryInterface pour lever l'ambiguïté avec le
 * ModuleRegistry / feature-gating tenant (BOS-011).
 */
interface ApiEndpointRegistryInterface extends FeatureRegistryInterface
{
}
