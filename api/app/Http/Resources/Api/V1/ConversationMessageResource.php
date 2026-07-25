<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Notification\Domain\Models\ConversationMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ConversationMessage
 */
class ConversationMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_thread_id' => $this->conversation_thread_id,
            'author_id' => $this->author_id,
            'author' => $this->relationLoaded('author') && $this->author ? [
                'id' => $this->author->id,
                'name' => trim(($this->author->first_name ?? '').' '.($this->author->last_name ?? '')),
            ] : null,
            'body' => $this->body,
            'attachment' => $this->hasAttachment() ? [
                'original_name' => $this->attachment_original_name,
                'mime_type' => $this->attachment_mime_type,
                'size' => $this->attachment_size,
                'url' => route('conversations.messages.attachment', [$this->conversation_thread_id, $this->id]),
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
