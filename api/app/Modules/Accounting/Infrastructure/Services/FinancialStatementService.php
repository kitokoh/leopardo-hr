<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Enums\AccountType;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use stdClass;

/**
 * États financiers — bilan et compte de résultat (issue #5422).
 *
 * Lecture seule, agrégation des écritures du journal
 * (`accounting_journal_entries`) :
 *
 *  - `balanceSheet()`    : bilan d'une année civile. ACTIF = comptes
 *    `asset` (solde débiteur Σ débits − Σ crédits), regroupés par classe
 *    (2 Immobilisations, 3 Stocks, 4 Créances, 5 Trésorerie). PASSIF =
 *    comptes `liability` (solde créditeur Σ crédits − Σ débits), regroupés
 *    par classe (4 Dettes fournisseurs/tiers, 1 Emprunts). CAPITAUX PROPRES
 *    = comptes `equity` (solde créditeur) + résultat net de l'exercice
 *    (produits − charges, mêmes définitions que le compte de résultat).
 *  - `incomeStatement()` : compte de résultat d'une période YYYY-MM.
 *    Produits = soldes créditeurs nets des comptes `revenue`, charges =
 *    soldes débiteurs nets des comptes `expense`, regroupés en
 *    exploitation / financier / exceptionnel (66/67 pour les charges,
 *    76/77 pour les produits).
 *
 * Décision de calcul — soldes NETS (pas seulement Σ crédits / Σ débits) :
 * un avoir (709 au débit, remise sur vente) ou un reclassement (charge au
 * crédit) vient en diminution du poste concerné. C'est la définition qui
 * garantit l'invariant d'équilibre du bilan (|total_actif −
 * total_passif_capitaux| < tolérance) pour tout journal équilibré, y
 * compris en présence d'avoirs (un avoir réduit le résultat, sinon le bilan
 * serait déséquilibré).
 *
 * Plan comptable : JOIN optionnel avec `accounting_chart_accounts` pour la
 * nature (type/classe) et l'intitulé. Un code absent du plan est résolu par
 * repli robuste — classe dérivée du premier chiffre (1→equity, 2/3/5→asset,
 * 4→selon code (41* / 4456 = créance, sinon dette), 6→expense, 7→revenue,
 * défaut→liability) et intitulé repris du journal. Le service reste donc
 * fonctionnel même sans plan provisionné.
 *
 * Isolation tenant : toutes les agrégations filtrent explicitement par
 * `company_id` (fail-closed #3727) — aucun id d'URL, aucune dépendance au
 * scope global Eloquent.
 */
final class FinancialStatementService
{
    /** Tolérance d'équilibre du bilan (écart maximum actif vs passif+capitaux). */
    private const TOLERANCE = 0.005;

    private const SECTION_IMMOBILISATIONS = 'Immobilisations';

    private const SECTION_STOCKS = 'Stocks';

    private const SECTION_CREANCES = 'Créances';

    private const SECTION_TRESORERIE = 'Trésorerie';

    private const SECTION_AUTRES_ACTIFS = 'Autres actifs';

    private const SECTION_DETTES_TIERS = 'Dettes fournisseurs/tiers';

    private const SECTION_EMPRUNTS = 'Emprunts';

    private const SECTION_AUTRES_DETTES = 'Autres dettes';

    private const SECTION_CAPITAUX_PROPRES = 'Capitaux propres';

    private const SECTION_RESULTAT = 'Résultat de l\'exercice';

    private const SECTION_PRODUITS_EXPLOITATION = 'Produits d\'exploitation';

    private const SECTION_PRODUITS_FINANCIERS = 'Produits financiers';

    private const SECTION_PRODUITS_EXCEPTIONNELS = 'Produits exceptionnels';

    private const SECTION_CHARGES_EXPLOITATION = 'Charges d\'exploitation';

    private const SECTION_CHARGES_FINANCIERES = 'Charges financières';

    private const SECTION_CHARGES_EXCEPTIONNELLES = 'Charges exceptionnelles';

