<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Exports;

use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Support\CsvCellSanitizer;

/**
 * Export FEC — Fichier des Écritures Comptables (norme DGFiP, issue #5422).
 *
 * Format :
 *   - UTF-8 avec BOM, séparateur `;`, fins de ligne CRLF ;
 *   - 13 colonnes exactement (en-tête officiel DGFiP) ;
 *   - une ligne = une écriture comptable (débit OU crédit exclusif) ;
 *   - aucune ligne de TOTAUX (le FEC officiel n'en comporte pas) ;
 *   - montants au format français : virgule décimale, 2 décimales, cellule
 *     vide si zéro ; Devise = devise de l'écriture (DZD par défaut),
 *     MontantDevise identique au montant (même devise) ;
 *   - EcritureNum : numéro séquentiel de pièce 1..N sur la période (même
 *     numéro pour toutes les lignes d'une même pièce).
 *
 * Sécurité : toute cellule commençant par `= + - @ TAB CR` est préfixée d'une
 * apostrophe (neutralisation des formules CSV, issue #4169), puis chaque
 * cellule contenant `;`, `"` ou un saut de ligne est échappée RFC 4180
 * (doublage des guillemets) — la structure 13 colonnes est ainsi toujours
 * préservée.
 */
final class FecExporter
{
    /** Libellés lisibles des journaux FEC (fr). */
    private const JOURNAL_LIB = [
        'AC' => 'Journal des achats',
        'VE' => 'Journal des ventes',
        'TR' => 'Journal de trésorerie',
        'OD' => 'Opérations diverses',
    ];

    /** En-tête FEC officiel — exactement 13 colonnes. */
    private const HEADER = [
        'JournalCode',
        'JournalLib',
        'EcritureNum',
        'EcritureDate',
        'CompteNum',
        'CompteLib',
        'PieceRef',
        'PieceDate',
        'Libelle',
        'Debit',
        'Credit',
        'Devise',
        'MontantDevise',
    ];

    /** Longueur maximale du libellé (norme DGFiP). */
    private const LIBELLE_MAX_LENGTH = 500;

    /**
     * Génère le contenu CSV FEC complet (BOM + en-tête + lignes) pour la
     * société et la période données.
     */
    public function export(string $companyId, string $period, string $currency = 'DZD'): string
    {
        $entries = AccountingJournalEntry::query()
            ->where('company_id', $companyId)
            ->where('period', $period)
            ->orderBy('piece')
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $rows = [self::HEADER];

        // EcritureNum : compteur séquentiel 1..N sur la période, partagé par
        // toutes les lignes d'une même pièce (ordre : piece, entry_date, id).
        /** @var array<string, int> $pieceNumbers */
        $pieceNumbers = [];
        $nextNumber = 0;

        foreach ($entries as $entry) {
            $pieceKey = $entry->piece ?? '';

            if (! array_key_exists($pieceKey, $pieceNumbers)) {
                $nextNumber++;
                $pieceNumbers[$pieceKey] = $nextNumber;
            }

            $rows[] = $this->buildRow($entry, $pieceNumbers[$pieceKey], $currency);
        }

        return $this->render($rows);
    }

    /**
     * Construit une ligne FEC (13 cellules) pour une écriture.
     *
     * @return list<string>
     */
    private function buildRow(AccountingJournalEntry $entry, int $ecritureNum, string $currency): array
    {
        $journalCode = $this->journalCode($entry);
        $debit = $entry->debit > 0.0 ? number_format($entry->debit, 2, ',', '') : '';
        $credit = $entry->credit > 0.0 ? number_format($entry->credit, 2, ',', '') : '';
        $amount = $debit !== '' ? $debit : $credit;
        $date = $entry->entry_date->format('Ymd');

        return [
            $this->cell($journalCode),
            $this->cell(self::JOURNAL_LIB[$journalCode] ?? 'Opérations diverses'),
            $this->cell((string) $ecritureNum),
            $this->cell($date),
            $this->cell($entry->account_code),
            $this->cell($this->cleanText($entry->account_label)),
            $this->cell($this->cleanText($entry->piece)),
            $this->cell($date),
            $this->cell($this->libelle($entry)),
            $this->cell($debit),
            $this->cell($credit),
            $this->cell($currency),
            $this->cell($amount),
        ];
    }

    /**
     * Journal FEC d'une écriture :
     *   - TR (trésorerie) pour les paiements ;
     *   - AC (achats) pour un document sur compte 6xx ;
     *   - VE (ventes) pour un document sur compte 7xx ou 411 ;
     *   - OD (opérations diverses) sinon.
     */
    private function journalCode(AccountingJournalEntry $entry): string
    {
        if ($entry->source_type === 'payment') {
            return 'TR';
        }

        if ($entry->source_type === 'document') {
            if (str_starts_with($entry->account_code, '6')) {
                return 'AC';
            }

            if (str_starts_with($entry->account_code, '7') || $entry->account_code === '411') {
                return 'VE';
            }
        }

        return 'OD';
    }

    /**
     * Libellé FEC : description de l'écriture, ou référence de pièce si
     * absente, nettoyé (retours chariot/sauts de ligne remplacés par un
     * espace) et tronqué à 500 caractères.
     */
    private function libelle(AccountingJournalEntry $entry): string
    {
        $libelle = $entry->description ?? $entry->piece ?? '';

        return $this->truncate($this->cleanText($libelle), self::LIBELLE_MAX_LENGTH);
    }

    /**
     * Nettoyage d'une cellule texte : trim + remplacement des retours
     * chariot / sauts de ligne par un espace unique (le FEC est un fichier
     * ligne-à-ligne — aucune cellule ne doit contenir de CR/LF).
     */
    private function cleanText(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return preg_replace('/[\r\n]+/', ' ', trim($value)) ?? '';
    }

    /** Tronque à $maxLength caractères (portable sans ext-mbstring). */
    private function truncate(string $value, int $maxLength): string
    {
        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return function_exists('mb_substr')
            ? mb_substr($value, 0, $maxLength)
            : substr($value, 0, $maxLength);
    }

    /**
     * Neutralise l'injection de formule CSV (OWASP) : toute cellule commençant
     * par `=`, `+`, `-`, `@`, tabulation ou retour-chariot est préfixée d'une
     * apostrophe (les montants formatés ne commencent jamais par ces
     * caractères et passent inchangés).
     */
    private function cell(string $value): string
    {
        return CsvCellSanitizer::neutralize($value);
    }

    /**
     * Assemble le fichier : BOM UTF-8, lignes séparées par CRLF, cellules
     * échappées RFC 4180 (guillemets doublés) quand elles contiennent `;`,
     * `"`, CR ou LF.
     *
     * @param  list<list<string>>  $rows
     */
    private function render(array $rows): string
    {
        $lines = [];

        foreach ($rows as $row) {
            $cells = [];

            foreach ($row as $cell) {
                $cells[] = $this->escape($cell);
            }

            $lines[] = implode(';', $cells);
        }

        return "\xEF\xBB\xBF".implode("\r\n", $lines)."\r\n";
    }

    /** Échappement RFC 4180 d'une cellule CSV. */
    private function escape(string $cell): string
    {
        if (strpbrk($cell, ";\r\n\"") === false) {
            return $cell;
        }

        return '"'.str_replace('"', '""', $cell).'"';
    }
}
