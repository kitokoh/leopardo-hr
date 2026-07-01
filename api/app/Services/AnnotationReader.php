<?php
/**
 * Backward-compat alias shim.
 *
 * Canonical: App\Core\Feature\Infrastructure\Services\AnnotationReader
 *
 * ⚠️  DO NOT add logic here. Edit the canonical service.
 * ✅  Once all usages reference App\Core\Feature\Infrastructure\Services\AnnotationReader, delete this file.
 */

declare(strict_types=1);

namespace App\Services;

class_alias(\App\Core\Feature\Infrastructure\Services\AnnotationReader::class, __NAMESPACE__ . '\\AnnotationReader');
