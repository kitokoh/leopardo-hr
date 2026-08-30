<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * TRAVEL-901/902/903 (#6104/#6105/#6106) — Rating (contenu éditorial).
 *
 * @mixin Builder<static>
 */
class TravelRating extends Model
{
    use BelongsToCompany;

    use HasFactory;

    protected $table = 'travel_ratings';
    protected $fillable = ["company_id", "article_id", "actor_type", "actor_id", "rating"];
    protected $casts = ["rating" => "integer"];
}
