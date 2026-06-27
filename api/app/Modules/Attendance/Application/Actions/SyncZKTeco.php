<?php

namespace App\Modules\Attendance\Application\Actions;

use App\Models\ZktecoDevice;
use App\Models\ZktecoSyncLog;
use App\Modules\Attendance\Infrastructure\Services\ZktecoIntegrationService;

class SyncZKTeco
{
    public function __construct(
        private readonly ZktecoIntegrationService $zktecoService,
    ) {}

    public function handle(string $deviceId): ZktecoSyncLog
    {
        $device = ZktecoDevice::query()->findOrFail($deviceId);

        return $this->zktecoService->pushUsers($device);
    }
}
