<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryCountItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-504 (#6203) — Ressource API d'une ligne de comptage d'inventaire.
 */
/**
 * @mixin RestaurantInventoryCountItem
 */
class RestaurantInventoryCountItemResource extends JsonResource
{
    /**
     * Le cast Eloquent `decimal:3` rend `expected_qty`/`counted_qty`/
     * `variance_qty` sous forme de CHAINE ("-2.000"). Un contrat d'API
     * numerique doit exposer des nombres : on convertit explicitement, comme
     * le font deja `RestaurantOrderItemResource` et
     * `RestaurantKitchenOrderResource` pour leurs quantites. Sans cela un
     * client JS recoit "-2.000" et ne peut le comparer a -2 qu'apres
     * conversion manuelle — et l'ecart (`variance_qty`) reste inexploitable
     * pour un tri ou un calcul cote client.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'count_id' => $this->count_id,
            'ingredient_id' => $this->ingredient_id,
            'expected_qty' => (float) $this->expected_qty,
            'counted_qty' => $this->counted_qty === null ? null : (float) $this->counted_qty,
            'variance_qty' => $this->variance_qty === null ? null : (float) $this->variance_qty,
            'reason_code' => $this->reason_code,
        ];
    }
}
