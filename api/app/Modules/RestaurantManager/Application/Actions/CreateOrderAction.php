<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Enums\PosSessionStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPosSession;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use Illuminate\Support\Facades\DB;

/**
 * RESTO-402 (#6189) — Création de commande (idempotente).
 *
 * Idempotence : si `idempotency_key` est fournie et qu'une commande du
 * tenant existe déjà avec cette clé, l'action retourne la commande existante
 * (rejeu sans doublon — critère d'acceptation). Sinon la clé est générée
 * côté serveur (modèle). `reference` (RST-…) est générée automatiquement.
 *
 * Validation métier : la branche, la table (si `dine_in`) et la session POS
 * (si fournie) doivent appartenir au tenant ET à la même branche.
 */
final class CreateOrderAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{order: RestaurantOrder, created: bool}
     */
    public function create(Employee $actor, array $data): array
    {
        $companyId = $actor->company_id;

        if (isset($data['idempotency_key'])) {
            $existing = RestaurantOrder::query()
                ->where('company_id', $companyId)
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing instanceof RestaurantOrder) {
                return ['order' => $existing, 'created' => false];
            }
        }

        $branch = RestaurantBranch::query()
            ->where('company_id', $companyId)
            ->findOrFail($data['branch_id']);

        // Cohérence table ↔ branche ↔ tenant (une table d'une autre branche
        // ou d'un autre tenant est refusée en 422 à la validation, doublon
        // de sécurité ici).
        if (! empty($data['table_id'])) {
            $table = RestaurantTable::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branch->id)
                ->find($data['table_id']);

            if (! $table instanceof RestaurantTable) {
                abort(422, 'Table does not belong to this branch.');
            }
        }

        if (! empty($data['pos_session_id'])) {
            $session = RestaurantPosSession::query()
                ->where('company_id', $companyId)
                ->where('branch_id', $branch->id)
                ->where('status', PosSessionStatus::OPEN->value)
                ->find($data['pos_session_id']);

            if (! $session instanceof RestaurantPosSession) {
                abort(422, 'POS session does not exist, is not open, or belongs to another branch.');
            }
        }

        $order = DB::transaction(function () use ($actor, $branch, $data): RestaurantOrder {
            return RestaurantOrder::query()->create([
                'company_id' => $actor->company_id,
                'branch_id' => $branch->id,
                'pos_session_id' => $data['pos_session_id'] ?? null,
                'order_type' => $data['order_type'],
                'table_id' => $data['table_id'] ?? null,
                'zone_id' => $data['zone_id'] ?? null,
                'covers' => $data['covers'] ?? null,
                'customer_contact_id' => $data['customer_contact_id'] ?? null,
                'status' => 'draft',
                'currency' => $branch->currency ?: 'DZD',
                'source' => $data['source'] ?? 'pos',
                'note_redacted' => $data['note_redacted'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'version' => 1,
            ]);
        });

        return ['order' => $order, 'created' => true];
    }
}
