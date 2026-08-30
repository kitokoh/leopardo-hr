<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TRAVEL-902 (#6105) — Représentation API d'un commentaire.
 *
 * @mixin TravelComment
 */
class TravelCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'article_id' => $this->article_id,
            'author_type' => $this->author_type,
            'author_id' => $this->author_id,
            'content' => $this->content_redacted,
            'status' => $this->status,
            'moderated_at' => $this->moderated_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
