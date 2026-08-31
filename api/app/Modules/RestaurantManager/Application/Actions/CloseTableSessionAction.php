<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Enums\TableSessionStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTableSession;
use App\Modules\RestaurantManager\Infrastructure\Services\RestaurantOutboxPublisher;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RESTO-409 (#6196) — Clôture d'une session d'occupation de table.
 *
 * Clôture définitive (immuable) + événement `restaurant.table.closed.v1`
 * (consommateurs Reporting/Accounting, spec §6.3).
 */
final class CloseTableSessionAction
{
    public const EVENT_TABLE_CLOSED = 'restaurant.table.closed.v1';

    public function __construct(private readonly RestaurantOutboxPublisher $outbox)
    {
    }

    public function close(Employee $actor, RestaurantTableSession $session): RestaurantTableSession
    {
        if ($session->company_id !== $actor->company_id) {
            throw new RuntimeException('Session does not belong to tenant.');
        }

        if ($session->status !== TableSessionStatus::OPEN) {
            abort(409, 'Table session is not open.');
        }

        DB::transaction(function () use ($session): void {
            $session->forceFill([
                'status' => TableSessionStatus::CLOSED->value,
                'closed_at' => now(),
            ])->save();
        });

        $this->outbox->publish(
            $session->company_id,
            self::EVENT_TABLE_CLOSED,
            [
                'table_session_id' => $session->id,
                'table_id' => $session->table_id,
                'branch_id' => $session->branch_id,
                'order_id' => $session->order_id,
                'closed_at' => $session->closed_at?->toIso8601String(),
            ],
        );

        $session->refresh();

        return $session;
    }
}
