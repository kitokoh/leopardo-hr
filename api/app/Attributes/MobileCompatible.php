<?php
/**
 * Backward-compat alias.
 * Canonical: App\Shared\Attributes\MobileCompatible
 * @deprecated Use App\Shared\Attributes\MobileCompatible
 */
declare(strict_types=1);
namespace App\Attributes;
if (! class_exists(\App\Attributes\MobileCompatible::class, false)) {
    class_alias(\App\Shared\Attributes\MobileCompatible::class, \App\Attributes\MobileCompatible::class);
}
