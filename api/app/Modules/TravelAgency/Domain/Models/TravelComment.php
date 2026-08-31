<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelCommentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Commentaire modéré (TRAVEL-902, issue #6105). Contenu borné 3..1000, statut pending|approved|rejected|reported, signalement tracé.
 */
/**
 * @property int $id
 * @property string $company_id
 * @property int $article_id
 * @property string $author_type
 * @property int|null $author_user_id
 * @property string|null $author_name
 * @property string $body
 * @property string $status
 * @property Carbon|null $moderated_at
 * @property int|null $moderated_by_user_id
 * @property string|null $report_reason
 * @property Carbon|null $reported_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelComment extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelCommentFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id', 'article_id', 'author_type', 'author_user_id', 'author_name', 'body', 'status', 'moderated_at', 'moderated_by_user_id', 'report_reason', 'reported_at',
    ];

    protected $casts = [
        'moderated_at' => 'datetime',
        'reported_at' => 'datetime',
    ];
}
