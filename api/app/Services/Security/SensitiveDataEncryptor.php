<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\\Core\Auth\Infrastructure\Services\SensitiveDataEncryptor
 *
 * ??  DO NOT add logic here. Edit the canonical service.
 * ?  Once all usages reference App\\Core\Auth\Infrastructure\Services\SensitiveDataEncryptor, delete this file.
 */

declare(strict_types=1);

namespace App\Services\Security;

class_alias(\App\Core\Auth\Infrastructure\Services\SensitiveDataEncryptor::class, __NAMESPACE__ . '\SensitiveDataEncryptor');
