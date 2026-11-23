<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Connectors;

use App\Modules\Attendance\Domain\Models\CalendarConnection;
use App\Shared\Contracts\Connectors\ConnectorIntegrationSourceInterface;

/** #9846 — synchronisation calendrier du tenant. */
final class CalendarConnectionIntegrationSource implements ConnectorIntegrationSourceInterface
{
    public function key(): string
    {
        return 'calendar';
    }

    public function label(): string
    {
        return 'Synchronisation calendrier';
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
        return 'Congés et formations poussés vers Google Calendar / Outlook.';
    }

    /** @return list<'platform'|'tenant'> */
    public function audiences(): array
    {
        return ['tenant'];
    }

    public function count(): int
    {
        return CalendarConnection::query()->where('is_active', true)->count();
    }
}
