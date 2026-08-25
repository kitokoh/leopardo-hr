<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Modules\Accounting\Domain\Enums\AccountClass;
use App\Modules\Accounting\Domain\Enums\AccountType;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Compte du plan comptable d'une entreprise — issue #5422.
 * Une ligne = un compte (code, intitulé, nature, classe PCG/SCF).
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $code
 * @property string $label
 * @property string $type
 * @property int $class
 * @property bool $is_system
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class AccountingChartAccount extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_chart_accounts';

    protected $fillable = [
        'company_id',
        'code',
        'label',
        'type',
        'class',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function accountType(): AccountType
    {
        return AccountType::tryFrom((string) $this->type) ?? AccountType::Expense;
    }

    public function accountClass(): AccountClass
    {
        return AccountClass::tryFrom((int) $this->class) ?? AccountClass::Special;
    }

    /**
     * Écritures du journal portées par ce compte (pour la garde de
     * suppression et le grand livre).
     *
     * @return HasMany<AccountingJournalEntry, $this>
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(AccountingJournalEntry::class, 'account_code', 'code');
    }
}
