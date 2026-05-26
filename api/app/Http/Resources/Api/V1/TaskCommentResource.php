<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\TaskComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskComment
 */
class TaskCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'author_id' => $this->author_id,
            'content' => $this->content,
            'author' => $this->whenLoaded('author'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
