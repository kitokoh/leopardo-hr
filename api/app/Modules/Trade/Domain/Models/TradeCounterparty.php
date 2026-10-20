<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Models;

use App\Modules\Trade\Domain\Enums\TradeCounterpartyRole;
use App\Modules\Trade\Domain\Enums\TradeVerificationStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TradeCounterpartyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Contrepartie Trade — satellite CRM (BC-36 TRADE, TRADE-003/#8478).
 *
 * Le referentiel clients/prospects reste `crm_accounts` (module CRM, non
 * modifie) ; cette table ajoute le ROLE trade et les attributs commerciaux
 * (pays, capacites). `crm_account_id` est optionnel : la verticale fonctionne
 * sur un tenant SANS CRM actif (degradation gracieuse, lecon VTC-01).
 *
 * `verification_status` : v1 = verification MANUELLE TRACEE. Le statut
 * `verified` signifie « verifie par l'equipe » et ne doit JAMAIS etre presente
 * comme le resultat d'un controle automatise.
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $crm_account_id
 * @property string $name
 * @property string|null $country
 * @property string|null $city
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property list<string> $roles
 * @property TradeVerificationStatus $verification_status
 * @property int|null $verified_by_employee_id
 * @property Carbon|null $verified_at
 * @property string|null $verification_note
 * @property array<string, mixed>|null $capabilities
 * @property string|null $notes
 */
final class TradeCounterparty extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TradeCounterpartyFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'trade_counterparties';

    protected $fillable = [
        'crm_account_id',
        'name',
        'country',
        'city',
        'email',
        'phone',
        'website',
        'roles',
        'verification_status',
        'verified_by_employee_id',
        'verified_at',
        'verification_note',
        'capabilities',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'crm_account_id' => 'integer',
            'roles' => 'array',
            'verification_status' => TradeVerificationStatus::class,
            'verified_by_employee_id' => 'integer',
            'verified_at' => 'datetime',
            'capabilities' => 'array',
        ];
    }

    /**
     * La contrepartie exerce-t-elle au moins un des roles demandes ?
     *
     * @param  list<TradeCounterpartyRole|string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        $wanted = array_map(
            static fn (TradeCounterpartyRole|string $role): string => $role instanceof TradeCounterpartyRole ? $role->value : $role,
            $roles,
        );

        return array_intersect($wanted, $this->roles ?? []) !== [];
    }

    /** @return HasMany<TradeQuote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(TradeQuote::class, 'trade_counterparty_id');
    }
}
