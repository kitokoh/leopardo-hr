<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Models;

use App\Modules\SearchIntelligence\Domain\Enums\MetricSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * GSI-3 (#8514) — relevé de performance d'une campagne, daté et sourcé.
 *
 * Pas de colonne « calculée » et pas de valeur par défaut sur les volumes :
 * un relevé qui n'aurait ni dépense ni clic n'est pas un relevé, l'API refuse
 * l'insertion plutôt que de stocker un zéro trompeur.
 *
 * @property int $id
 * @property int $campaign_id
 * @property Carbon $measured_on
 * @property MetricSource $source
 * @property int|null $integration_id
 * @property int $spend_cents
 * @property int $impressions
 * @property int $clicks
 * @property int $conversions
 * @property int $revenue_cents
 *
 * @mixin Builder<static>
 */
class CampaignMetric extends Model
{
    protected $table = 'campaign_metrics';

    protected $fillable = [
        'campaign_id',
        'measured_on',
        'source',
        'integration_id',
        'spend_cents',
        'impressions',
        'clicks',
        'conversions',
        'revenue_cents',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'source' => MetricSource::class,
            'measured_on' => 'date',
            'spend_cents' => 'integer',
            'impressions' => 'integer',
            'clicks' => 'integer',
            'conversions' => 'integer',
            'revenue_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    /**
     * Coût par acquisition, en centimes — `null` quand il n'y a aucune
     * conversion : on ne divise pas par zéro et on n'affiche pas un CPO
     * infini, on affiche « pas encore de conversion ».
     */
    public function costPerConversionCents(): ?int
    {
        if ($this->conversions <= 0) {
            return null;
        }

        return (int) round($this->spend_cents / $this->conversions);
    }

    /** Taux de clic (0..1) — `null` si aucune impression. */
    public function clickThroughRate(): ?float
    {
        if ($this->impressions <= 0) {
            return null;
        }

        return $this->clicks / $this->impressions;
    }
}
