<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Grand livre des crédits IA (#7764, spec MISSION_ESPACE_CLIENT §3.4).
 *
 * Chaque ligne est un mouvement SIGNÉ de tokens IA achetés : achat (+),
 * consommation (−), ajustement admin (±). Le solde d'une entreprise est la
 * somme des `delta` — aucune colonne de solde matérialisée.
 *
 * `reference` porte l'id de session Stripe (ou la clé sandbox) d'un achat ;
 * l'index unique partiel `(reference) WHERE reason = 'purchase'` garantit
 * qu'un rejeu de webhook ne crédite jamais deux fois.
 *
 * @property int $id
 * @property string $company_id
 * @property int $delta
 * @property string $reason
 * @property string|null $reference
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class AiCreditLedger extends Model
{
    use BelongsToCompany;

    public const REASON_PURCHASE = 'purchase';

    public const REASON_CONSUMPTION = 'consumption';

    public const REASON_ADJUSTMENT = 'adjustment';

    protected $table = 'ai_credit_ledger';

    protected $fillable = [
        'company_id',
        'delta',
        'reason',
        'reference',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'delta' => 'integer',
            'created_by' => 'integer',
        ];
    }
}
