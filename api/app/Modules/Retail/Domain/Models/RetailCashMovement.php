<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use App\Modules\Retail\Domain\Enums\RetailCashMovementType;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Mouvement de tiroir-caisse d'une session POS Retail (BC-17 RETAIL, #9212).
 *
 * Trace une entree (`in`), une sortie (`out`) ou un prelevement (`drop`)
 * d'especes pendant une session ouverte. `reason` est obligatoire : un
 * mouvement d'especes sans motif est un trou d'audit.
 *
 * L'attendu de cloture (`RetailPosService::closeSession`) integre ces
 * mouvements : `expected = fonds d'ouverture + ventes cash + entrees - sorties
 * - prelevements - remboursements cash`.
 *
 * Montants en minor units. Cree UNIQUEMENT via RetailPosService (transaction).
 *
 * @property int $id
 * @property string $company_id
 * @property int $pos_session_id
 * @property RetailCashMovementType $type
 * @property int $amount_minor
 * @property string $reason
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class RetailCashMovement extends Model
{
    use BelongsToCompany;

    protected $table = 'retail_cash_movements';

    protected $fillable = [
        'company_id',
        'pos_session_id',
        'type',
        'amount_minor',
        'reason',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RetailCashMovementType::class,
            'amount_minor' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RetailPosSession, $this>
     */
    public function posSession(): BelongsTo
    {
        return $this->belongsTo(RetailPosSession::class, 'pos_session_id');
    }
}
