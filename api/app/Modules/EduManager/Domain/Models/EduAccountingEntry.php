<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Ligne d'écriture comptable EduManager — Issue #5832 (EDU-016).
 *
 * Contrat Accounting : lignes équilibrées (débit = crédit) produites par
 * EduAccountingEntryService — à la création d'une charge (créance client /
 * produits), à l'encaissement (banque/caisse / créance) et à l'abandon
 * (pertes / créance). Le module EduManager reste maître de la facturation ;
 * le module Accounting consomme ces lignes (pattern PayrollAccountingEntry
 * #5239). UNIQUE (company_id, source_type, source_id, account_code) →
 * régénération idempotente sans doublon (rapprochement audité).
 *
 * @property int $id
 * @property string $company_id
 * @property string $source_type
 * @property int $source_id
 * @property Carbon $entry_date
 * @property string $account_code
 * @property string $account_label
 * @property string $debit
 * @property string $credit
 * @property string $reference
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduAccountingEntry extends Model
{
    use BelongsToCompany;

    public const SOURCE_FEE_CHARGE = 'fee_charge';

    public const SOURCE_FEE_PAYMENT = 'fee_payment';

    public const SOURCE_FEE_WAIVER = 'fee_waiver';

    protected $table = 'edu_accounting_entries';

    protected $fillable = [
        'company_id',
        'source_type',
        'source_id',
        'entry_date',
        'account_code',
        'account_label',
        'debit',
        'credit',
        'reference',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];
}
