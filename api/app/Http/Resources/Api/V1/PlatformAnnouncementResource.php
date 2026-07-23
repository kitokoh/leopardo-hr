<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Modules\Platform\Domain\Models\PlatformAnnouncement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlatformAnnouncement
 */
class PlatformAnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'category' => $this->category,
            'severity' => $this->severity,
            'audience_type' => $this->audience_type,
            'company_ids' => $this->when(
                $this->audience_type === PlatformAnnouncement::AUDIENCE_COMPANIES,
                fn () => $this->companies->pluck('id')->values(),
            ),
            'created_by' => $this->created_by,
            'author' => $this->whenLoaded('author'),
            'companies_count' => $this->companies_count,
            'recipients_count' => $this->recipients_count,
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
