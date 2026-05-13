<?php

namespace App\Attributes;

use Attribute;

/**
 * Attribut pour spécifier les permissions requises pour une fonctionnalité API
 */
#[Attribute(Attribute::TARGET_METHOD)]
class RequiresPermission
{
    /**
     * @param  string|array<int, string>  $permissions
     */
    public function __construct(
        public readonly string|array $permissions
    ) {}
}
