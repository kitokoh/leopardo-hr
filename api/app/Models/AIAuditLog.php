<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\AI\Models\AIAuditLog
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model.
 * ✅  Once all usages reference App\AI\Models\AIAuditLog, delete this file.
 *
 * @see \App\AI\Models\AIAuditLog
 */

declare(strict_types=1);

namespace App\Models;

class_alias(\App\AI\Models\AIAuditLog::class, __NAMESPACE__ . '\\AIAuditLog');
