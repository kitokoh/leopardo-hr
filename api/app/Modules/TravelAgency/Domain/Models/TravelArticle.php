<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TRAVEL-901/902/903 (#6104/#6105/#6106) — Article (contenu éditorial).
 *
 * @mixin Builder<static>
 *
 * @property int|null $author_id
 * @property string|null $author_type
 * @property string $body_redacted
 * @property int|null $category_id
 * @property string $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $moderated_at
 * @property string|null $moderation_note
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property string|null $slug
 * @property string $status
 * @property string $title
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class TravelArticle extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $table = 'travel_articles';

    protected $fillable = ['company_id', 'category_id', 'slug', 'title', 'body_redacted', 'status', 'author_type', 'author_id', 'moderated_by_user_id', 'moderated_at', 'published_at'];

    protected $casts = [
        'published_at' => 'datetime',
        'moderated_at' => 'datetime',
    ];

    public function likes(): HasMany
    {
        return $this->hasMany(TravelLike::class, 'article_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TravelComment::class, 'article_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(TravelRating::class, 'article_id');
    }
}
