<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Commentaire modéré (TRAVEL-902, issue #6105). Contenu borné 3..1000, statut pending|approved|rejected|reported, signalement tracé.
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
