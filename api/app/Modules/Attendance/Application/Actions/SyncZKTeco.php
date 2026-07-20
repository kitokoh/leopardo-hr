<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Modules\Attendance\Infrastructure\Services\ZktecoIntegrationService;

class SyncZKTeco
{
    public function __construct(
        private readonly ZktecoIntegrationService $zktecoService,
    ) {}

    public function handle(string $deviceId): int
    {
        return $this->zktecoService->syncDevice($deviceId);
    }
}
