<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Resources;

use App\Modules\CRM\Domain\Models\CrmImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * #5714 — Sérialisation d'une session d'import CSV CRM.
 *
 * PII protégée : `preview_data` est déjà masqué par le service d'import ;
 * les lignes brutes (`raw_rows`) ne sont JAMAIS exposées ; `errors` et
 * `result` restent bornés.
 *
 * @property CrmImport $resource
 */
class CrmImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $import = $this->resource;

        return [
            'id' => $import->id,
            'entity_type' => $import->entity_type->value,
            'filename' => $import->filename,
            'status' => $import->status->value,
            'total_rows' => $import->total_rows,
            'valid_rows' => $import->valid_rows,
            'error_rows' => $import->error_rows,
            'columns' => $import->columns,
            'preview_data' => $import->preview_data,
            'errors' => $import->errors,
            'result' => $import->result,
            'created_by' => $import->creator?->only(['id', 'first_name', 'last_name']),
            'committed_at' => $import->committed_at?->toIso8601String(),
            'cancelled_at' => $import->cancelled_at?->toIso8601String(),
            'created_at' => $import->created_at?->toIso8601String(),
        ];
    }
}
