<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * TRAVEL-901/902/903 (#6104/#6105/#6106) — Comment (contenu éditorial).
 *
 * @mixin Builder<static>
 */
class TravelComment extends Model
{
    use BelongsToCompany;

    use HasFactory;

    protected $table = 'travel_comments';
    protected $fillable = ["company_id", "article_id", "author_type", "author_id", "content_redacted", "status", "moderated_by_user_id", "moderated_at"];
}
