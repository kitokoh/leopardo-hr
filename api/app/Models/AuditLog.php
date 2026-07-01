<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Core\Auth\Domain\Models\AuditLog
 *
 * ⚠️  DO NOT add logic here. Edit the canonical model.
 * ✅  Once all usages reference App\Core\Auth\Domain\Models\AuditLog, delete this file.
 *
 * @see \App\Core\Auth\Domain\Models\AuditLog
 */

declare(strict_types=1);

namespace App\Models;

class_alias(\App\Core\Auth\Domain\Models\AuditLog::class, __NAMESPACE__ . '\\AuditLog');
