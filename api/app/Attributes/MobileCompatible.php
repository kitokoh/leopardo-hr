<?php

namespace App\Attributes;

use Attribute;

/**
 * Attribut pour marquer la compatibilité mobile d'une fonctionnalité API
 */
#[Attribute(Attribute::TARGET_METHOD)]
class MobileCompatible
{
    public function __construct(
        public readonly bool $compatible = true,
        public readonly ?string $minimum_version = null,
        public readonly ?string $maximum_version = null
    ) {}
}
