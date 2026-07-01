<?php
/**
 * Class alias — backward compat shim.
 *
 * Canonical: App\Modules\Attendance\Application\DTOs\CheckInDTO
 *
 * ⚠️  DO NOT add logic here. Edit the canonical DTO.
 * ✅  Once all usages → App\Modules\Attendance\Application\DTOs\CheckInDTO, delete this file.
 *
 * @deprecated Use App\Modules\Attendance\Application\DTOs\CheckInDTO instead.
 */

declare(strict_types=1);

namespace App\DTOs;

if (! class_exists(\App\DTOs\CheckInDTO::class, false)) {
    class_alias(
        \App\Modules\Attendance\Application\DTOs\CheckInDTO::class,
        \App\DTOs\CheckInDTO::class,
    );
}
