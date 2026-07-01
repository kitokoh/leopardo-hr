<?php
/**
 * Backward-compat re-export.
 *
 * Canonical: App\Shared\Traits\Auditable
 *
 * ⚠️  DO NOT add logic here. Edit the canonical trait.
 * ✅  Once all usages point to App\Shared\Traits\Auditable, delete this file.
 *
 * @deprecated Use App\Shared\Traits\Auditable
 */

declare(strict_types=1);

namespace App\Traits;

/** @phpstan-ignore-next-line */
trait Auditable
{
    use \App\Shared\Traits\Auditable;
}
