<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * TRAVEL-901/902/903 (#6104/#6105/#6106) — ArticleCategory (contenu éditorial).
 *
 * @mixin Builder<static>
 */
class TravelArticleCategory extends Model
{
    use BelongsToCompany;

    use HasFactory;

    protected $table = 'travel_article_categories';
    protected $fillable = ["company_id", "slug", "name"];
}
