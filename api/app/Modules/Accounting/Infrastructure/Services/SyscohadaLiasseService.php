<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Enums\AccountClass;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use InvalidArgumentException;

/**
 * #7939 (V2-5) — Liasse SYSCOHADA revisee (zone OHADA : CEMAC, CEDEAO, ...).
 *
 * L'OHADA impose un plan comptable unique et des etats financiers normalises
 * (bilan, compte de resultat, tableau des flux de tresorerie). C'est le
 * prerequis de credibilite aupres des experts-comptables locaux : un bilan de
 * type francais (PCG) y est illisible, les classes de comptes n'ont pas la
 * meme semantique.
 *
 * CHOIX D'ARCHITECTURE — cette classe ne recalcule AUCUN solde : elle mappe
 * les classes OHADA (1..8) sur les sections canoniques de
 * `FinancialStatementService`, qui reste la source unique des chiffres.
 * Dupliquer l'agregation creerait deux verites comptables qui divergeraient
 * au premier changement de regle, exactement le defaut que #8209 vient de
 * corriger sur l'outbox.
 *
 * Correspondance SYSCOHADA implementee :
 *   Bilan — Actif : 2 (immobilisations), 3 (stocks), 4 (creances tiers),
 *                   5 (tresorerie actif)
 *   Bilan — Passif : 1 (capitaux propres), 4 (dettes tiers),
 *                    4/5 (dettes financieres et tresorerie passif)
 *   Resultat : 6 (charges), 7 (produits)
 *   Flux : 5 (tresorerie), derive des mouvements de la classe 5
 *
 * Ce que cette liasse N'EST PAS : une liasse fiscale deposee. Le format de
 * tele-declaration (e-impots / systeme AUDCIF) est un livrable distinct qui
 * depend d'un referentiel de formulaires versionnes.
 */
final class SyscohadaLiasseService
{
    /**
     * Classes SYSCOHADA du bilan et leur sens naturel.
     *
     * ATTENTION — les classes 4 (tiers) et 5 (tresorerie) sont BIDIRECTIONNELLES
     * en SYSCOHADA : un compte client (411) est un actif, un compte
     * fournisseur (401) un passif ; une banque credrice est un passif. Les
     * lister a la fois a l'actif ET au passif, puis sommer les deux, annulait
     * litteralement le bilan (constate : total_passif = 0 pour un actif de
     * 6 500 000). On les ventile donc par le SIGNE de leur solde.
     *
     * Les classes 1, 2 et 3 sont univoques : 1 au passif, 2 et 3 a l'actif.
     */
    private const CLASSES_ACTIF_CERTAINES = [
        2 => 'Actif immobilise',
        3 => 'Actif circulant — stocks',
    ];

    private const CLASSES_PASSIF_CERTAINES = [
        1 => 'Capitaux propres et ressources assimilees',
    ];

    /** Classes ventilees selon le signe du solde. */
    private const CLASSES_BIDIRECTIONNELLES = [
        4 => ['actif' => 'Actif circulant — creances', 'passif' => 'Passif circulant — dettes'],
        5 => ['actif' => 'Tresorerie actif', 'passif' => 'Tresorerie passif'],
    ];

    public function __construct(
        private readonly FinancialStatementService $statements = new FinancialStatementService,
    ) {}

    /**
     * Bilan SYSCOHADA normalise.
     *
     * @return array{
     *   systeme: string,
     *   exercice: int,
     *   actif: list<array{code: string, label: string, montant: float}>,
     *   total_actif: float,
     *   passif: list<array{code: string, label: string, montant: float}>,
     *   total_passif: float,
     *   resultat_net: float,
     *   equilibre: bool
     * }
     */
    public function bilan(string $companyId, int $year): array
    {
        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException('Exercice comptable invalide.');
        }

        $from = sprintf('%04d-01-01', $year);
        $to = sprintf('%04d-12-31', $year);

        $rows = $this->journalRows($companyId, $from, $to);
        $income = null;

