<?php
/**
 * Backward-compat alias.
 * Canonical: App\Shared\Attributes\RequiresPermission
 * @deprecated Use App\Shared\Attributes\RequiresPermission
 */
declare(strict_types=1);

// Use a string literal as the alias target so PHPStan does not try to
// resolve the class constant before the alias is registered (which would
// produce "Class App\Attributes\RequiresPermission not found").
class_alias(\App\Shared\Attributes\RequiresPermission::class, 'App\Attributes\RequiresPermission');
