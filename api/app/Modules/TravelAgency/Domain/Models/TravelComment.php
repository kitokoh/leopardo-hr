<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * TRAVEL-901/902/903 (#6104/#6105/#6106) — Comment (contenu éditorial).
 *
 * @mixin Builder<static>
 *
 * @property int $article_id
 * @property int|null $author_id
 * @property string $author_type
 * @property string $company_id
 * @property string|null $content_redacted
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $moderated_at
 * @property \Illuminate\Support\Carbon|null $reported_at
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class TravelComment extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $table = 'travel_comments';

    protected $fillable = ['company_id', 'article_id', 'author_type', 'author_id', 'content_redacted', 'status', 'moderated_by_user_id', 'moderated_at'];

    protected $casts = [
        'moderated_at' => 'datetime',
        'reported_at' => 'datetime',
    ];
}
