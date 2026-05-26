<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CabinetFolder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CabinetFolder
 */
class CabinetFolderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'children' => CabinetFolderResource::collection($this->whenLoaded('children')),
            'documents' => CabinetDocumentResource::collection($this->whenLoaded('documents')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
