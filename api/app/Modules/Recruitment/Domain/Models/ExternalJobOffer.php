<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * BC-10 RECRUITMENT — offre d'emploi importée depuis un agrégateur externe.
 *
 * Table `public.external_job_offers`. La clé métier `(source_id, external_id)`
 * est UNIQUE en base : c'est elle qui rend la synchronisation idempotente.
 * Le service de synchronisation écrit par `upsert` sur cette clé, jamais par
 * `create` — deux exécutions du job convergent donc vers le même état.
 *
 * `raw_payload` conserve la charge utile d'origine : elle permet de rejouer un
 * mapping corrigé sans re-fetch, et de prouver ce que la source a réellement
 * envoyé en cas de litige.
 *
 * @property int $id
 * @property int $source_id
 * @property string $external_id
 * @property string $title
 * @property string|null $description
 * @property string $company_name
 * @property string|null $location
 * @property string|null $country
 * @property string $remote_policy
 * @property string $contract_type
 * @property float|null $salary_min
 * @property float|null $salary_max
 * @property string|null $currency
 * @property string $apply_url
 * @property Carbon|null $published_at
 * @property Carbon|null $expires_at
 * @property string $status
 * @property array<string, mixed>|null $raw_payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class ExternalJobOffer extends Model
{
    /**
     * Préfixe `public.` EXPLICITE (cf. `JobSource`) : la table est publique par
     * conception, un nom nu résoudrait vers le schéma du tenant courant.
     */
    protected $table = 'public.external_job_offers';

    protected $fillable = [
        'source_id',
        'external_id',
        'title',
        'description',
        'company_name',
        'location',
        'country',
        'remote_policy',
        'contract_type',
        'salary_min',
        'salary_max',
        'currency',
        'apply_url',
        'published_at',
        'expires_at',
        'status',
        'raw_payload',
    ];

    protected $casts = [
        'salary_min' => 'float',
        'salary_max' => 'float',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    /** @return BelongsTo<JobSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(JobSource::class, 'source_id');
    }

    /**
     * Offres visibles sur le portail public : publiées et non expirées.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });
    }
}
