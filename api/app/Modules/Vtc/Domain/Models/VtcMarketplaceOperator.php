<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Models;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Annuaire public des opérateurs VTC opt-in de la marketplace (#8423, M-10)
 * — schema PUBLIC, cross-tenant.
 *
 * Marqueur « opérateur VTC publié sur marketplace » : équivalent VTC du
 * jeton boutique actif de la marketplace travel (#7737), posé sur le
 * pattern des annuaires plateforme FUNDRAISING (#8379). La présence d'une
 * ligne `active` rend la société LISTABLE par
 * `GET /public/vtc/marketplace/operators` — sous réserve des gardes
 * plateforme (société ni suspendue ni expirée, feature `vtc` activée).
 * Les données métier (courses, grilles tarifaires) ne transitent JAMAIS
 * par cette table : elles restent lues dans le schema tenant après
 * résolution du tenant par ressource (PublicTenantResolver, BOS-050).
 *
 * EXCEPTION TENANT-SCOPE canonique (#7999, liste
 * dev-hub/governance/tenant-scope-exceptions.json) : annuaire plateforme
 * de résolution — `company_id` y est un pointeur de routage, pas une clé
 * d'isolation ; BelongsToCompany est donc inapplicable.
 *
 * @property int $id
 * @property string $company_id
 * @property string $status active|inactive
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
final class VtcMarketplaceOperator extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'vtc_marketplace_operators';

    protected $fillable = [
        'company_id',
        'status',
    ];

    /**
     * Société propriétaire de la publication (registre public.companies).
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Portée « publié » : ligne active uniquement — les gardes société
     * (statut, feature `vtc`) sont appliquées par l'appelant
     * (VtcMarketplaceService), fail-closed.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
