<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Issue #1432 — etat de publication par reseau social pour un
 * `social_post` donne (`docs/specifications/MODULE_MARKETING.md` §3.1,
 * table `post_publications`). Un post cible potentiellement plusieurs
 * plateformes en une seule requete Ayrshare ; chaque plateforme a son
 * propre `postId` et peut reussir ou echouer independamment des autres
 * (ex: publication reussie sur LinkedIn mais rejetee sur X/Twitter pour
 * depassement de longueur). `SocialPost::status`/`provider_post_ref`
 * restent l'etat agrege global ; cette table porte le detail par
 * plateforme, consomme par le futur dashboard analytique
 * (`docs/specifications/MODULE_MARKETING.md` §4.1.3).
 *
 * @property int $id
 * @property string $company_id
 * @property int $social_post_id
 * @property int $social_account_id
 * @property string $platform
 * @property string $status
 * @property string|null $external_post_id
 * @property string|null $error_message
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class PostPublication extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'social_post_id',
        'social_account_id',
        'platform',
        'status',
        'external_post_id',
        'error_message',
        'published_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SocialPost, $this> */
    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }

    /** @return BelongsTo<SocialAccount, $this> */
    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }
}
