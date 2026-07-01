<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\AI\Models\AIConversation
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model.
 * ✅  Once all usages reference App\AI\Models\AIConversation, delete this file.
 *
 * @see \App\AI\Models\AIConversation
 */

declare(strict_types=1);

namespace App\Models;

class_alias(\App\AI\Models\AIConversation::class, __NAMESPACE__ . '\\AIConversation');