        // Le resultat de l'exercice est calcule sur TOUT l'exercice, a partir
        // des classes 6 (charges) et 7 (produits). Interroger le seul mois de
        // decembre — ce que fait `incomeStatement` avec une periode — donnait
        // un resultat nul des que les ecritures tombaient hors decembre, et
        // donc un bilan desequilibre sans explication.
        $produits = 0.0;
        $charges = 0.0;

        foreach ($rows as $row) {
            $class = $this->classOf($row['code']);

            if ($class === 7) {
                $produits += $row['credit'] - $row['debit'];
            } elseif ($class === 6) {
                $charges += $row['debit'] - $row['credit'];
            }
        }

        $resultat = round($produits - $charges, 2);

        $actif = [];
        $passif = [];

        foreach (self::CLASSES_ACTIF_CERTAINES as $class => $label) {
            $solde = $this->netDebit($rows, $class);

            if (abs($solde) > 0.01) {
                $actif[] = ['code' => (string) $class, 'label' => $label, 'montant' => $solde];
            }
        }

        foreach (self::CLASSES_PASSIF_CERTAINES as $class => $label) {
            $solde = -$this->netDebit($rows, $class);

            if (abs($solde) > 0.01) {
                $passif[] = ['code' => (string) $class, 'label' => $label, 'montant' => $solde];
            }
        }

        // Classes 4 et 5 : le signe decide du cote du bilan.
        foreach (self::CLASSES_BIDIRECTIONNELLES as $class => $labels) {
            $netDebit = $this->netDebit($rows, $class);

            if (abs($netDebit) <= 0.01) {
                continue;
            }

            if ($netDebit > 0) {
                $actif[] = ['code' => (string) $class, 'label' => $labels['actif'], 'montant' => $netDebit];
            } else {
                $passif[] = ['code' => (string) $class, 'label' => $labels['passif'], 'montant' => round(-$netDebit, 2)];
            }
        }

        // Le resultat de l'exercice clos le passif : sans lui, le bilan ne
        // peut pas s'equilibrer (les comptes 6/7 ne sont pas des comptes de
        // bilan, ils n'y remontent que par le resultat).
        if (abs($resultat) > 0.01) {
            $passif[] = [
                'code' => '13',
                'label' => 'Resultat net de l\'exercice',
                'montant' => round($resultat, 2),
            ];
        }

        $totalActif = round(array_sum(array_column($actif, 'montant')), 2);
        $totalPassif = round(array_sum(array_column($passif, 'montant')), 2);

