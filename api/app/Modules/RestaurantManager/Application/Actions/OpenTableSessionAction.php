<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Enums\TableSessionStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTableSession;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RESTO-409 (#6196) — Ouverture d'une session d'occupation de table.
 *
 * Invariant : une table occupée ne peut pas être rouverte — conflit 409
 * (critère d'acceptation). La session enregistre la branche, le nombre de
 * couverts et éventuellement la commande liée.
 */
final class OpenTableSessionAction
{
    /**
     * @param  array{covers?: int|null, order_id?: int|null}  $data
     */
    public function open(Employee $actor, RestaurantTable $table, array $data): RestaurantTableSession
    {
        if ($table->company_id !== $actor->company_id) {
            throw new RuntimeException('Table does not belong to tenant.');
        }

        $alreadyOccupied = RestaurantTableSession::query()
            ->where('company_id', $actor->company_id)
            ->where('table_id', $table->id)
            ->where('status', TableSessionStatus::OPEN->value)
            ->exists();

        if ($alreadyOccupied) {
            abort(409, 'Table is already occupied.');
        }

        return DB::transaction(function () use ($actor, $table, $data): RestaurantTableSession {
            return RestaurantTableSession::query()->create([
                'company_id' => $actor->company_id,
                'branch_id' => $table->branch_id,
                'table_id' => $table->id,
                'order_id' => $data['order_id'] ?? null,
                'opened_at' => now(),
                'covers' => $data['covers'] ?? null,
                'status' => TableSessionStatus::OPEN->value,
            ]);
        });
    }
}
