<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Remise caisse d'un règlement COD (DELIVERY-205, issue #6289).
 */
final class DeliverySettlementCollectRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'collected_minor' => ['required', 'integer', 'min:0'],
            'commission_minor' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
