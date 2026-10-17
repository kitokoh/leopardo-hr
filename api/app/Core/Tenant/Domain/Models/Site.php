<?php

declare(strict_types=1);

namespace App\Core\Tenant\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Établissement d'exploitation d'un tenant (BOS-T-01 / #8624).
 *
 * Un tenant peut exploiter plusieurs lieux (groupe de restaurants, réseau de
 * stations, chaîne de boutiques). `sites` est donc l'axe de séparation des
 * données métier : chaque opération doit pouvoir être rattachée à un
 * établissement identifiable par un `code` stable au sein de son tenant.
 *
 * @property int $id
 * @property int|null $company_id
 * @property string $name
 * @property string|null $code Code stable et unique PAR tenant (jamais global)
 * @property string|null $site_type restaurant|station|store|school|office|…
 * @property string $status active|inactive|archived
 * @property string|null $address
 * @property float|null $gps_lat
 * @property float|null $gps_lng
 * @property int $gps_radius_m
 * @property string|null $timezone Fuseau de l'établissement (NULL = celui du tenant)
 * @property string|null $currency Devise de l'établissement (NULL = celle du tenant)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class Site extends Model
{
    use BelongsToCompany;
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ARCHIVED = 'archived';

    /** Statuts acceptés — allowlist fail-closed côté écriture (voir SiteController). */
    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_ARCHIVED];

    protected $table = 'sites';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'site_type',
        'status',
        'address',
        'gps_lat',
        'gps_lng',
        'gps_radius_m',
        'timezone',
        'currency',
    ];

    // Pas de `$attributes` par défaut : la fixture de test `CreatesMvpSchema`
    // crée une table `sites` minimale (id/company_id/name/timestamps) et un
    // défaut Eloquent serait inclus dans l'INSERT → « column does not exist ».
    // Les valeurs par défaut vivent dans la migration (DEFAULT côté serveur).

    protected $casts = [
        'gps_lat' => 'float',
        'gps_lng' => 'float',
        'gps_radius_m' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Établissements actifs uniquement — un établissement archivé ne doit plus
     * apparaître dans les sélecteurs d'exploitation.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Résolution par code DANS le tenant courant.
     *
     * Le `code` n'est unique que par tenant (`sites_company_code_uidx`) : un
     * `where('code', …)` non tenant-scopé retournerait l'établissement d'un
     * autre client. Le scope `BelongsToCompany` filtre déjà, cette méthode
     * ajoute la normalisation (casse/espaces) attendue par les imports.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', strtoupper(trim($code)));
    }
}
