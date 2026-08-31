<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Resources;

use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * RESTO-405 (#6192) — Addition d'une commande : totaux serveur recalculés
 * (sous-total, TVA, remise promo, total — minor units) + détail de la
 * commande. Interne au module (PA2-ARCH-010).
 *
 * @mixin RestaurantOrder
 */
class RestaurantBillResource extends JsonResource
{
    /**
     * @param  array{subtotal_minor: int, tax_minor: int, discount_minor: int, total_minor: int, currency: string, promotion?: \App\Modules\RestaurantManager\Domain\Models\RestaurantPromotion|null}  $totals
     */
    public function __construct(RestaurantOrder $order, private readonly array $totals)
    {
        parent::__construct($order);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var RestaurantOrder $order */
        $order = $this->resource;

        return [
            'id' => $order->id,
            'reference' => $order->reference,
            'status' => $order->status->value,
            'currency' => $order->currency,
            'items' => RestaurantOrderItemResource::collection($order->items),
            'bill' => [
                'subtotal_minor' => $this->totals['subtotal_minor'],
                'tax_minor' => $this->totals['tax_minor'],
                'discount_minor' => $this->totals['discount_minor'],
                'total_minor' => $this->totals['total_minor'],
                'currency' => $this->totals['currency'],
                'promotion_code' => isset($this->totals['promotion']) && $this->totals['promotion'] !== null
                    ? $this->totals['promotion']->code
                    : null,
            ],
        ];
    }
}
