<?php
/**
 * Class alias — backward compat shim.
 *
 * Canonical: App\Modules\HR\Application\DTOs\CreateEmployeeDTO
 *
 * ⚠️  DO NOT add logic here. Edit the canonical DTO.
 * ✅  Once all usages → App\Modules\HR\Application\DTOs\CreateEmployeeDTO, delete this file.
 *
 * @deprecated Use App\Modules\HR\Application\DTOs\CreateEmployeeDTO instead.
 */

declare(strict_types=1);

namespace App\DTOs;

if (! class_exists(\App\DTOs\CreateEmployeeDTO::class, false)) {
    class_alias(
        \App\Modules\HR\Application\DTOs\CreateEmployeeDTO::class,
        \App\DTOs\CreateEmployeeDTO::class,
    );
}
