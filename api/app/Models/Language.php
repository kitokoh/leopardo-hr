<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Shared\Models\Language
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model.
 * ✅  Once all usages reference App\Shared\Models\Language, delete this file.
 *
 * @see \App\Shared\Models\Language
 */

declare(strict_types=1);

namespace App\Models;

class_alias(\App\Shared\Models\Language::class, __NAMESPACE__ . '\\Language');
