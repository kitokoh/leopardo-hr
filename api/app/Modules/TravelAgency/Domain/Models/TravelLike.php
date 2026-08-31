<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Like d'un article (TRAVEL-903, issue #6106). Unicité (tenant, article, acteur) — un acteur = un like.
 */
/**
 * @property int $id
 * @property string $company_id
 * @property int $article_id
 * @property string $actor_type
 * @property int|null $actor_user_id
 * @property string|null $actor_identifier
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelLike extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'article_id', 'actor_type', 'actor_user_id', 'actor_identifier',
    ];
}
