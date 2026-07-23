<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Notification\Domain\Models\CompanyAnnouncement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CompanyAnnouncement
 */
class CompanyAnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'priority' => $this->priority,
            'audience_type' => $this->audience_type,
            'audience_department_id' => $this->audience_department_id,
            'audience_employee_id' => $this->audience_employee_id,
            'created_by' => $this->created_by,
            'author' => $this->whenLoaded('author'),
            'recipients_count' => $this->recipients_count,
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
