<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $company_id
 * @property int $social_account_id
 * @property int|null $created_by
 * @property string $content
 * @property array<int, string>|null $media_paths
 * @property array<int, string> $target_platforms
 * @property string $status
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $published_at
 * @property string|null $provider_post_ref
 * @property string|null $error_message
 * @property int $attempts
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class SocialPost extends Model
{
    use BelongsToCompany;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHING = 'publishing';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'social_account_id',
        'created_by',
        'content',
        'media_paths',
        'target_platforms',
        'status',
        'scheduled_at',
        'published_at',
        'provider_post_ref',
        'error_message',
        'attempts',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'media_paths' => 'array',
            'target_platforms' => 'array',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /** @return BelongsTo<SocialAccount, $this> */
    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function isDue(): bool
    {
        return $this->status === self::STATUS_SCHEDULED
            && $this->scheduled_at !== null
            && $this->scheduled_at->isPast();
    }
}
