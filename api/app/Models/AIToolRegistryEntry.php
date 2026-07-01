<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\AI\Models\AIToolRegistryEntry
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model.
 * ✅  Once all usages reference App\AI\Models\AIToolRegistryEntry, delete this file.
 *
 * @see \App\AI\Models\AIToolRegistryEntry
 */

declare(strict_types=1);

namespace App\Models;

class_alias(\App\AI\Models\AIToolRegistryEntry::class, __NAMESPACE__ . '\\AIToolRegistryEntry');
