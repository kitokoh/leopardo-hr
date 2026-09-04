<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Resources;

use App\Modules\TravelAgency\Domain\Models\TravelArticle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * TRAVEL-901 (#6104) — Représentation API d'un article.
 *
 * @mixin TravelArticle
 *
 * @property-read int|null $category_id
 * @property-read string $slug
 * @property-read string $title
 * @property-read string $body_redacted
 * @property-read string $status
 * @property-read string|null $author_type
 * @property-read int|null $author_id
 * @property-read Carbon|null $published_at
 * @property-read Carbon|null $moderated_at
 */
class TravelArticleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'slug' => $this->slug,
            'title' => $this->title,
            'body' => $this->body_redacted,
            'status' => $this->status,
            'author_type' => $this->author_type,
            'author_id' => $this->author_id,
            'published_at' => $this->published_at,
            'moderated_at' => $this->moderated_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }


}