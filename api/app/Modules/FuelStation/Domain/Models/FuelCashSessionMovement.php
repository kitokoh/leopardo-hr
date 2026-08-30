<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Mouvement d'une session de caisse FuelStation (FUEL-007, #5801).
 *
 * type in|out, montant strictement positif, motif obligatoire. Écrit
 * uniquement tant que la session est ouverte (422 SESSION_NOT_OPEN sinon).
 *
 * @property int $id
 * @property string $company_id
 * @property int $session_id
 * @property string $type in|out
 * @property float $amount
 * @property string $reason
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read FuelCashSession|null $session
 *
 * @mixin Builder<static>
 */
class FuelCashSessionMovement extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_cash_session_movements';

    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    public const TYPES = [self::TYPE_IN, self::TYPE_OUT];

    protected $fillable = [
        'company_id',
        'session_id',
        'type',
        'amount',
        'reason',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'session_id' => 'integer',
            'amount' => 'float',
        ];
    }

    /** @return BelongsTo<FuelCashSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(FuelCashSession::class, 'session_id');
    }
}
