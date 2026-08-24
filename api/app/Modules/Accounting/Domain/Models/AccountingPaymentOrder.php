<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Modules\Accounting\Domain\Enums\PaymentOrderStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Ordre de virement salarial — flux Paie → Comptabilité (issue #5239).
 *
 * Créé depuis un run de paie validé (net total), préparé par le comptable
 * (export banque via BankExportGenerator — formats CNEP/SEPA/csv_generic),
 * exécuté avec référence banque + date (= rapprochement).
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $payroll_run_id
 * @property string $status
 * @property float $total_net
 * @property string|null $currency
 * @property string|null $export_format
 * @property string|null $export_file
 * @property string|null $bank_reference
 * @property Carbon|null $executed_at
 * @property int|null $executed_by
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class AccountingPaymentOrder extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_payment_orders';

    protected $fillable = [
        'company_id',
        'payroll_run_id',
        'status',
        'total_net',
        'currency',
        'export_format',
        'export_file',
        'bank_reference',
        'executed_at',
        'executed_by',
        'created_by',
    ];

    protected $casts = [
        'total_net' => 'float',
        'executed_at' => 'datetime',
    ];

    public function isExecuted(): bool
    {
        return $this->status === PaymentOrderStatus::Executed->value;
    }

    public function isPrepared(): bool
    {
        return $this->status === PaymentOrderStatus::Prepared->value;
    }
}
