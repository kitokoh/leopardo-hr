<?php

namespace App\Attributes;

use Attribute;

/**
 * Attribut pour marquer et configurer une fonctionnalité API
 *
 * Cet attribut permet de définir les métadonnées d'une fonctionnalité API
 * directement dans le code du contrôleur.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class ApiFeature
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $description = null,
        public readonly string $ui_type = 'generic',
        public readonly bool $mobile_compatible = true,
        public readonly ?string $mobile_version_min = null,
        public readonly ?string $mobile_version_max = null,
        public readonly array $form_schema = [],
        public readonly array $list_schema = []
    ) {}
}
