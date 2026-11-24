<?php

declare(strict_types=1);

namespace App\Core\Tenant\Domain\Models;

use App\Core\Tenant\Domain\Enums\PlatformPermission;
use App\Core\Tenant\Domain\Enums\PlatformRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $status
 * @property string $platform_role
 * @property string|null $password_hash
 * @property string|null $two_fa_secret
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 *
 * @mixin Builder<static>
 */
class SuperAdmin extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'super_admins';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'email',
        'last_login_at',
    ];

    protected $hidden = [
        'password_hash',
        'two_fa_secret',
    ];

    /**
     * Les colonnes created_at / last_login_at sont gérées manuellement
     * ($timestamps = false) mais doivent être castées en Carbon : la
     * sérialisation admin (PlatformUserController::serialize) appelle
     * ->toIso8601String() — sans cast, PostgreSQL renvoie une string et
     * l'endpoint /platform/users (et /admin/users) crashe en 500
     * (constat QA live 2026-08-15).
     */
    protected $casts = [
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        // #9898 — `password_hash` est declare `string|null` (colonne nullable),
        // mais `getAuthPassword()` est contractuellement `string` : Laravel
        // l'utilise pour comparer un hash, et un `null` y produirait un
        // `TypeError` au milieu du flux d'authentification. On normalise ici
        // plutot que d'elargir la signature : un compte sans hash ne peut pas
        // s'authentifier, et une chaine vide fait echouer la comparaison de
        // facon sure (fail-closed) au lieu de lever.
        return (string) $this->password_hash;
    }

    /**
     * Issue #7553 — rôle interne du compte plateforme.
     *
     * `platform_role` est volontairement hors `$fillable` (#3597, garde
     * `SensitiveFillableGuardTest`) : un rôle ne s'assigne jamais par
     * mass-assignment, seulement explicitement (console `/platform/team` ou
     * seeder).
     *
     * Un compte sans rôle renseigné (schéma historique, ligne insérée hors
     * migration) reste `super_admin` : c'est le défaut de la colonne, et le
     * seul choix qui préserve les accès existants lors du déploiement.
     */
    public function platformRole(): PlatformRole
    {
        $raw = $this->platform_role ?? null;

        if (! is_string($raw) || $raw === '') {
            return PlatformRole::SuperAdmin;
        }

        return PlatformRole::tryFrom($raw) ?? PlatformRole::SuperAdmin;
    }

    public function hasPlatformPermission(PlatformPermission|string $permission): bool
    {
        return $this->platformRole()->hasPermission($permission);
    }

    public function isPlatformActive(): bool
    {
        return ($this->status ?? 'active') === 'active';
    }
}
