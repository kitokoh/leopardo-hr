<?php

declare(strict_types=1);

namespace App\Core\Feature\Infrastructure\Services;

use App\Contracts\ApiEndpointRegistryInterface;

/**
 * BOS-015 (#8202) — Registre d'endpoints API pour le manifeste mobile.
 *
 * Alias canonique clarifié de FeatureRegistry, éliminant l'homonymie avec ModuleRegistry (BOS-011).
 */
class ApiEndpointRegistry extends FeatureRegistry implements ApiEndpointRegistryInterface
{
}
