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
 */
class TravelArticle extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $table = 'travel_articles';

    protected $fillable = ['company_id', 'category_id', 'slug', 'title', 'body_redacted', 'status', 'author_type', 'author_id', 'moderated_by_user_id', 'moderated_at', 'published_at'];

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
