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
 * Le statut suit le workflow `detected → recommended → approved → applied →
 * verified` (+ `dismissed`). Aucune transition n'est faite directement sur ce
 * modèle : `SeoIssueWorkflow` est le seul point d'écriture du statut, parce
 * qu'il porte l'audit.
 *
 * @property int $id
 * @property int|null $seo_page_id
 * @property string $type
 * @property SeoIssueSeverity $severity
 * @property SeoIssueStatus $status
 * @property string $title
 * @property string|null $detail
 * @property string|null $recommendation
 * @property string|null $dismissed_reason
 * @property Carbon $detected_at
 * @property Carbon|null $applied_at
 * @property Carbon|null $verified_at
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
        'recommendation',
        'dismissed_reason',
        'detected_at',
        'applied_at',
        'verified_at',
        'resolved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'severity' => SeoIssueSeverity::class,
            'status' => SeoIssueStatus::class,
            'detected_at' => 'datetime',
            'applied_at' => 'datetime',
            'verified_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SeoPage, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(SeoPage::class, 'seo_page_id');
    }

    /**
     * Anomalies encore actionnables : c'est la seule population qui doit
     * alimenter la file de traitement. `applied` en est exclu — l'action est
     * faite, il reste à vérifier au prochain scan.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActionable(Builder $query): void
    {
        $query->whereIn('status', [
            SeoIssueStatus::Detected->value,
            SeoIssueStatus::Recommended->value,
            SeoIssueStatus::Approved->value,
        ]);
    }
}
