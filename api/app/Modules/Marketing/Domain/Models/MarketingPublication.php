<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Models;

use App\Modules\Marketing\Domain\Enums\MarketingPublicationMode;
use App\Modules\Marketing\Domain\Enums\MarketingPublicationStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Marketing OS — publication d'un contenu sur UNE plateforme (brief §E, §F, §G).
 *
 * Une ligne par plateforme cible : c'est ce qui permet de suivre
 * « cette vidéo a-t-elle déjà été publiée ? sur quelles plateformes ? à
 * quelle date ? avec quelle description ? quel compte l'a publiée ? quel
 * lien ? quels résultats ? ».
 *
 * Table APPEND-ONLY côté logique métier : une nouvelle version de contenu
 * crée une nouvelle publication, elle n'écrase jamais la ligne existante.
 *
 * `mode` est toujours exposé (`direct` vs `assisted`) : l'interface ne peut
 * donc pas présenter comme automatisée une publication qui exige une
 * intervention manuelle.
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $content_idea_id
 * @property int|null $marketing_asset_id
 * @property int|null $social_post_id
 * @property string $platform
 * @property MarketingPublicationMode $mode
 * @property MarketingPublicationStatus $status
 * @property string|null $title
 * @property string|null $body
 * @property string|null $hashtags
 * @property string|null $call_to_action
 * @property string|null $destination_url
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $published_at
 * @property string|null $published_url
 * @property string|null $platform_account
 * @property array<string, mixed>|null $stats
 * @property string|null $stats_source
 * @property string|null $error_message
 * @property int $attempts
 * @property array<int, mixed>|null $attempt_history
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class MarketingPublication extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'content_idea_id',
        'marketing_asset_id',
        'social_post_id',
        'platform',
        'mode',
        'status',
        'title',
        'body',
        'hashtags',
        'call_to_action',
        'destination_url',
        'scheduled_at',
        'published_at',
        'published_url',
        'platform_account',
        'stats',
        'stats_source',
        'error_message',
        'attempts',
        'attempt_history',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'mode' => MarketingPublicationMode::class,
            'status' => MarketingPublicationStatus::class,
            'stats' => 'array',
            'attempt_history' => 'array',
            'attempts' => 'integer',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
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

    /** La publication a-t-elle été réellement mise en ligne ? */
    public function isPublished(): bool
    {
        return $this->status === MarketingPublicationStatus::Published
            && $this->published_at !== null;
    }

    /** Le mode est-il réellement automatisé (API) ? */
    public function isDirect(): bool
    {
        return $this->mode === MarketingPublicationMode::Direct;
    }
}
