<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Models;

use App\Core\Tenant\Domain\Models\Company;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Domaine personnalisé rattaché à une Page (BC PAGES, #8726).
 *
 * ## Le host est unique GLOBALEMENT, pas par tenant
 *
 * C'est la contrainte qui décide de tout le reste. Deux tenants qui
 * revendiqueraient `chez-lea.sn` ne produiraient pas « deux Pages derrière un
 * domaine » mais un routage non déterministe : le renderer servirait l'une ou
 * l'autre selon la ligne gagnante de la requête. L'unicité est donc posée en
 * base, et le premier arrivé gagne — le second reçoit un refus explicite, pas
 * une file d'attente.
 *
 * ## La machine à états est fail-closed
 *
 * Une Page n'est servie sur son domaine que si `status = active`. Tous les
 * autres états (`pending_dns`, `pending_certificate`, `failed`, `expired`,
 * `removed`) servent un 404 — jamais un contenu approximatif. C'est
 * délibérément pessimiste : mieux vaut une Page inaccessible pendant une heure
 * qu'une Page servie sur un domaine que quelqu'un d'autre contrôle.
 *
 * @property int $id
 * @property string $company_id
 * @property int $page_id
 * @property string $host
 * @property string $status
 * @property string $provider
 * @property string|null $provider_reference
 * @property string|null $certificate_status
 * @property Carbon|null $certificate_expires_at
 * @property Carbon|null $verified_at
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $removed_at
 * @property int $check_attempts
 * @property string|null $last_error
 * @property array<string, mixed>|null $dns_instructions
 * @property bool $is_apex
 * @property bool $is_wildcard
 */
class PageCustomDomain extends Model
{
    use BelongsToCompany;

    protected $table = 'page_custom_domains';

    public const STATUS_PENDING_DNS = 'pending_dns';

    public const STATUS_PENDING_CERTIFICATE = 'pending_certificate';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REMOVED = 'removed';

    /**
     * États dans lesquels le host peut servir du trafic.
     *
     * @var list<string>
     */
    public const SERVING_STATUSES = [self::STATUS_ACTIVE];

    /**
     * Nombre de vérifications DNS avant de basculer en `failed`.
     *
     * Un TXT absent au bout de 20 contrôles est presque toujours une erreur de
     * saisie côté tenant, pas une propagation lente. Continuer à interroger le
     * DNS indéfiniment ne fait que consommer du quota pour rien.
     */
    public const MAX_CHECK_ATTEMPTS = 20;

    /**
     * Au-delà de ce délai avant expiration, on renouvelle au lieu d'attendre.
     */
    public const CERTIFICATE_RENEWAL_WINDOW_DAYS = 14;

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'page_id',
        'host',
        'status',
        'provider',
        'provider_reference',
        'certificate_status',
        'certificate_expires_at',
        'verified_at',
        'last_checked_at',
        'removed_at',
        'check_attempts',
        'last_error',
        'dns_instructions',
        'is_apex',
        'is_wildcard',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'certificate_expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'removed_at' => 'datetime',
            'check_attempts' => 'integer',
            'is_apex' => 'boolean',
            'is_wildcard' => 'boolean',
            'dns_instructions' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Le host peut-il servir du trafic maintenant ?
     *
     * On revérifie la date d'expiration du certificat en plus du statut : un
     * certificat expiré avec un statut `active` resté en base (webhook perdu,
     * job arrêté) servirait une erreur TLS à tous les visiteurs. Le statut est
     * une intention, la date est un fait.
     */
    public function canServe(?Carbon $now = null): bool
    {
        if (! in_array($this->status, self::SERVING_STATUSES, true)) {
            return false;
        }

        if ($this->removed_at !== null) {
            return false;
        }

        $now ??= Carbon::now();

        if ($this->certificate_expires_at !== null && $this->certificate_expires_at->lessThanOrEqualTo($now)) {
            return false;
        }

        return true;
    }

    /**
     * Le certificat doit-il être renouvelé bientôt ?
     */
    public function needsCertificateRenewal(?Carbon $now = null): bool
    {
        if ($this->certificate_expires_at === null) {
            return true;
        }

        $now ??= Carbon::now();

        return $this->certificate_expires_at->lessThanOrEqualTo(
            $now->copy()->addDays(self::CERTIFICATE_RENEWAL_WINDOW_DAYS)
        );
    }

    public function markDnsPending(string $error = 'enregistrement TXT introuvable'): void
    {
        $this->status = self::STATUS_PENDING_DNS;
        $this->check_attempts = $this->check_attempts + 1;
        $this->last_checked_at = Carbon::now();
        $this->last_error = $error;

        if ($this->check_attempts >= self::MAX_CHECK_ATTEMPTS) {
            $this->status = self::STATUS_FAILED;
            $this->last_error = sprintf(
                'vérification abandonnée après %d tentatives : %s',
                $this->check_attempts,
                $error,
            );
        }
    }

    public function markVerified(): void
    {
        $this->verified_at = Carbon::now();
        $this->last_checked_at = Carbon::now();
        $this->last_error = null;
    }

    public function markActive(?Carbon $certificateExpiresAt = null): void
    {
        $this->status = self::STATUS_ACTIVE;
        $this->certificate_status = 'active';
        if ($certificateExpiresAt !== null) {
            $this->certificate_expires_at = $certificateExpiresAt;
        }
        $this->last_error = null;
    }

    public function markCertificatePending(string $detail = 'validation en cours'): void
    {
        $this->status = self::STATUS_PENDING_CERTIFICATE;
        $this->certificate_status = 'pending';
        $this->last_checked_at = Carbon::now();
        $this->last_error = $detail;
    }

    public function markFailed(string $error): void
    {
        $this->status = self::STATUS_FAILED;
        $this->last_checked_at = Carbon::now();
        $this->last_error = $error;
    }

    public function markExpired(string $reason = 'certificat expiré'): void
    {
        $this->status = self::STATUS_EXPIRED;
        $this->certificate_status = 'expired';
        $this->last_checked_at = Carbon::now();
        $this->last_error = $reason;
    }

    public function markRemoved(): void
    {
        $this->status = self::STATUS_REMOVED;
        $this->removed_at = Carbon::now();
        $this->last_checked_at = Carbon::now();
    }

    /**
     * Domaines susceptibles de servir : le seul périmètre du routage public.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeServing(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereNull('removed_at')
            ->where(function (Builder $q): void {
                $q->whereNull('certificate_expires_at')
                    ->orWhere('certificate_expires_at', '>', Carbon::now());
            });
    }
}
