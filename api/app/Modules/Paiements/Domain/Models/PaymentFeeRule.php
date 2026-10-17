<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Models;

use App\Modules\Paiements\Domain\Enums\FeeMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * BC-38 PAIEMENTS (#8687) — regle de commission.
 *
 * `company_id` et `provider_instance_id` sont nullables : c'est ce qui permet
 * la cascade de specificite (tenant + instance > tenant > instance > globale).
 * Aucune regle resolue = commission zero, jamais d'exception silencieuse.
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $operation_type
 * @property int|null $provider_instance_id
 * @property FeeMode $mode
 * @property int $value
 * @property string|null $currency
 * @property int|null $min_amount
 * @property int|null $max_amount
 * @property int $priority
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class PaymentFeeRule extends Model
{
    protected $table = 'payment_fee_rules';

    protected $fillable = [
        'company_id',
        'operation_type',
        'provider_instance_id',
        'mode',
        'value',
        'currency',
        'min_amount',
        'max_amount',
        'priority',
    ];

    protected $casts = [
        'mode' => FeeMode::class,
        'value' => 'integer',
        'min_amount' => 'integer',
        'max_amount' => 'integer',
        'priority' => 'integer',
    ];

    /**
     * Score de specificite : plus il est eleve, plus la regle est precise.
     *
     * tenant + instance = 3, tenant = 2, instance = 1, globale = 0.
     */
    public function specificity(): int
    {
        return ($this->company_id !== null ? 2 : 0)
            + ($this->provider_instance_id !== null ? 1 : 0);
    }
}
