<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Boite mail connectee d'un employe (BC-29 COMMUNICATION, R1 #7686).
 *
 * Securite (exigences issue) :
 * - `access_token` / `refresh_token` : casts `encrypted` — JAMAIS en clair
 *   en base (pattern CrmChannelMessage #5725) ; `$hidden` les retire de
 *   toute serialisation (API, logs contextuels) — seul le service OAuth y
 *   accede, jamais les Resources.
 * - `company_id` : hors `$fillable` (#7646), pose par le hook `creating` de
 *   `BelongsToCompany` sous tenant actif, ou par `forceFill` explicite sur
 *   le chemin callback (hors surface tenant, state signe verifie).
 *
 * Minimisation : la ligne ne porte que l'adresse de la boite, les scopes
 * accordes et les tokens — aucun contenu de mail avant R2, aucune autre
 * donnee du profil Google.
 *
 * @property string $id
 * @property string $company_id
 * @property int $employee_id
 * @property string $provider
 * @property string|null $email
 * @property array<int, string>|null $scopes
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $expires_at
 * @property string $status
 * @property Carbon|null $connected_at
 * @property Carbon|null $revoked_at
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CommunicationIntegration extends Model
{
    use BelongsToCompany;
    use HasUuids;

    public const PROVIDER_GOOGLE = 'google';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_ERROR = 'error';

    protected $table = 'communication_integrations';

    /**
     * Allowlist explicite SANS `company_id` (#7646) : le tenant est pose par
     * le hook creating de BelongsToCompany, jamais par mass assignment.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'provider',
        'email',
        'scopes',
        'access_token',
        'refresh_token',
        'expires_at',
        'status',
        'connected_at',
        'revoked_at',
        'last_error',
    ];

    /**
     * Les tokens ne sortent JAMAIS d'une serialisation (reponses API, logs).
     *
     * @var list<string>
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * L'access token est-il a rafraichir ? Marge de 60 s pour ne jamais
     * presenter a Google un token qui expire pendant l'appel.
     */
    public function accessTokenNeedsRefresh(): bool
    {
        return $this->access_token === null
            || $this->expires_at === null
            || $this->expires_at->lte(now()->addSeconds(60));
    }
}
