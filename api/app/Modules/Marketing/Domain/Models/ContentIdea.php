<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Models;

use App\Modules\Marketing\Domain\Enums\ContentIdeaStatus;
use App\Modules\Marketing\Domain\Enums\MarketingContentFormat;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Marketing OS — idée / brief de production (brief §B, §D).
 *
 * Porte à la fois le cadrage éditorial (objectif, cible, canal, format,
 * dates, responsable, budget) et la fiche studio (`brief`, JSON libre :
 * concept, script, storyboard, scènes, narration, montage, prompts vidéo
 * et image, ressources, format/durée, versions linguistiques).
 *
 * `production_task_id` relie l'idée à une tâche de production par simple
 * identifiant : aucun import croisé de module n'est autorisé (#5584).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $campaign_id
 * @property string $title
 * @property string|null $description
 * @property string|null $objective
 * @property MarketingContentFormat $format
 * @property string|null $channel
 * @property string $locale
 * @property string|null $audience
 * @property string|null $product
 * @property string|null $sector
 * @property string|null $key_message
 * @property Carbon|null $production_due_at
 * @property Carbon|null $publish_due_at
 * @property int|null $owner_id
 * @property string|null $budget_amount
 * @property string $budget_currency
 * @property ContentIdeaStatus $status
 * @property string|null $next_action
 * @property array<string, mixed>|null $brief
 * @property int|null $production_task_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class ContentIdea extends Model
{
    use BelongsToCompany;

    protected $table = 'content_ideas';

    protected $fillable = [
        'company_id',
        'campaign_id',
        'title',
        'description',
        'objective',
        'format',
        'channel',
        'locale',
        'audience',
        'product',
        'sector',
        'key_message',
        'production_due_at',
        'publish_due_at',
        'owner_id',
        'budget_amount',
        'budget_currency',
        'status',
        'next_action',
        'brief',
        'production_task_id',
    ];

    /**
     * Valeurs par defaut alignees sur le schema SQL.
     *
     * Eloquent ne lit JAMAIS les `default()` SQL : sans ces valeurs, une idee
     * creee sans format/statut explicite expose `null` en memoire juste apres
     * `save()` (meme classe de bug que MarketingAsset, #8396).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'format' => 'other',
        'locale' => 'fr',
        'budget_currency' => 'EUR',
        'status' => 'idea',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'format' => MarketingContentFormat::class,
            'status' => ContentIdeaStatus::class,
            'brief' => 'array',
            'production_due_at' => 'datetime',
            'publish_due_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MarketingCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    /** @return HasMany<MarketingAsset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(MarketingAsset::class, 'content_idea_id');
    }

    /** @return HasMany<MarketingPublication, $this> */
    public function publications(): HasMany
    {
        return $this->hasMany(MarketingPublication::class, 'content_idea_id');
    }

    /** Une idée non terminée est-elle en retard sur sa date de production ? */
    public function isProductionLate(): bool
    {
        return $this->production_due_at !== null
            && $this->production_due_at->isPast()
            && $this->status->isOpen();
    }
}
