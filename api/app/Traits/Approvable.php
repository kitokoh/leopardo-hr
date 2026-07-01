<?php
/**
 * Backward-compat re-export.
 *
 * Canonical: App\Shared\Traits\Approvable
 *
 * ⚠️  DO NOT add logic here. Edit the canonical trait.
 * ✅  Once all usages point to App\Shared\Traits\Approvable, delete this file.
 *
 * @deprecated Use App\Shared\Traits\Approvable
 */

declare(strict_types=1);

namespace App\Traits;

/** @phpstan-ignore-next-line */
trait Approvable
{
    use \App\Shared\Traits\Approvable;
}
