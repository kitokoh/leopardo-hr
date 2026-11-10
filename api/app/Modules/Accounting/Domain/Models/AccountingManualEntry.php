<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Écriture manuelle (journal d'opérations diverses) — issue #9369.
 *
 * Le module n'offrait **aucune** saisie libre : toutes les écritures étaient
 * dérivées de documents ou de paiements. Il était donc impossible de passer une
 * écriture d'ouverture, une régularisation de TVA, une charge à payer (408), une
 * dotation, un virement interne 512 ↔ 53 ou une correction d'erreur — ce qui
 * rendait le module inexploitable par un comptable.
 *
 * Une écriture manuelle regroupe ses lignes par un `manual_ref` (uuid) : c'est
 * l'unité que l'on modifie ou que l'on supprime, jamais une ligne isolée (sinon
 * le regroupement perdrait son équilibre).
 *
 * @property int $id
 * @property string $company_id
 * @property string $manual_ref
 * @property Carbon $entry_date
 * @property string $period
 * @property string|null $journal_code
 * @property string $created_by
 *
 * @mixin Builder<static>
 */
class AccountingManualEntry extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_manual_entries';

    protected $fillable = [
        'company_id',
        'manual_ref',
        'entry_date',
        'period',
        'journal_code',
        'piece',
        'description',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    /**
     * Lignes d'écriture du groupe.
     *
     * @return Builder<AccountingJournalEntry>
     */
    public function lines(): Builder
    {
        return AccountingJournalEntry::query()
            ->where('company_id', $this->company_id)
            ->where('source_type', 'manual')
            ->where('manual_ref', $this->manual_ref);
    }
}
