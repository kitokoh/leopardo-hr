<?php
/**
 * Backward-compat re-export.
 *
 * Canonical: App\Shared\Traits\BelongsToCompany
 *
 * ⚠️  DO NOT add logic here. Edit the canonical trait.
 * ✅  Once all usages point to App\Shared\Traits\BelongsToCompany, delete this file.
 *
 * @deprecated Use App\Shared\Traits\BelongsToCompany
 */

declare(strict_types=1);

namespace App\Traits;

/** @phpstan-ignore-next-line */
trait BelongsToCompany
{
    use \App\Shared\Traits\BelongsToCompany;
}
