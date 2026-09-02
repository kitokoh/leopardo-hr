<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * TRAVEL-901/902/903 (#6104/#6105/#6106) — ArticleCategory (contenu éditorial).
 *
 * @mixin Builder<static>
 */
class TravelArticleCategory extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $table = 'travel_article_categories';

    protected $fillable = ['company_id', 'slug', 'name'];


}