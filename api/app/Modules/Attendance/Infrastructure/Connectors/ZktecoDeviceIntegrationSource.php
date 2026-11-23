<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Connectors;

use App\Modules\Attendance\Domain\Models\ZktecoDevice;
use App\Shared\Contracts\Connectors\ConnectorIntegrationSourceInterface;

/** #9846 — pointeuses biométriques ZKTeco du tenant. */
final class ZktecoDeviceIntegrationSource implements ConnectorIntegrationSourceInterface
{
    public function key(): string
    {
        return 'zkteco_devices';
    }

    public function label(): string
    {
        return 'Pointeuses ZKTeco';
    }

    public function category(): string
    {
        return 'other';
    }

    public function href(): string
    {
        return '/settings/integrations';
    }

    public function detail(): string
    {
        return 'Appareils biométriques de pointage connectés à la plateforme.';
    }

    /** @return list<'platform'|'tenant'> */
    public function audiences(): array
    {
        return ['tenant'];
    }

    public function count(): int
    {
        return ZktecoDevice::query()->count();
    }
}
