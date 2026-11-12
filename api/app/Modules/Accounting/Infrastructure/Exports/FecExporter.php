<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Exports;

use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingJournal;
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
 *     vide si zéro ; Devise = devise de l'écriture, résolue depuis le pays
 *     du tenant par l'appelant (issue #7927 — EUR pour FR, DZD pour DZ…),
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
    /**
     * Cache des types de document pendant l'export (clé company#document) :
     * une facture porte plusieurs lignes, on ne requête pas une fois par ligne.
     *
     * @var array<string, string|null>
     */
    private array $documentTypes = [];

    /**
     * Cache des libellés de journal (clé company#code).
     *
     * @var array<string, string|null>
     */
    private array $journalLabels = [];

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
     * Nom de fichier FEC officiel pour la France (issue #7927) — arrêté du
     * 29/07/2013 codifié à l'art. A. 47 A-1 du LPF : `SIRENFECAAAAMMJJ`, où
     * AAAAMMJJ est la date de clôture de l'exercice comptable. Les exercices
     * du module sont civils (accounting_fiscal_years.year) : la clôture est
     * le 31/12 de l'année de la période exportée.
     */
    public static function officialFrFilename(string $siren, int $closingYear): string
    {
        return sprintf('%sFEC%d1231.txt', $siren, $closingYear);
    }

    /**
     * SIREN valide = exactement 9 chiffres (INSEE). La clé de Luhn n'est
     * pas vérifiée ici (réglage déclaratif, validé en format).
     */
    public static function isValidSiren(string $siren): bool
    {
        return preg_match('/^\d{9}$/', $siren) === 1;
    }

    /**
     * SIREN du tenant depuis les métadonnées de l'entreprise (convention
     * métadata plate, comme `siret`/`company_iban`) : clé `siren` dédiée,
     * sinon dérivé des 9 premiers chiffres d'un SIRET 14 chiffres.
     * Retourne null si aucun réglage exploitable (pas de valeur devinée).
     *
     * @param  array<mixed>|null  $metadata
     */
    public static function resolveSiren(?array $metadata): ?string
    {
        $siren = $metadata['siren'] ?? null;

        if (is_string($siren) && trim($siren) !== '') {
            return trim($siren);
        }

        $siret = $metadata['siret'] ?? null;

        if (is_string($siret) && preg_match('/^\d{14}$/', trim($siret)) === 1) {
            return substr(trim($siret), 0, 9);
        }

        return null;
    }

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
        $journalLabel = $entry->journal_code !== null
            ? $this->journalLabel($entry, $journalCode)
            : (self::JOURNAL_LIB[$journalCode] ?? 'Opérations diverses');
        $debit = $entry->debit > 0.0 ? number_format($entry->debit, 2, ',', '') : '';
        $credit = $entry->credit > 0.0 ? number_format($entry->credit, 2, ',', '') : '';
        $amount = $debit !== '' ? $debit : $credit;
        $date = $entry->entry_date->format('Ymd');

        // #9373 — la colonne Devise est celle de **l'écriture**, pas celle de la
        // company : une ligne comptabilisée en DZD issue d'une facture en EUR
        // doit porter DZD (c'est la devise du montant des colonnes Débit/Crédit),
        // et MontantDevise reste égal au montant puisque le journal est tenu dans
        // une seule devise. L'ancien comportement écrivait la devise de la
        // company sur des montants exprimés dans une autre devise.
        $entryCurrency = is_string($entry->currency) && $entry->currency !== ''
            ? $entry->currency
            : $currency;

        return [
            $this->cell($journalCode),
            $this->cell($journalLabel),
            $this->cell((string) $ecritureNum),
            $this->cell($date),
            $this->cell($entry->account_code),
            $this->cell($this->cleanText($entry->account_label)),
            $this->cell($this->cleanText($entry->piece)),
            $this->cell($date),
            $this->cell($this->libelle($entry)),
            $this->cell($debit),
            $this->cell($credit),
            $this->cell($entryCurrency),
            $this->cell($amount),
        ];
    }

    /**
     * Libellé du journal tel que paramétré par le tenant (entité #9370), avec
     * repli sur les libellés FEC standards pour les codes connus et, en dernier
     * recours, le code lui-même — jamais un libellé inventé.
     */
    private function journalLabel(AccountingJournalEntry $entry, string $journalCode): string
    {
        $key = $entry->company_id.'#'.$journalCode;

        if (array_key_exists($key, $this->journalLabels)) {
            return $this->journalLabels[$key] ?? (self::JOURNAL_LIB[$journalCode] ?? $journalCode);
        }

        $label = AccountingJournal::query()
            ->withoutGlobalScopes()
            ->where('company_id', $entry->company_id)
            ->where('code', $journalCode)
            ->value('label');

        $this->journalLabels[$key] = is_string($label) && $label !== '' ? $label : null;

        return $this->journalLabels[$key] ?? (self::JOURNAL_LIB[$journalCode] ?? $journalCode);
    }

    /**
     * Journal FEC d'une écriture.
     *
     * #9367/#9370 — `journal_code` (entité journal) et le type de document
     * source font foi : une facture d'achat va en `AC` quel que soit le préfixe
     * de son compte (un tenant turc poste `153`, un tenant PCG `607`) et une
     * facture de vente va en `VE` y compris pour sa ligne client. L'ancienne
     * heuristique sur le préfixe de compte classait `411` en `VE` mais `120` en
     * `OD`, et séparait une même facture de son encaissement.
     *
     * Priorité : `journal_code` → source explicite (document/paiement) →
     * préfixe de compte (filet de sécurité pour les écritures historiques).
     */
    private function journalCode(AccountingJournalEntry $entry): string
    {
        // #9370 — la colonne `journal_code` fait foi dès qu'elle est renseignée :
        // le journal est une entité paramétrable, plus une déduction à la lecture.
        if (is_string($entry->journal_code) && $entry->journal_code !== '') {
            return $entry->journal_code;
        }

        if ($entry->source_type === 'payment') {
            return 'TR';
        }

        if ($entry->source_type === 'document') {
            $type = $this->documentTypeFor((int) $entry->source_id, (string) $entry->company_id);

            if ($type !== null) {
                if (in_array($type, [DocumentType::PurchaseInvoice->value, DocumentType::PurchaseCreditNote->value], true)) {
                    return 'AC';
                }

                if (in_array($type, [DocumentType::Invoice->value, DocumentType::CreditNote->value], true)) {
                    return 'VE';
                }
            }

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
     * Type du document source, mémorisé le temps de l'export (une facture =
     * plusieurs lignes → une seule requête par document).
     */
    private function documentTypeFor(int $documentId, string $companyId): ?string
    {
        $key = $companyId.'#'.$documentId;

        if (array_key_exists($key, $this->documentTypes)) {
            return $this->documentTypes[$key];
        }

        $type = AccountingDocument::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereKey($documentId)
            ->value('type');

        return $this->documentTypes[$key] = is_string($type) ? $type : null;
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
