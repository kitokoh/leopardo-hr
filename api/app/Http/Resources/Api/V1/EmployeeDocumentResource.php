<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\HR\Domain\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmployeeDocument
 */
class EmployeeDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'type' => $this->type,
            'type_label' => __('hr_documents.type_'.$this->type),
            'status' => $this->status,
            'status_label' => __('hr_documents.status_'.$this->status),
            'document_date' => $this->document_date?->toDateString(),
            'reference' => $this->reference,
            'url' => $this->url,
            'notes' => $this->notes,
            'uploaded_by' => $this->uploaded_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
