<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Domain\Models;

use App\Modules\Recruitment\Domain\Enums\JobSourceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * BC-10 RECRUITMENT — connecteur d'agrégateur d'offres (job board externe).
 *
 * Table `public.job_sources` : volontairement NON scopée à une entreprise.
 * Une source est configurée par la PLATEFORME depuis la console super admin,
 * et les offres qu'elle fournit sont publiées sur le portail public au même
 * titre que les offres des tenants.
 *
 * Les identifiants sont stockés CHIFFRÉS (`credentials_encrypted`, cast
 * `encrypted` de Laravel) et ne sont jamais sérialisés : `$hidden` retire la
 * colonne, et la projection publique n'expose qu'un booléen
 * `has_credentials`.
 *
 * @property int $id
 * @property string $provider
 * @property string $label
 * @property JobSourceStatus $status
 * @property string|null $credentials_encrypted
 * @property array<string, mixed>|null $config
 * @property Carbon|null $last_synced_at
 * @property string|null $last_error
 * @property int $offers_imported
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class JobSource extends Model
{
    /**
     * Préfixe `public.` EXPLICITE, comme `Company` et `WebhookEvent` : le
     * search_path de l'application pointe sur le schéma du tenant courant, donc
     * un nom nu résoudrait vers `shared_tenants.job_sources` (inexistant) dès
     * qu'un tenant est sélectionné. La table est publique par conception.
     */
    protected $table = 'public.job_sources';

    protected $fillable = [
        'provider',
        'label',
        'status',
        'credentials_encrypted',
        'config',
        'last_synced_at',
        'last_error',
        'offers_imported',
    ];

    /**
     * Le secret ne sort JAMAIS d'une sérialisation : ni dans une réponse JSON
     * d'API, ni dans un log de debug qui ferait un `json_encode($model)`.
     *
     * @var list<string>
     */
    protected $hidden = [
        'credentials_encrypted',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => JobSourceStatus::class,
            'credentials_encrypted' => 'encrypted',
            'config' => 'array',
            'last_synced_at' => 'datetime',
            'offers_imported' => 'integer',
        ];
    }

    /** @return HasMany<ExternalJobOffer, $this> */
    public function offers(): HasMany
    {
        return $this->hasMany(ExternalJobOffer::class, 'source_id');
    }

    /** Un connecteur est-il utilisable (connecté ET porteur de secrets) ? */
    public function isUsable(): bool
    {
        return $this->status->canSync()
            && is_string($this->credentials_encrypted)
            && $this->credentials_encrypted !== '';
    }

    /** @param  Builder<self>  $query */
    public function scopeConnected(Builder $query): void
    {
        $query->where('status', JobSourceStatus::Connected->value);
    }
}
