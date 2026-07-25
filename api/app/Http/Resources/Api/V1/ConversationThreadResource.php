<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Notification\Domain\Models\ConversationThread;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ConversationThread
 */
class ConversationThreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Core\Auth\Domain\Models\Employee|null $actor */
        $actor = $request->user();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'employee' => $this->relationLoaded('employee') && $this->employee ? [
                'id' => $this->employee->id,
                'name' => trim(($this->employee->first_name ?? '').' '.($this->employee->last_name ?? '')),
                'matricule' => $this->employee->matricule,
            ] : null,
            'manager' => $this->relationLoaded('manager') && $this->manager ? [
                'id' => $this->manager->id,
                'name' => trim(($this->manager->first_name ?? '').' '.($this->manager->last_name ?? '')),
            ] : null,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'unread' => $actor !== null ? $this->isUnreadFor($actor) : false,
            'messages' => $this->relationLoaded('messages')
                ? ConversationMessageResource::collection($this->messages)
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
