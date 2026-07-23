<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Models;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * PA2-COMM-005 — Platform-wide announcement authored by a super-admin and
 * broadcast to every company (or an explicit subset of companies) on the
 * platform. Mirrors the tenant-scoped `CompanyAnnouncement` (PA2-COMM-004)
 * but lives in the `public` schema since it is not owned by any one tenant.
 *
 * @property int $id
 * @property int $created_by
 * @property string $title
 * @property string $body
 * @property string $category
 * @property string $severity
 * @property string $audience_type
 * @property Carbon|null $published_at
 * @property Carbon|null $expires_at
 * @property int $companies_count
 * @property int $recipients_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Company> $companies
 *
 * @mixin Builder<static>
 */
class PlatformAnnouncement extends Model
{
    protected $table = 'platform_announcements';

    protected $fillable = [
        'created_by',
        'title',
        'body',
        'category',
        'severity',
        'audience_type',
        'published_at',
        'expires_at',
        'companies_count',
        'recipients_count',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const CATEGORY_MAINTENANCE = 'maintenance';

    public const CATEGORY_FEATURE = 'feature';

    public const CATEGORY_INCIDENT = 'incident';

    public const CATEGORY_ACTION_REQUIRED = 'action_required';

    public const CATEGORY_NEWS = 'news';

    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_COMPANIES = 'companies';

    /** @return list<string> */
    public static function categories(): array
    {
        return [
            self::CATEGORY_MAINTENANCE,
            self::CATEGORY_FEATURE,
            self::CATEGORY_INCIDENT,
            self::CATEGORY_ACTION_REQUIRED,
            self::CATEGORY_NEWS,
        ];
    }

    /** @return list<string> */
    public static function audienceTypes(): array
    {
        return [self::AUDIENCE_ALL, self::AUDIENCE_COMPANIES];
    }

    /** @return BelongsTo<SuperAdmin, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'created_by');
    }

    /** @return BelongsToMany<Company, $this> */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(
            Company::class,
            'platform_announcement_companies',
            'platform_announcement_id',
            'company_id',
        );
    }
}
