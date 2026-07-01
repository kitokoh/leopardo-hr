<?php
/**
 * Backward-compat alias.
 * Canonical: App\Shared\Enums\ApiError
 * @deprecated Use App\Shared\Enums\ApiError
 */
declare(strict_types=1);
namespace App\Enums;
if (! class_exists(\App\Enums\ApiError::class, false)) {
    class_alias(\App\Shared\Enums\ApiError::class, \App\Enums\ApiError::class);
}
