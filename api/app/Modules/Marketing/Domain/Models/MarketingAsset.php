<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Models;

use App\Modules\Marketing\Domain\Enums\MarketingAssetKind;
use App\Modules\Marketing\Domain\Enums\MarketingAssetValidationStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Marketing OS — ressource de la médiathèque (brief §C, §D).
 *
 * Décision d'architecture majeure : les BINAIRES ne sont jamais stockés en
 * base. `disk` + `path` pointent vers le système de fichiers existant, le
 * même parti pris que `ShowcaseMedia` (disk `local`, hors webroot, servi
 * par une route contrôlée). Les grosses vidéos restent donc hors du
 * stockage base de données, conformément au brief.
 *
 * La ressource est VERSIONNÉE par (nom, version) : publier une nouvelle
 * version ne remplace jamais la ligne précédente, donc l'historique d'une
 * ancienne publication reste intact (brief §G).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $campaign_id
 * @property int|null $content_idea_id
 * @property string $name
 * @property string|null $description
 * @property MarketingAssetKind $kind
 * @property string|null $mime_type
 * @property string|null $extension
 * @property int|null $size
 * @property string|null $disk
 * @property string|null $path
 * @property string|null $preview_path
 * @property string|null $thumbnail_path
 * @property string $locale
 * @property string|null $product
 * @property string|null $objective
 * @property string|null $sector
 * @property array<int, string>|null $platforms
 * @property array<int, string>|null $tags
 * @property int $version
 * @property MarketingAssetValidationStatus $validation_status
 * @property int|null $author_id
 * @property string|null $rights
 * @property Carbon|null $rights_expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class MarketingAsset extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'campaign_id',
        'content_idea_id',
        'name',
        'description',
        'kind',
        'mime_type',
        'extension',
        'size',
        'disk',
        'path',
        'preview_path',
        'thumbnail_path',
        'locale',
        'product',
        'objective',
        'sector',
        'platforms',
        'tags',
        'version',
        'validation_status',
        'author_id',
        'rights',
        'rights_expires_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => MarketingAssetKind::class,
            'validation_status' => MarketingAssetValidationStatus::class,
            'platforms' => 'array',
            'tags' => 'array',
            'version' => 'integer',
            'size' => 'integer',
            'rights_expires_at' => 'datetime',
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

    /**
     * Publications qui utilisent cette ressource — une même ressource peut
     * alimenter plusieurs publications (brief §G).
     *
     * @return HasMany<MarketingPublication, $this>
     */
    public function publications(): HasMany
    {
        return $this->hasMany(MarketingPublication::class, 'marketing_asset_id');
    }

    /** Un binaire est-il associé ? Les contenus texte n'en ont pas. */
    public function hasFile(): bool
    {
        return $this->disk !== null && $this->path !== null;
    }

    /**
     * La ressource peut-elle être réutilisée (brief §G) ?
     *
     * Réutiliser ne signifie pas republier : cela signifie qu'une nouvelle
     * publication peut s'appuyer sur cette ressource sans enfreindre un droit
     * d'utilisation ni reprendre un livrable rejeté. La règle est volontairement
     * PERMISSIVE sur l'information absente (pas de droits déclarés = pas de
     * blocage inventé) et STRICTE sur l'information présente (droits expirés,
     * validation rejetée).
     */
    public function isReusable(): bool
    {
        return $this->reuseBlockers() === [];
    }

    /**
     * Motifs de non-réutilisation, en clair.
     *
     * `[]` = réutilisable. Chaque motif est une phrase exploitable telle
     * quelle par l'interface : pas de code opaque à retraduire côté client.
     *
     * @return list<string>
     */
    public function reuseBlockers(): array
    {
        $blockers = [];

        if ($this->validation_status === MarketingAssetValidationStatus::Rejected) {
            $blockers[] = 'Ressource rejetée en validation : elle ne peut pas être republiée en l\'état.';
        }

        if ($this->rights_expires_at !== null && $this->rights_expires_at->isPast()) {
            $blockers[] = 'Droits d\'utilisation expirés le '.$this->rights_expires_at->toDateString().' : une nouvelle publication exige un renouvellement.';
        }

        return $blockers;
    }
}
