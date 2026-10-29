<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Models;

use App\Modules\Marketing\Domain\Enums\MarketingCampaignStatus;
use App\Modules\Marketing\Domain\Enums\MarketingObjective;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Marketing OS — campagne marketing d'un tenant (brief §B, §I).
 *
 * Une campagne regroupe ses idées, ses ressources et ses publications.
 * C'est aussi le filtre de verticale métier (`sector`) demandé par le
 * brief : préparer une campagne « restaurants » sans mélanger les
 * ressources d'une autre verticale.
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property string|null $description
 * @property MarketingObjective $objective
 * @property string|null $audience
 * @property string|null $sector
 * @property string|null $product
 * @property string|null $key_message
 * @property string|null $channel
 * @property string $locale
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $owner_id
 * @property string|null $budget_amount
 * @property string $budget_currency
 * @property MarketingCampaignStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class MarketingCampaign extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'objective',
        'audience',
        'sector',
        'product',
        'key_message',
        'channel',
        'locale',
        'starts_at',
        'ends_at',
        'owner_id',
        'budget_amount',
        'budget_currency',
        'status',
    ];

    /**
     * Valeurs par defaut alignees sur le schema SQL.
     *
     * Eloquent ne lit JAMAIS les `default()` SQL : sans ces valeurs, une
     * campagne creee sans statut explicite expose `null` a tout appelant qui
     * lit le modele juste apres `save()` (meme classe de bug que
     * MarketingAsset, #8396).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'objective' => 'notoriete',
        'locale' => 'fr',
        'budget_currency' => 'EUR',
        'status' => 'draft',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'objective' => MarketingObjective::class,
            'status' => MarketingCampaignStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return HasMany<ContentIdea, $this> */
    public function ideas(): HasMany
    {
        return $this->hasMany(ContentIdea::class, 'campaign_id');
    }

    /** @return HasMany<MarketingAsset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(MarketingAsset::class, 'campaign_id');
    }
}
