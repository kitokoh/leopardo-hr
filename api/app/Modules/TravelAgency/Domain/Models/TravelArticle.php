<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelArticleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Article éditorial (TRAVEL-901, issue #6104). Statuts draft|published|reported|archived, publication contrôlée.
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $category_id
 * @property string $title
 * @property string $body_redacted
 * @property string $status
 * @property string $author_user_id
 * @property Carbon|null $published_at
 * @property string $moderation_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelArticle extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelArticleFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id', 'category_id', 'title', 'body_redacted', 'status', 'author_user_id', 'published_at', 'moderation_note',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    /**
     * @return HasMany<TravelLike, $this>
     */
    public function likes(): HasMany
    {
        return $this->hasMany(TravelLike::class, 'article_id');
    }

    /**
     * @return HasMany<TravelComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TravelComment::class, 'article_id');
    }

    /**
     * @return HasMany<TravelRating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(TravelRating::class, 'article_id');
    }
}
