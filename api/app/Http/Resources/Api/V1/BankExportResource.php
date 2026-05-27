<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\BankExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankExport
 */
class BankExportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_run_id' => $this->payroll_run_id,
            'format' => $this->format,
            'file_path' => $this->file_path,
            'total_amount' => $this->total_amount,
            'transfer_count' => $this->transfer_count,
            'status' => $this->status,
            'generated_at' => $this->generated_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'payroll_run' => $this->whenLoaded('payrollRun'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
