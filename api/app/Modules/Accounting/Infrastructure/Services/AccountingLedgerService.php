<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Grand livre + balance de vérification — issue #5422.
 *
 * Consultation analytique (lecture seule) des écritures du journal :
 *   - `ledger()` : grand livre d'un compte (ou de tous les comptes) sur une
 *     période mensuelle (YYYY-MM), ordonné par date puis id, avec le solde
 *     cumulé après chaque écriture et le solde d'ouverture (écritures des
 *     périodes antérieures) lorsque le compte est filtré ;
 *   - `openingBalance()` : solde d'ouverture d'un compte à l'entame d'une
 *     période (Σ débits − Σ crédits des périodes antérieures) ;
 *   - `balance()` : balance de vérification de la période — totaux débit /
 *     crédit et solde par compte, totaux généraux et indicateur d'équilibre.
 *
 * Isolation tenant : chaque requête porte un WHERE company_id explicite — le
 * scope global BelongsToCompany (#3727) ne suffit pas pour des requêtes
 * manuelles/agrégées, on ne dépend jamais de l'état du container pour
 * cloisonner les données.
 *
 * Invariants : les écritures sont équilibrées par pièce (Σ débit = Σ crédit,
 * cf. JournalPostingService) ; une ligne porte exclusivement un débit OU un
 * crédit (check DB). Une balance à l'équilibre a un écart strictement
 * inférieur à la tolérance comptable (0.005, mêmes conventions que le
 * posting).
 */
final class AccountingLedgerService
{
    /** Tolérance d'équilibre (mêmes conventions que JournalPostingService). */
    private const TOLERANCE = 0.005;

    /**
     * Grand livre : écritures de la période pour un compte (ou tous comptes
     * si $accountCode est null), triées par entry_date puis id, paginées.
     * Chaque écriture de la page est enrichie de son solde cumulé après
     * l'écriture (`running_balance`), départ pris sur le solde d'ouverture
     * (périodes antérieures) + les écritures de la période classées avant la
     * première écriture de la page.
     *
     * @return LengthAwarePaginator<int, AccountingJournalEntry>
     */
    public function ledger(string $companyId, ?string $accountCode, string $period, int $perPage): LengthAwarePaginator
    {
        $query = AccountingJournalEntry::query()
            ->where('company_id', $companyId)
            ->where('period', $period)
            ->orderBy('entry_date')
            ->orderBy('id');

        if ($accountCode !== null) {
            $query->where('account_code', $accountCode);
        }

        /** @var LengthAwarePaginator<int, AccountingJournalEntry> $paginator */
        $paginator = $query->paginate($perPage);

        $this->attachRunningBalance($paginator, $companyId, $accountCode, $period);

        return $paginator;
    }

    /**
     * Solde d'ouverture d'un compte à l'entame de la période : Σ débits −
     * Σ crédits des écritures antérieures à la période (comparaison
     * lexicographique de 'YYYY-MM'). Retourne null lorsque aucun compte
     * n'est filtré — un solde cumulé multi-comptes n'a pas de sens
     * comptable.
     */
    public function openingBalance(string $companyId, ?string $accountCode, string $period): ?float
    {
        if ($accountCode === null) {
            return null;
        }

        $query = AccountingJournalEntry::query()
            ->where('company_id', $companyId)
            ->where('period', '<', $period)
            ->where('account_code', $accountCode);

        return round((float) $query->sum(DB::raw('debit - credit')), 2);
    }

    /**
     * Balance de vérification de la période : totaux débit/crédit et solde
     * (débit − crédit) par compte (code + libellé), ordonnés par code.
     *
     * @return array{
     *     data: array<int, array{account_code: string, account_label: string, total_debit: float, total_credit: float, balance: float}>,
     *     totals: array{total_debit: float, total_credit: float, ecart: float},
     *     balanced: bool,
     * }
     */
    public function balance(string $companyId, string $period): array
    {
        $rows = AccountingJournalEntry::query()
            ->where('company_id', $companyId)
            ->where('period', $period)
            ->selectRaw('account_code, account_label, SUM(debit) AS total_debit, SUM(credit) AS total_credit')
            ->groupBy('account_code', 'account_label')
            ->orderBy('account_code')
            ->get();

        $data = $rows
            ->map(static function (AccountingJournalEntry $entry): array {
                $totalDebit = round((float) $entry->getAttribute('total_debit'), 2);
                $totalCredit = round((float) $entry->getAttribute('total_credit'), 2);

                return [
                    'account_code' => $entry->account_code,
                    'account_label' => $entry->account_label,
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'balance' => round($totalDebit - $totalCredit, 2),
                ];
            })
            ->values()
            ->all();

        $totalDebit = round((float) array_sum(array_column($data, 'total_debit')), 2);
        $totalCredit = round((float) array_sum(array_column($data, 'total_credit')), 2);

        return [
            'data' => $data,
            'totals' => [
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'ecart' => round(abs($totalDebit - $totalCredit), 2),
            ],
            'balanced' => abs($totalDebit - $totalCredit) < self::TOLERANCE,
        ];
    }

    /**
     * Enrichit les écritures de la page courante avec leur solde cumulé
     * (`running_balance`) : solde d'ouverture (périodes antérieures) + solde
     * des écritures de la période antérieures à la première écriture de page
     * + cumul séquentiel débit−crédit sur la page.
     */
    /**
     * @param  LengthAwarePaginator<int, AccountingJournalEntry>  $paginator
     */
    private function attachRunningBalance(LengthAwarePaginator $paginator, string $companyId, ?string $accountCode, string $period): void
    {
        $items = $paginator->getCollection();
        $first = $items->first();

        if (! $first instanceof AccountingJournalEntry) {
            return;
        }

        $running = round(
            ($this->openingBalance($companyId, $accountCode, $period) ?? 0.0)
            + $this->balanceBefore($companyId, $accountCode, $period, $first),
            2,
        );

        foreach ($items as $entry) {
            $running = round($running + (float) $entry->debit - (float) $entry->credit, 2);
            $entry->setAttribute('running_balance', $running);
        }
    }

    /**
     * Solde des écritures de la période classées AVANT la première écriture
     * de la page courante (même ordre que la pagination : entry_date puis
     * id).
     */
    private function balanceBefore(string $companyId, ?string $accountCode, string $period, AccountingJournalEntry $first): float
    {
        $query = AccountingJournalEntry::query()
            ->where('company_id', $companyId)
            ->where('period', $period)
            ->when($accountCode !== null, static fn (Builder $builder): Builder => $builder->where('account_code', $accountCode))
            ->where(static function (Builder $builder) use ($first): void {
                $builder->where('entry_date', '<', $first->entry_date->toDateString())
                    ->orWhere(static function (Builder $inner) use ($first): void {
                        $inner->where('entry_date', $first->entry_date->toDateString())
                            ->where('id', '<', $first->id);
                    });
            });

        return round((float) $query->sum(DB::raw('debit - credit')), 2);
    }
}
