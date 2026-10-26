<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Models;

use App\Modules\SearchIntelligence\Domain\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * GSI-3 (#8514) — campagne SEA.
 *
 * @property int $id
 * @property string $channel
 * @property string $name
 * @property CampaignStatus $status
 * @property int|null $daily_budget_cents
 * @property string $currency
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class Campaign extends Model
{
    protected $table = 'campaigns';

    protected $fillable = [
        'channel',
        'name',
        'status',
        'daily_budget_cents',
        'currency',
        'starts_on',
        'ends_on',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'daily_budget_cents' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /** @return HasMany<CampaignMetric, $this> */
    public function metrics(): HasMany
    {
        return $this->hasMany(CampaignMetric::class, 'campaign_id');
    }

    /** @param  Builder<self>  $query */
    public function scopeRunning(Builder $query): void
    {
        $query->where('status', CampaignStatus::Active->value);
    }
}
