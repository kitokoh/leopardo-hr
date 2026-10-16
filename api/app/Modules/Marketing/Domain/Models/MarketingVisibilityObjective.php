<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Models;

use App\Modules\Marketing\Domain\Enums\MarketingVisibilityChannel;
use App\Modules\Marketing\Domain\Enums\MarketingVisibilityMetricsSource;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Marketing OS — objectif de visibilite SEO/SEA/AEO/GEO (brief §H, #8531).
 *
 * Relie un CONTENU (idee, ressource, publication) a une INTENTION de
 * visibilite : mot-cle, intention de recherche, page de destination,
 * campagne publicitaire, questions auxquelles le contenu repond.
 *
 * Ce modele ne remplace pas le centre de visibilite existant (vitrine :
 * `robots.ts`, `JsonLd`, `ai-search.ts`, sitemap) : il stocke l'intention et
 * la mesure cote administration, et REFERENCE la page de destination de la
 * vitrine. Aucun systeme parallele n'est cree.
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $campaign_id
 * @property int|null $content_idea_id
 * @property int|null $marketing_asset_id
 * @property int|null $marketing_publication_id
 * @property MarketingVisibilityChannel $channel
 * @property string|null $keyword
 * @property string|null $search_intent
 * @property string|null $destination_url
 * @property string|null $ad_campaign_ref
 * @property string $locale
 * @property string|null $sector
 * @property array<int, string>|null $questions_answered
 * @property array<string, mixed>|null $metrics
 * @property MarketingVisibilityMetricsSource $metrics_source
 * @property string|null $metrics_note
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class MarketingVisibilityObjective extends Model
{
    use BelongsToCompany;

    protected $table = 'marketing_visibility_objectives';

    protected $fillable = [
        'company_id',
        'campaign_id',
        'content_idea_id',
        'marketing_asset_id',
        'marketing_publication_id',
        'channel',
        'keyword',
        'search_intent',
        'destination_url',
        'ad_campaign_ref',
        'locale',
        'sector',
        'questions_answered',
        'metrics',
        'metrics_source',
        'metrics_note',
        'notes',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channel' => MarketingVisibilityChannel::class,
            'metrics_source' => MarketingVisibilityMetricsSource::class,
            'questions_answered' => 'array',
            'metrics' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MarketingCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    /** @return BelongsTo<ContentIdea, $this> */
    public function contentIdea(): BelongsTo
    {
        return $this->belongsTo(ContentIdea::class, 'content_idea_id');
    }

    /** @return BelongsTo<MarketingAsset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(MarketingAsset::class, 'marketing_asset_id');
    }

    /**
     * L'objectif est-il rattache a au moins un contenu ?
     *
     * Un objectif sans contenu est legitime (l'intention precede la
     * production) : l'interface le signale comme « a produire ».
     */
    public function hasLinkedContent(): bool
    {
        return $this->content_idea_id !== null
            || $this->marketing_asset_id !== null
            || $this->marketing_publication_id !== null;
    }

    /**
     * Les indicateurs sont-ils MESURES (et non estimes ou absents) ?
     *
     * L'interface s'appuie sur ce predicat pour ne jamais presenter une
     * estimation comme une mesure.
     */
    public function hasMeasuredMetrics(): bool
    {
        return $this->metrics_source->isMeasured()
            && is_array($this->metrics)
            && $this->metrics !== [];
    }
}
