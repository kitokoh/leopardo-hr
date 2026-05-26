<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'created_by' => $this->created_by,
            'assigned_to' => $this->assigned_to,
            'project_id' => $this->project_id,
            'due_date' => $this->due_date?->toIso8601String(),
            'priority' => $this->priority,
            'status' => $this->status,
            'category' => $this->category,
            'visibility' => $this->visibility,
            'checklist' => $this->checklist,
            'comments' => TaskCommentResource::collection($this->whenLoaded('comments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
