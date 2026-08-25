<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Payroll\Domain\Models\PayrollPaymentOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PayrollPaymentOrder
 */
class PayrollPaymentOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_run_id' => $this->payroll_run_id,
            'status' => $this->status,
            'format' => $this->format,
            'file_path' => $this->file_path,
            'total_amount' => $this->total_amount,
            'transfer_count' => $this->transfer_count,
            'bank_reference' => $this->bank_reference,
            'executed_by' => $this->executed_by,
            'executed_at' => $this->executed_at?->toIso8601String(),
            'reconciled_at' => $this->reconciled_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'items' => PayrollPaymentOrderItemResource::collection($this->whenLoaded('items')),
            'payroll_run' => $this->whenLoaded('payrollRun'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
