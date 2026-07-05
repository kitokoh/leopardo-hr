<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\\Core\Auth\Infrastructure\Services\SSO\SSOProviderConfig
 *
 * ??  DO NOT add logic here. Edit the canonical service.
 * ?  Once all usages reference App\\Core\Auth\Infrastructure\Services\SSO\SSOProviderConfig, delete this file.
 */

declare(strict_types=1);

namespace App\Services\;

class_alias(\\App\\Core\Auth\Infrastructure\Services\SSO\SSOProviderConfig::class, __NAMESPACE__ . '\SSOProviderConfig');
