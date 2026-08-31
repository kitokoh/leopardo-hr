<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Compte de fidélité d'un voyageur (TRAVEL-811, issue #6101).
 *
 * L'opt-in RGPD est OBLIGATOIRE avant tout crédit de points ; le solde est
 * mis à jour transactionnellement avec les entrées de journal (jamais de
 * solde dérivé).
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $contact_identifier
 * @property int $points_balance
 * @property bool $opt_in
 * @property Carbon|null $opt_in_at
 * @property Carbon|null $opt_out_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelLoyaltyAccount extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'contact_identifier',
        'points_balance',
        'opt_in',
        'opt_in_at',
        'opt_out_at',
    ];

    protected $casts = [
        'points_balance' => 'integer',
        'opt_in' => 'boolean',
        'opt_in_at' => 'datetime',
        'opt_out_at' => 'datetime',
    ];

    /**
     * @return HasMany<TravelLoyaltyEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(TravelLoyaltyEntry::class, 'account_id');
    }
}
