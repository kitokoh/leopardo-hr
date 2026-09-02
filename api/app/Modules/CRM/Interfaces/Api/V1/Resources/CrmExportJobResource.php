<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Resources;

use App\Modules\CRM\Domain\Models\CrmExportJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sérialisation d'un job d'export CRM (issue #5729).
 *
 * Le `file_path` interne n'est jamais exposé ; seul `file_name` et le statut
 * sont visibles, et le téléchargement passe par l'endpoint dédié (auth +
 * expiration contrôlées).
 *
 * @mixin CrmExportJob
 */
final class CrmExportJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entity' => $this->entity,
            'format' => $this->format,
            'columns' => $this->columns ?? [],
            'filters' => $this->filters ?? [],
            'status' => $this->status,
            'progress' => $this->progress,
            'file_name' => $this->file_name,
            'error' => $this->error,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
