<?php
/**
 * Backward-compat alias.
 * Canonical: App\Shared\Attributes\RequiresPermission
 * @deprecated Use App\Shared\Attributes\RequiresPermission
 */
declare(strict_types=1);
namespace App\Attributes;
if (! class_exists(\App\Attributes\RequiresPermission::class, false)) {
    class_alias(\App\Shared\Attributes\RequiresPermission::class, \App\Attributes\RequiresPermission::class);
}
