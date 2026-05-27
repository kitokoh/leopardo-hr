<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CabinetShare;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CabinetShare
 */
class CabinetShareResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'shared_with_id' => $this->shared_with_id,
            'permission' => $this->permission,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'document' => new CabinetDocumentResource($this->whenLoaded('document')),
            'shared_with' => $this->whenLoaded('sharedWith'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