    /**
     * Bilan d'une année civile : actif, passif, capitaux propres (avec
     * résultat net de l'exercice) et invariant d'équilibre.
     *
     * @return array{
     *   actif: list<array{section: string, accounts: list<array{code: string, label: string, balance: float}>, total: float}>,
     *   passif: list<array{section: string, accounts: list<array{code: string, label: string, balance: float}>, total: float}>,
     *   capitaux: list<array{section: string, accounts: list<array{code: string, label: string, balance: float}>, total: float}>,
     *   total_actif: float,
     *   total_passif: float,
     *   total_capitaux: float,
     *   total_passif_capitaux: float,
     *   resultat_net: float,
     *   balanced: bool
     * }
     */
    public function balanceSheet(string $companyId, int $year): array
    {
        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException(__('accounting.errors.statement_year_invalid'));
        }

        $chart = $this->chartByCode($companyId);
        $rows = $this->journalRowsForRange($companyId, sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year));

        /** @var array<int, array<string, array{code: string, label: string, balance: float}>> $assetsByClass */
        $assetsByClass = [];
        /** @var array<int, array<string, array{code: string, label: string, balance: float}>> $liabilitiesByClass */
        $liabilitiesByClass = [];
        /** @var array<string, array{code: string, label: string, balance: float}> $equityAccounts */
        $equityAccounts = [];

        $produits = 0.0;
        $charges = 0.0;

        foreach ($rows as $row) {
            $meta = $this->resolveAccount($chart, $row['code'], $row['label']);

            switch ($meta['type']) {
                case AccountType::Asset:
                    $balance = round($row['debit'] - $row['credit'], 2);
                    if (abs($balance) > self::TOLERANCE) {
                        $assetsByClass[$meta['class']][$row['code']] = [
                            'code' => $row['code'],
                            'label' => $meta['label'],
                            'balance' => $balance,
                        ];
                    }
                    break;

                case AccountType::Liability:
                    $balance = round($row['credit'] - $row['debit'], 2);
                    if (abs($balance) > self::TOLERANCE) {
                        $liabilitiesByClass[$meta['class']][$row['code']] = [
                            'code' => $row['code'],
                            'label' => $meta['label'],
                            'balance' => $balance,
                        ];
                    }
                    break;

                case AccountType::Equity:
                    $balance = round($row['credit'] - $row['debit'], 2);
                    if (abs($balance) > self::TOLERANCE) {
                        $equityAccounts[$row['code']] = [
                            'code' => $row['code'],
                            'label' => $meta['label'],
                            'balance' => $balance,
                        ];
                    }
                    break;

                case AccountType::Revenue:
                    $produits += $row['credit'] - $row['debit'];
                    break;

                case AccountType::Expense:
                    $charges += $row['debit'] - $row['credit'];
                    break;
            }
        }

        $resultatNet = round($produits - $charges, 2);

        $actif = $this->balanceSections($assetsByClass, [
            2 => self::SECTION_IMMOBILISATIONS,
            3 => self::SECTION_STOCKS,
            4 => self::SECTION_CREANCES,
            5 => self::SECTION_TRESORERIE,
        ], self::SECTION_AUTRES_ACTIFS);

        $passif = $this->balanceSections($liabilitiesByClass, [
            4 => self::SECTION_DETTES_TIERS,
            1 => self::SECTION_EMPRUNTS,
        ], self::SECTION_AUTRES_DETTES);

        $capitaux = [
            $this->buildBalanceSection(self::SECTION_CAPITAUX_PROPRES, $equityAccounts),
            $this->buildBalanceSection(self::SECTION_RESULTAT, [
                'resultat' => [
                    'code' => '12',
                    'label' => self::SECTION_RESULTAT,
                    'balance' => $resultatNet,
                ],
            ]),
        ];

        $totalActif = $this->sumBalanceSections($actif);
        $totalPassif = $this->sumBalanceSections($passif);
        $totalCapitaux = $this->sumBalanceSections($capitaux);
        $totalPassifCapitaux = $totalPassif + $totalCapitaux;

        return [
            'actif' => $actif,
            'passif' => $passif,
            'capitaux' => $capitaux,
            'total_actif' => round($totalActif, 2),
            'total_passif' => round($totalPassif, 2),
            'total_capitaux' => round($totalCapitaux, 2),
            'total_passif_capitaux' => round($totalPassifCapitaux, 2),
            'resultat_net' => $resultatNet,
            'balanced' => abs($totalActif - $totalPassifCapitaux) <= self::TOLERANCE,
        ];
    }

    /**
     * Compte de résultat d'une période mensuelle (YYYY-MM, colonne `period`).
     *
     * @return array{
     *   produits: array{sections: list<array{section: string, accounts: list<array{code: string, label: string, amount: float}>, total: float}>, total: float},
     *   charges: array{sections: list<array{section: string, accounts: list<array{code: string, label: string, amount: float}>, total: float}>, total: float},
     *   resultat: float
     * }
     */
    public function incomeStatement(string $companyId, string $period): array
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) !== 1) {
            throw new InvalidArgumentException(__('accounting.errors.statement_period_invalid'));
        }

        $chart = $this->chartByCode($companyId);
        $rows = $this->journalRowsForPeriod($companyId, $period);

        /** @var array<string, array<string, array{code: string, label: string, amount: float}>> $produitsBySection */
        $produitsBySection = [];
        /** @var array<string, array<string, array{code: string, label: string, amount: float}>> $chargesBySection */
        $chargesBySection = [];

        foreach ($rows as $row) {
            $meta = $this->resolveAccount($chart, $row['code'], $row['label']);

            if ($meta['type'] === AccountType::Revenue) {
                $amount = round($row['credit'] - $row['debit'], 2);
                if (abs($amount) > self::TOLERANCE) {
                    $section = $this->produitSection($row['code']);
                    $produitsBySection[$section][$row['code']] = [
                        'code' => $row['code'],
                        'label' => $meta['label'],
                        'amount' => $amount,
                    ];
                }
            } elseif ($meta['type'] === AccountType::Expense) {
                $amount = round($row['debit'] - $row['credit'], 2);
                if (abs($amount) > self::TOLERANCE) {
                    $section = $this->chargeSection($row['code']);
                    $chargesBySection[$section][$row['code']] = [
                        'code' => $row['code'],
                        'label' => $meta['label'],
                        'amount' => $amount,
                    ];
                }
            }
        }

        $produits = $this->amountStatement($produitsBySection, [
            self::SECTION_PRODUITS_EXPLOITATION,
            self::SECTION_PRODUITS_FINANCIERS,
            self::SECTION_PRODUITS_EXCEPTIONNELS,
        ]);
        $charges = $this->amountStatement($chargesBySection, [
            self::SECTION_CHARGES_EXPLOITATION,
            self::SECTION_CHARGES_FINANCIERES,
            self::SECTION_CHARGES_EXCEPTIONNELLES,
        ]);

        return [
            'produits' => $produits,
            'charges' => $charges,
            'resultat' => round($produits['total'] - $charges['total'], 2),
        ];
    }

    /**
     * Écritures du journal agrégées par compte sur une plage de dates.
     *
     * @return array<int, array{code: string, label: string, debit: float, credit: float}>
     */
    private function journalRowsForRange(string $companyId, string $from, string $to): array
    {
        return DB::table('accounting_journal_entries')
            ->selectRaw('account_code AS code')
            ->selectRaw('MAX(account_label) AS label')
            ->selectRaw('COALESCE(SUM(debit), 0) AS debit')
            ->selectRaw('COALESCE(SUM(credit), 0) AS credit')
            ->where('company_id', $companyId)
            ->whereBetween('entry_date', [$from, $to])
            ->groupBy('account_code')
            ->orderBy('account_code')
            ->get()
            ->map(static fn (stdClass $row): array => [
                'code' => (string) $row->code,
                'label' => (string) $row->label,
                'debit' => (float) $row->debit,
                'credit' => (float) $row->credit,
            ])
            ->all();
    }

    /**
     * Écritures du journal agrégées par compte sur une période exacte
     * (colonne `period`, format YYYY-MM).
     *
     * @return array<int, array{code: string, label: string, debit: float, credit: float}>
     */
    private function journalRowsForPeriod(string $companyId, string $period): array
    {
        return DB::table('accounting_journal_entries')
            ->selectRaw('account_code AS code')
            ->selectRaw('MAX(account_label) AS label')
            ->selectRaw('COALESCE(SUM(debit), 0) AS debit')
            ->selectRaw('COALESCE(SUM(credit), 0) AS credit')
            ->where('company_id', $companyId)
            ->where('period', $period)
            ->groupBy('account_code')
            ->orderBy('account_code')
            ->get()
            ->map(static fn (stdClass $row): array => [
                'code' => (string) $row->code,
                'label' => (string) $row->label,
                'debit' => (float) $row->debit,
                'credit' => (float) $row->credit,
            ])
            ->all();
    }

    /**
     * Plan comptable du tenant indexé par code (tous les comptes, y compris
     * désactivés — un compte désactivé porteur d'écritures reste classable).
     *
     * @return array<string, array{type: AccountType, class: int, label: string}>
     */
    private function chartByCode(string $companyId): array
    {
        $chart = [];

        foreach (DB::table('accounting_chart_accounts')
            ->select(['code', 'label', 'type', 'class'])
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get() as $row) {
            $type = AccountType::tryFrom((string) $row->type);

            if ($type === null) {
                continue;
            }

            $chart[(string) $row->code] = [
                'type' => $type,
                'class' => (int) $row->class,
                'label' => (string) $row->label,
            ];
        }

        return $chart;
    }

    /**
     * Nature d'un compte du journal : plan comptable si présent, sinon
     * repli dérivé (classe du premier chiffre du code + intitulé du journal).
     *
     * @param  array<string, array{type: AccountType, class: int, label: string}>  $chart
     * @return array{type: AccountType, class: int, label: string}
     */
    private function resolveAccount(array $chart, string $code, string $journalLabel): array
    {
        if (isset($chart[$code])) {
            return $chart[$code];
        }

        $class = $this->classFromCode($code);

        return [
            'type' => $this->fallbackType($code, $class),
            'class' => $class,
            'label' => $journalLabel,
        ];
    }

    /**
     * Classe PCG/SCF dérivée du premier caractère du code (1→capitaux,
     * 2→immobilisations, 3→stocks, 4→tiers, 5→financier, 6→charges,
     * 7→produits, 8→spéciaux). 0 si le code ne commence pas par un chiffre.
     */
    private function classFromCode(string $code): int
    {
        $first = $code !== '' ? $code[0] : '';

        return preg_match('/^[1-8]$/', $first) === 1 ? (int) $first : 0;
    }

    /**
     * Repli de nature pour un compte absent du plan.
     *
     * Classe 4 (tiers) : les créances (41* clients, 4456 TVA déductible)
     * sont des actifs, tout le reste est traité comme une dette — heuristique
     * alignée sur le plan par défaut (ChartOfAccountsDefaults).
     */
    private function fallbackType(string $code, int $class): AccountType
    {
        return match ($class) {
            1 => AccountType::Equity,
            2, 3, 5 => AccountType::Asset,
            6 => AccountType::Expense,
            7 => AccountType::Revenue,
            4 => $this->isReceivableCode($code) ? AccountType::Asset : AccountType::Liability,
            default => AccountType::Liability,
        };
    }

    private function isReceivableCode(string $code): bool
    {
        return str_starts_with($code, '41') || str_starts_with($code, '4456');
    }

    /**
     * Regroupement des produits par section : 76 financiers, 77
     * exceptionnels, tout le reste en exploitation.
     */
    private function produitSection(string $code): string
    {
        if (str_starts_with($code, '76')) {
            return self::SECTION_PRODUITS_FINANCIERS;
        }

        if (str_starts_with($code, '77')) {
            return self::SECTION_PRODUITS_EXCEPTIONNELS;
        }

        return self::SECTION_PRODUITS_EXPLOITATION;
    }

    /**
     * Regroupement des charges par section : 66 financières, 67
     * exceptionnelles, tout le reste en exploitation.
     */
    private function chargeSection(string $code): string
    {
        if (str_starts_with($code, '66')) {
            return self::SECTION_CHARGES_FINANCIERES;
        }

        if (str_starts_with($code, '67')) {
            return self::SECTION_CHARGES_EXCEPTIONNELLES;
        }

        return self::SECTION_CHARGES_EXPLOITATION;
    }

    /**
     * Sections du bilan dans l'ordre canonique (classes listées), puis les
     * classes hors référentiel regroupées dans une section « Autres ».
     *
     * @param  array<int, array<string, array{code: string, label: string, balance: float}>>  $accountsByClass
     * @param  array<int, string>  $canonicalSections  classe → intitulé de section
     * @return list<array{section: string, accounts: list<array{code: string, label: string, balance: float}>, total: float}>
     */
    private function balanceSections(array $accountsByClass, array $canonicalSections, string $fallbackLabel): array
    {
        /** @var list<array{section: string, accounts: list<array{code: string, label: string, balance: float}>, total: float}> $sections */
        $sections = [];

        foreach ($canonicalSections as $class => $label) {
            $sections[] = $this->buildBalanceSection($label, $accountsByClass[$class] ?? []);
        }

        foreach (array_diff(array_keys($accountsByClass), array_keys($canonicalSections)) as $class) {
            $sections[] = $this->buildBalanceSection($fallbackLabel, $accountsByClass[$class]);
        }

        return $sections;
    }

    /**
     * @param  array<string, array{code: string, label: string, balance: float}>  $accountsByCode
     * @return array{section: string, accounts: list<array{code: string, label: string, balance: float}>, total: float}
     */
    private function buildBalanceSection(string $section, array $accountsByCode): array
    {
        $accounts = array_values($accountsByCode);
        $total = 0.0;

        foreach ($accounts as $account) {
            $total += $account['balance'];
        }

        return [
            'section' => $section,
            'accounts' => $accounts,
            'total' => round($total, 2),
        ];
    }

    /**
     * @param  list<array{section: string, accounts: list<array{code: string, label: string, balance: float}>, total: float}>  $sections
     */
    private function sumBalanceSections(array $sections): float
    {
        $total = 0.0;

        foreach ($sections as $section) {
            $total += $section['total'];
        }

        return $total;
    }

    /**
     * Compte de résultat : sections dans l'ordre canonique, puis sections
     * inattendues (codes hors 66/67/76/77 classés autrement) en complément.
     *
     * @param  array<string, array<string, array{code: string, label: string, amount: float}>>  $accountsBySection
     * @param  list<string>  $canonicalSections
     * @return array{sections: list<array{section: string, accounts: list<array{code: string, label: string, amount: float}>, total: float}>, total: float}
     */
    private function amountStatement(array $accountsBySection, array $canonicalSections): array
    {
        /** @var list<array{section: string, accounts: list<array{code: string, label: string, amount: float}>, total: float}> $sections */
        $sections = [];

        foreach ($canonicalSections as $label) {
            $sections[] = $this->buildAmountSection($label, $accountsBySection[$label] ?? []);
        }

        foreach (array_diff(array_keys($accountsBySection), $canonicalSections) as $label) {
            $sections[] = $this->buildAmountSection($label, $accountsBySection[$label]);
        }

        $total = 0.0;

        foreach ($sections as $section) {
            $total += $section['total'];
        }

        return [
            'sections' => $sections,
            'total' => round($total, 2),
        ];
    }

    /**
     * @param  array<string, array{code: string, label: string, amount: float}>  $accountsByCode
     * @return array{section: string, accounts: list<array{code: string, label: string, amount: float}>, total: float}
     */
    private function buildAmountSection(string $section, array $accountsByCode): array
    {
        $accounts = array_values($accountsByCode);
        $total = 0.0;

        foreach ($accounts as $account) {
            $total += $account['amount'];
        }

        return [
            'section' => $section,
            'accounts' => $accounts,
            'total' => round($total, 2),
        ];
    }
}
