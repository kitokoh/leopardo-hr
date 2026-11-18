<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantStockLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-501 (#6200) — Ressource API d'un niveau de stock.
 *
 * @mixin RestaurantStockLevel
 */
class RestaurantStockLevelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'ingredient_id' => $this->ingredient_id,
            // Les attributs `decimal:3` sont castés en CHAÎNES par Eloquent
            // (« 3.000 »). Les sérialiser tel quel exposait des chaînes là où
            // le contrat annonce des nombres : un client qui compare ou somme
            // ces champs (`data.quantity <= seuil`) recevait `"3.000"`, et une
            // comparaison de chaînes est fausse (`"10.000" < "9.000"`).
            // `is_below_threshold` castait déjà en float en interne — la
            // ressource est désormais cohérente avec elle-même.
            // `quantity` est `decimal(12,3) NOT NULL DEFAULT 0` : le cast
            // `decimal:3` en fait toujours une chaîne numérique, jamais `null`.
            // La garde `=== null` qui figurait ici était donc morte — PHPStan
            // la signalait à juste titre (identical.alwaysFalse).
            'quantity' => (float) $this->quantity,
            'avg_cost_minor' => $this->avg_cost_minor,
            'reorder_level' => $this->reorder_level === null ? null : (float) $this->reorder_level,
            'alert_threshold' => $this->alert_threshold === null ? null : (float) $this->alert_threshold,
            'is_below_threshold' => $this->alert_threshold !== null
                && (float) $this->quantity <= (float) $this->alert_threshold,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
