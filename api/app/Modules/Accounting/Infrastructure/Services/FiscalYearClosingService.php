<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Enums\AccountType;
use App\Modules\Accounting\Domain\Exceptions\FiscalYearAlreadyClosedException;
use App\Modules\Accounting\Domain\Models\AccountingChartAccount;
use App\Modules\Accounting\Domain\Models\AccountingClosedPeriod;
use App\Modules\Accounting\Domain\Models\AccountingFiscalYear;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Clôture d'exercice comptable — issue #5422.
 *
 * La clôture d'un exercice :
 *   1. calcule le résultat net de l'année (Σ crédits − Σ débits sur les
 *      comptes de gestion revenue/expense) ;
 *   2. si le résultat est non nul, passe une écriture de report à nouveau
 *      (source `fiscal_year_close`, pièce `CLO-{year}`, période YYYY-12) ;
 *   3. marque les 12 périodes de l'année comme clôturées (upsert par
 *      company_id + period) ;
 *   4. passe l'exercice en `closed` (closed_by, closed_at).
 *
 * Schéma d'écriture de clôture (simplification retenue et documentée) :
 * le résultat de l'exercice est soldé vers le report à nouveau en UNE
 * écriture équilibrée de deux lignes —
 *   - bénéfice (résultat > 0) : débit 12 « Report à nouveau » / crédit 891
 *     « Résultat de l'exercice » ;
 *   - perte   (résultat < 0) : crédit 12 « Report à nouveau » / débit 891
 *     « Résultat de l'exercice ».
 * On ne solde pas chaque compte de gestion individuellement (écriture
 * d'état complète) : le compte agrégé 891 porte le solde, l'écriture est
 * vérifiable et le report à nouveau alimenté du bon montant.
 *
 * Détermination du type de compte (résultat) : le type du plan comptable
 * (accounting_chart_accounts.type) fait foi ; à défaut, la classe PCG/SCF
 * (6 → expense, 7 → revenue) ; sans plan ni classe, le préfixe du code
 * (6 → expense, 7 → revenue) ; les autres comptes (bilan, tiers, banques…)
 * sont ignorés dans le calcul du résultat.
 *
 * Le calcul du résultat s'exécute DANS la transaction de clôture : une
 * écriture de gestion postée en parallèle pendant la clôture ne peut pas
 * être « oubliée » entre le calcul et le verrouillage des périodes.
 */
final class FiscalYearClosingService
{
    private const TOLERANCE = 0.005;

    private const SOURCE_TYPE_CLOSE = 'fiscal_year_close';

    private const ACCOUNT_RETAINED_EARNINGS = ['12', 'Report à nouveau'];

    private const ACCOUNT_YEAR_RESULT = ['891', "Résultat de l'exercice"];

    /**
     * Clôture l'exercice `year` de l'entreprise : écriture de report à
     * nouveau (si résultat non nul), clôture des 12 périodes de l'année,
     * passage de l'exercice en `closed`.
     *
     * @return array{result: float, entry_count: int, closed_periods: int}
     */
    public function close(string $companyId, int $year, string $closedBy): array
    {
        $fiscalYear = AccountingFiscalYear::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->first();

        if (! $fiscalYear instanceof AccountingFiscalYear || ! $fiscalYear->isOpen()) {
            throw new FiscalYearAlreadyClosedException($year);
        }

        return DB::transaction(function () use ($companyId, $year, $closedBy, $fiscalYear): array {
            $result = $this->netResult($companyId, $year);

            $entryCount = 0;
            if (abs($result) > self::TOLERANCE) {
                $entryCount = $this->postCarryForwardEntry($fiscalYear, $result);
            }

            $closedPeriods = $this->closeYearPeriods($companyId, $year, $closedBy);

            $fiscalYear->update([
                'status' => AccountingFiscalYear::STATUS_CLOSED,
                'closed_by' => $closedBy,
                'closed_at' => Carbon::now(),
            ]);

            return [
                'result' => $result,
                'entry_count' => $entryCount,
                'closed_periods' => $closedPeriods,
            ];
        });
    }

    /**
     * Exercices de l'entreprise, du plus récent au plus ancien.
     *
     * @return Collection<int, AccountingFiscalYear>
     */
    public function list(string $companyId): Collection
    {
        return AccountingFiscalYear::query()
            ->where('company_id', $companyId)
            ->orderByDesc('year')
            ->get();
    }

    /**
     * Ouvre (ou retourne) l'exercice `year` de l'entreprise — idempotent.
     * `$createdBy` est l'acteur à l'origine de la création (réservé trace
     * d'audit : pas de colonne `created_by` sur accounting_fiscal_years).
     */
    public function open(string $companyId, int $year, string $createdBy): AccountingFiscalYear
    {
        return AccountingFiscalYear::query()->firstOrCreate(
            ['company_id' => $companyId, 'year' => $year],
            ['status' => AccountingFiscalYear::STATUS_OPEN],
        );
    }

    /**
     * Résultat net de l'année : Σ crédits − Σ débits des écritures portées
     * par des comptes de gestion (revenue/expense), voir le docblock de
     * classe pour la chaîne de résolution du type.
     */
    private function netResult(string $companyId, int $year): float
    {
        /** @var array<string, AccountingChartAccount> $chartAccounts */
        $chartAccounts = [];
        foreach (AccountingChartAccount::query()->where('company_id', $companyId)->get() as $account) {
            $chartAccounts[(string) $account->code] = $account;
        }

        $entries = AccountingJournalEntry::query()
            ->where('company_id', $companyId)
            ->whereBetween('entry_date', [$year.'-01-01', $year.'-12-31'])
            ->get(['account_code', 'debit', 'credit']);

        $result = 0.0;

        foreach ($entries as $entry) {
            $accountCode = (string) $entry->account_code;

            if (! $this->isProfitAndLossAccount($accountCode, $chartAccounts[$accountCode] ?? null)) {
                continue;
            }

            $result += (float) $entry->credit - (float) $entry->debit;
        }

        return round($result, 2);
    }

    /**
     * Détermine si un compte alimente le compte de résultat :
     * 1. type du plan (revenue/expense) → inclus ;
     * 2. sinon classe PCG/SCF du plan (6 → expense, 7 → revenue) ;
     * 3. sans plan : préfixe du code (6 → expense, 7 → revenue) ;
     * 4. sinon (bilan, tiers, trésorerie…) → exclu.
     */
    private function isProfitAndLossAccount(string $code, ?AccountingChartAccount $chartAccount): bool
    {
        if ($chartAccount instanceof AccountingChartAccount) {
            if (in_array($chartAccount->type, [AccountType::Revenue->value, AccountType::Expense->value], true)) {
                return true;
            }

            return in_array($chartAccount->class, [6, 7], true);
        }

        return str_starts_with($code, '6') || str_starts_with($code, '7');
    }

    /**
     * Écriture de report à nouveau (deux lignes équilibrées, voir docblock
     * de classe). Datée au 31 décembre, période YYYY-12, pièce CLO-{year}.
     * Retourne le nombre de lignes créées (2 si le résultat est non nul).
     */
    private function postCarryForwardEntry(AccountingFiscalYear $fiscalYear, float $result): int
    {
        $amount = round(abs($result), 2);
        $isProfit = $result > 0;

        // Bénéfice : D 12 / C 891 — Perte : C 12 / D 891.
        $lines = $isProfit
            ? [
                ['account' => self::ACCOUNT_RETAINED_EARNINGS[0], 'label' => self::ACCOUNT_RETAINED_EARNINGS[1], 'debit' => $amount, 'credit' => 0.0],
                ['account' => self::ACCOUNT_YEAR_RESULT[0], 'label' => self::ACCOUNT_YEAR_RESULT[1], 'debit' => 0.0, 'credit' => $amount],
            ]
            : [
                ['account' => self::ACCOUNT_RETAINED_EARNINGS[0], 'label' => self::ACCOUNT_RETAINED_EARNINGS[1], 'debit' => 0.0, 'credit' => $amount],
                ['account' => self::ACCOUNT_YEAR_RESULT[0], 'label' => self::ACCOUNT_YEAR_RESULT[1], 'debit' => $amount, 'credit' => 0.0],
            ];

        $entryDate = Carbon::create($fiscalYear->year, 12, 31);
        $period = $fiscalYear->year.'-12';
        $piece = 'CLO-'.$fiscalYear->year;
        $description = 'Clôture exercice '.$fiscalYear->year.' — report à nouveau';

        $count = 0;
        foreach ($lines as $line) {
            AccountingJournalEntry::query()->create([
                'company_id' => $fiscalYear->company_id,
                'entry_date' => $entryDate,
                'period' => $period,
                'source_type' => self::SOURCE_TYPE_CLOSE,
                'source_id' => $fiscalYear->id,
                'account_code' => $line['account'],
                'account_label' => $line['label'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'piece' => $piece,
                'description' => $description,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Marque les 12 périodes de l'année (YYYY-01 → YYYY-12) comme clôturées
     * (upsert par company_id + period — idempotent). Retourne le nombre de
     * périodes assurées clôturées (toujours 12).
     */
    private function closeYearPeriods(string $companyId, int $year, string $closedBy): int
    {
        $closedAt = Carbon::now();
        $count = 0;

        for ($month = 1; $month <= 12; $month++) {
            $period = sprintf('%04d-%02d', $year, $month);

            AccountingClosedPeriod::query()->updateOrCreate(
                ['company_id' => $companyId, 'period' => $period],
                ['closed_by' => $closedBy, 'closed_at' => $closedAt],
            );

            $count++;
        }

        return $count;
    }
}
