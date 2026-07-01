<?php
/**
 * Class alias — backward compat shim.
 *
 * Canonical: App\Modules\HR\Application\DTOs\UpdateEmployeeDTO
 *
 * ⚠️  DO NOT add logic here. Edit the canonical DTO.
 * ✅  Once all usages → App\Modules\HR\Application\DTOs\UpdateEmployeeDTO, delete this file.
 *
 * @deprecated Use App\Modules\HR\Application\DTOs\UpdateEmployeeDTO instead.
 */

declare(strict_types=1);

namespace App\DTOs;

if (! class_exists(\App\DTOs\UpdateEmployeeDTO::class, false)) {
    class_alias(
        \App\Modules\HR\Application\DTOs\UpdateEmployeeDTO::class,
        \App\DTOs\UpdateEmployeeDTO::class,
    );
}
