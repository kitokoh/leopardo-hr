<?php
/**
 * Backward-compat alias.
 * Canonical: App\Shared\Attributes\ApiFeature
 * @deprecated Use App\Shared\Attributes\ApiFeature
 */
declare(strict_types=1);
namespace App\Attributes;
if (! class_exists(\App\Attributes\ApiFeature::class, false)) {
    class_alias(\App\Shared\Attributes\ApiFeature::class, \App\Attributes\ApiFeature::class);
}
