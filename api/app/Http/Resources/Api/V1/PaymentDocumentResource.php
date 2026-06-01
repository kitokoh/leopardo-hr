<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\PaymentDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentDocument
 */
class PaymentDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'payroll_run_id' => $this->payroll_run_id,
            'pay_slip_id' => $this->pay_slip_id,
            'salary_advance_id' => $this->salary_advance_id,
            'document_type' => $this->document_type,
            'status' => $this->status,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'is_downloadable' => $this->status === PaymentDocument::STATUS_AVAILABLE && $this->path !== null,
            'error_message' => $this->when($this->status === PaymentDocument::STATUS_FAILED, $this->error_message),
            'metadata' => $this->metadata ?? [],
            'generated_at' => $this->generated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
