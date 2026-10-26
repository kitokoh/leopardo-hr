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
 * @property string|null $objective
 * @property int|null $daily_budget_cents
 * @property int|null $total_budget_cents
 * @property string $currency
 * @property array<string, mixed>|null $audience
 * @property string|null $utm_campaign
 * @property string|null $landing_page
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property int|null $validated_by
 * @property Carbon|null $validated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class Campaign extends Model
{
    protected $table = 'campaigns';

    /**
     * `status`, `validated_by` et `validated_at` sont volontairement ABSENTS de
     * `$fillable` : ils résultent d'une transition validée, jamais d'un
     * `create()` ou d'un `PATCH` de masse. Sinon un client pourrait créer une
     * campagne « active » validée par ses soins, ce qui vide de son sens la
     * règle de validation humaine.
     */
    protected $fillable = [
        'channel',
        'name',
        'objective',
        'daily_budget_cents',
        'total_budget_cents',
        'currency',
        'audience',
        'utm_campaign',
        'landing_page',
        'starts_on',
        'ends_on',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'audience' => 'array',
            'daily_budget_cents' => 'integer',
            'total_budget_cents' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'validated_by' => 'integer',
            'validated_at' => 'datetime',
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

    /** Dépense cumulée, en centimes. */
    public function spentCents(): int
    {
        return (int) $this->metrics()->sum('spend_cents');
    }

    /**
     * Budget consommé face au budget prévu (0..1+).
     *
     * On privilégie le budget TOTAL quand il existe : une campagne peut
     * dépasser son budget journalier sur une journée sans que ce soit une
     * alerte, alors qu'un dépassement de budget total l'est toujours. Renvoie
     * `null` si aucun budget n'est défini — mieux vaut « non suivi » qu'un
     * pourcentage calculé sur zéro.
     */
    public function budgetConsumptionRatio(): ?float
    {
        $budget = $this->total_budget_cents ?? $this->daily_budget_cents;

        if ($budget === null || $budget <= 0) {
            return null;
        }

        return $this->spentCents() / $budget;
    }

    /** Nombre de jours couverts par des relevés — la base des moyennes. */
    public function measuredDays(): int
    {
        return (int) $this->metrics()->distinct()->count('measured_on');
    }
}
