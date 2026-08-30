<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Note d'un article (TRAVEL-903, issue #6106). Un acteur = une note par cible.
 */
class TravelRating extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'article_id', 'actor_type', 'actor_user_id', 'actor_identifier', 'stars',
    ];

    protected $casts = [
        'stars' => 'integer',
    ];
}
