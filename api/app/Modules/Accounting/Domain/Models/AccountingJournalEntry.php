<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Écriture du journal comptable — une ligne = un compte, débit OU crédit
 * (exclusifs). Issue #5234 — dérivée des documents (invoice/credit_note) et
 * des paiements par JournalPostingService.
 *
 * @property int $id
 * @property string|null $company_id
 * @property Carbon $entry_date
 * @property string $period
 * @property string $source_type
 * @property int $source_id
 * @property string $account_code
 * @property string $account_label
 * @property float $debit
 * @property float $credit
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class AccountingJournalEntry extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_journal_entries';

    protected $fillable = [
        'company_id',
        'entry_date',
        'period',
        'source_type',
        'source_id',
        'account_code',
        'account_label',
        'debit',
        'credit',
        'piece',
        'description',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'debit' => 'float',
        'credit' => 'float',
    ];
}