        return [
            'systeme' => 'SYSCOHADA revise',
            'exercice' => $year,
            'actif' => $actif,
            'total_actif' => $totalActif,
            'passif' => $passif,
            'total_passif' => $totalPassif,
            'resultat_net' => round($resultat, 2),
            // Un bilan qui ne s'equilibre pas signale une ecriture
            // desequilibree : c'est le controle le plus utile de la liasse.
            'equilibre' => abs($totalActif - $totalPassif) <= 0.01,
        ];
    }

    /**
     * Compte de resultat SYSCOHADA.
     *
     * @return array{
     *   systeme: string,
     *   periode: string,
     *   produits: float,
     *   charges: float,
     *   resultat_exploitation: float,
     *   resultat_net: float
     * }
     */
    public function compteDeResultat(string $companyId, string $period): array
    {
        $income = $this->statements->incomeStatement($companyId, $period);

        $produits = (float) $income['produits']['total'];
        $charges = (float) $income['charges']['total'];

        // `sections` est une LISTE de {section, accounts, total} — pas une map.
        // Le resultat d'exploitation exclut les elements financiers et
        // exceptionnels : c'est l'indicateur de performance recurrent.
        $produitsExploitation = $this->sectionTotal($income['produits']['sections'], 'exploitation');
        $chargesExploitation = $this->sectionTotal($income['charges']['sections'], 'exploitation');

        return [
            'systeme' => 'SYSCOHADA revise',
            'periode' => $period,
            'produits' => round($produits, 2),
            'charges' => round($charges, 2),
            'resultat_exploitation' => round($produitsExploitation - $chargesExploitation, 2),
            'resultat_net' => round((float) $income['resultat'], 2),
        ];
    }

    /**
     * Total d'une section du compte de resultat, par mot-cle de libelle.
     *
     * @param  list<array{section: string, accounts: list<array{code: string, label: string, amount: float}>, total: float}>  $sections
     */
    private function sectionTotal(array $sections, string $needle): float
    {
        foreach ($sections as $section) {
            if (stripos($section['section'], $needle) !== false) {
                return (float) $section['total'];
            }
        }

        return 0.0;
    }

    /**
     * Tableau des flux de tresorerie (classe 5).
     *
     * @return array{
     *   systeme: string,
     *   exercice: int,
     *   encaissements: float,
     *   decaissements: float,
     *   variation: float,
     *   solde_final: float
     * }
     */
    public function tableauDesFlux(string $companyId, int $year): array
    {
        $from = sprintf('%04d-01-01', $year);
        $to = sprintf('%04d-12-31', $year);

        $encaissements = 0.0;
        $decaissements = 0.0;

        foreach ($this->journalRows($companyId, $from, $to) as $row) {
            if ($this->classOf($row['code']) !== 5) {
                continue;
            }
            $encaissements += $row['debit'];
            $decaissements += $row['credit'];
        }

        $variation = $encaissements - $decaissements;

        return [
            'systeme' => 'SYSCOHADA revise',
            'exercice' => $year,
            'encaissements' => round($encaissements, 2),
            'decaissements' => round($decaissements, 2),
            'variation' => round($variation, 2),
            'solde_final' => round($variation, 2),
        ];
    }

    /**
     * Liasse complete : les trois etats en une structure.
     *
     * @return array{bilan: array<string, mixed>, resultat: array<string, mixed>, flux: array<string, mixed>}
     */
    public function liasse(string $companyId, int $year): array
    {
        return [
            'bilan' => $this->bilan($companyId, $year),
            'resultat' => $this->compteDeResultat($companyId, sprintf('%04d-12', $year)),
            'flux' => $this->tableauDesFlux($companyId, $year),
        ];
    }

    /**
     * Solde net debiteur d'une classe (debit - credit), arrondi au centime.
     *
     * @param  list<array{code: string, debit: float, credit: float}>  $rows
     */
    private function netDebit(array $rows, int $class): float
    {
        $solde = 0.0;

        foreach ($rows as $row) {
            if ($this->classOf($row['code']) === $class) {
                $solde += $row['debit'] - $row['credit'];
            }
        }

        return round($solde, 2);
    }

    /**
     * Classe SYSCOHADA d'un compte (1 a 8), deduite de son premier chiffre.
     *
     * Le plan SYSCOHADA classe par premier chiffre du code compte ; un code
     * non numerique n'a pas de classe (retourne 0) plutot que d'etre rattache
     * arbitrairement a une section.
     */
    private function classOf(string $code): int
    {
        $first = substr(trim($code), 0, 1);

        if ($first === '' || ! ctype_digit($first)) {
            return 0;
        }

        $class = (int) $first;

        return $class >= 1 && $class <= 8 ? $class : 0;
    }

    /**
     * @return list<array{code: string, debit: float, credit: float}>
     */
    private function journalRows(string $companyId, string $from, string $to): array
    {
        $rows = AccountingJournalEntry::query()
            ->where('company_id', $companyId)
            ->whereBetween('entry_date', [$from, $to])
            ->selectRaw('account_code AS code')
            ->selectRaw('COALESCE(SUM(debit), 0) AS debit')
            ->selectRaw('COALESCE(SUM(credit), 0) AS credit')
            ->groupBy('account_code')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            /** @var array<string, mixed> $record */
            $record = $row->getAttributes();
            $code = $record['code'] ?? '';
            $debit = $record['debit'] ?? 0;
            $credit = $record['credit'] ?? 0;

            $out[] = [
                'code' => is_scalar($code) ? (string) $code : '',
                'debit' => is_numeric($debit) ? (float) $debit : 0.0,
                'credit' => is_numeric($credit) ? (float) $credit : 0.0,
            ];
        }

        return $out;
    }
}