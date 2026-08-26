<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Payroll\Domain\Models\PayrollPaymentOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PayrollPaymentOrderItem
 */
class PayrollPaymentOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_order_id' => $this->payment_order_id,
            'employee_id' => $this->employee_id,
            'net_amount' => $this->net_amount,
            'iban' => $this->iban,
        ];
    }
}
