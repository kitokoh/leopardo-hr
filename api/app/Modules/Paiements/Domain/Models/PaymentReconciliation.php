<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Models;

use App\Modules\Paiements\Domain\Enums\ReconciliationStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * BC-38 PAIEMENTS (#8692) — ligne d'historique de rapprochement.
 *
 * `difference = declared - computed`. Un ecart nul vaut `ok` ; tout le reste
 * vaut `discrepancy` et reste trace, meme apres correction.
 *
 * @property int $id
 * @property string $company_id
 * @property int $provider_instance_id
 * @property string $currency
 * @property int $declared_balance
 * @property int $computed_balance
 * @property int $difference
 * @property ReconciliationStatus $status
 * @property Carbon $checked_at
 * @property array<string, mixed>|null $metadata
 *
 * @mixin Builder<static>
 */
class PaymentReconciliation extends Model
{
    use BelongsToCompany;

    protected $table = 'payment_reconciliations';

    protected $fillable = [
        'company_id',
        'provider_instance_id',
        'currency',
        'declared_balance',
        'computed_balance',
        'difference',
        'status',
        'checked_at',
        'metadata',
    ];

    protected $casts = [
        'declared_balance' => 'integer',
        'computed_balance' => 'integer',
        'difference' => 'integer',
        'status' => ReconciliationStatus::class,
        'checked_at' => 'datetime',
        'metadata' => 'array',
    ];
}
