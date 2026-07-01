<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Core\Auth\Infrastructure\Services\DataAccessAuditLogger
 *
 * ⚠️  DO NOT add logic here. Edit the canonical service.
 * ✅  Once all usages reference App\Core\Auth\Infrastructure\Services\DataAccessAuditLogger, delete this file.
 */

declare(strict_types=1);

namespace App\Services;

class_alias(\App\Core\Auth\Infrastructure\Services\DataAccessAuditLogger::class, __NAMESPACE__ . '\\DataAccessAuditLogger');
