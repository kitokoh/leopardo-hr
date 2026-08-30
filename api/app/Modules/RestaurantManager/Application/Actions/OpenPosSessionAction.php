<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Enums\PosSessionStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RESTO-401 (#6188) — Ouverture d'une session de caisse POS.
 *
 * Invariants (spec §5.2, D3) :
 * - une seule session `open` par branche : contrainte UNIQUE
 *   (company_id, branch_id, status) en base + garde applicative 409 ;
 * - fonds d'ouverture en minor units (aucun flottant) ;
 * - `opened_by_user_id` = acteur authentifié.
 */
final class OpenPosSessionAction
{
    /**
     * @param  array{opening_cash_minor: int, branch_id: int}  $data
     */
    public function open(Employee $actor, array $data): RestaurantPosSession
    {
        $branch = RestaurantBranch::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($data['branch_id']);

        if ($branch->company_id !== $actor->company_id) {
            throw new RuntimeException('Branch does not belong to tenant.');
        }

        // Garde applicative : une seule session ouverte par branche (409).
        $alreadyOpen = RestaurantPosSession::query()
            ->where('company_id', $actor->company_id)
            ->where('branch_id', $branch->id)
            ->where('status', PosSessionStatus::OPEN->value)
            ->exists();

        if ($alreadyOpen) {
            abort(409, 'A POS session is already open for this branch.');
        }

        try {
            return DB::transaction(function () use ($actor, $branch, $data): RestaurantPosSession {
                return RestaurantPosSession::query()->create([
                    'company_id' => $actor->company_id,
                    'branch_id' => $branch->id,
                    'opened_at' => now(),
                    'opened_by_user_id' => $actor->id,
                    'opening_cash_minor' => $data['opening_cash_minor'],
                    'status' => PosSessionStatus::OPEN->value,
                    'version' => 1,
                ]);
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Course entre deux requêtes : la contrainte (tenant, branche, statut)
            // a tranché — la seconde ouverture est refusée proprement.
            abort(409, 'A POS session is already open for this branch.');
        }
    }
}
