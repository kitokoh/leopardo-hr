<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Modules\Accounting\Domain\Enums\JournalType;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Journal comptable d'une entreprise — issue #9370.
 *
 * Avant cette entité, le journal d'une écriture n'existait pas : `FecExporter`
 * le devinait à l'export depuis le préfixe du numéro de compte, ce qui
 * éclatait une même facture entre `VE` et `OD` (la ligne de TVA `4457` ne
 * commence ni par 6, ni par 7, ni par `411`) et classait en `OD` toutes les
 * écritures d'un tenant non-PCG (turc `120`, OHADA `44571`).
 *
 * Un journal est désormais une **entité paramétrable** : code FEC (2 à 6
 * caractères), libellé, nature, et compte de trésorerie par défaut pour les
 * journaux de banque/caisse.
 *
 * @property int $id
 * @property string $company_id
 * @property string $code
 * @property string $label
 * @property string $type
 * @property string|null $default_account_code
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class AccountingJournal extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_journals';

    protected $fillable = [
        'company_id',
        'code',
        'label',
        'type',
        'default_account_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function journalType(): JournalType
    {
        return JournalType::tryFrom((string) $this->type) ?? JournalType::Misc;
    }

    /**
     * Écritures rattachées (par code, l'écriture ne porte pas d'id de journal
     * pour rester lisible dans un export FEC brut).
     *
     * @return Builder<AccountingJournalEntry>
     */
    public function entries(): Builder
    {
        return AccountingJournalEntry::query()
            ->where('company_id', $this->company_id)
            ->where('journal_code', $this->code);
    }

    public function hasEntries(): bool
    {
        return $this->entries()->exists();
    }
}
