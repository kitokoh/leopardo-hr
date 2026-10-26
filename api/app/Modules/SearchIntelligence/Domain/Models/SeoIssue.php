<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Models;

use App\Modules\SearchIntelligence\Domain\Enums\SeoIssueSeverity;
use App\Modules\SearchIntelligence\Domain\Enums\SeoIssueStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * GSI-2 (#8513) — anomalie SEO détectée sur une page du parc.
 *
 * @property int $id
 * @property int|null $seo_page_id
 * @property string $type
 * @property SeoIssueSeverity $severity
 * @property SeoIssueStatus $status
 * @property string $title
 * @property string|null $detail
 * @property Carbon $detected_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SeoPage|null $page
 *
 * @mixin Builder<static>
 */
class SeoIssue extends Model
{
    protected $table = 'seo_issues';

    protected $fillable = [
        'seo_page_id',
        'type',
        'severity',
        'status',
        'title',
        'detail',
        'detected_at',
        'resolved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'severity' => SeoIssueSeverity::class,
            'status' => SeoIssueStatus::class,
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SeoPage, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(SeoPage::class, 'seo_page_id');
    }

    /**
     * Anomalies encore actionnables : ni résolues, ni ignorées. C'est la seule
     * population qui doit alimenter la file de traitement.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActionable(Builder $query): void
    {
        $query->whereNotIn('status', [
            SeoIssueStatus::Resolved->value,
            SeoIssueStatus::Ignored->value,
        ]);
    }
}
