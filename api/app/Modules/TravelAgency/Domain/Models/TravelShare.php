<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * TRAVEL-901/902/903 (#6104/#6105/#6106) — Share (contenu éditorial).
 *
 * @mixin Builder<static>
use Illuminate\Database\Eloquent\Model;

/**
 * Partage d'un article (TRAVEL-903, issue #6106).
 */
class TravelShare extends Model
{
    use BelongsToCompany;

    use HasFactory;

    protected $table = 'travel_shares';
    protected $fillable = ["company_id", "article_id", "channel", "actor_type", "actor_id"];
    protected $fillable = [
        'company_id', 'article_id', 'actor_type', 'actor_user_id', 'actor_identifier', 'channel',
    ];
}
