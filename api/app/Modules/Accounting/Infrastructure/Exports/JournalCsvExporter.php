<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Exports;

use App\Modules\Accounting\Infrastructure\Services\JournalPostingService;
use App\Support\CsvCellSanitizer;
use Closure;

/**
 * Export CSV du journal comptable pour l'expert-comptable (issue #5234).
 *
 * Format standard exploitable : UTF-8 avec BOM (Excel), séparateur `;`,
 * colonnes `date;piece;libelle;compte;intitule;debit;credit`, ligne de
 * TOTAUX finale. Cellules texte neutralisées contre l'injection de formules
 * CSV (issue #4169) ; les montants restent numériques (jamais préfixés).
 */
final class JournalCsvExporter
{
    public function __construct(private readonly JournalPostingService $journal) {}

    /**
     * Génère la closure de streaming du CSV (utilisée par le controller via
     * response()->streamDownload()).
     */
    public function generateCsvClosure(string $period): Closure
    {
        return function () use ($period): void {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                throw new \RuntimeException('Impossible d\'ouvrir le flux CSV de sortie.');
            }

            // BOM UTF-8 pour compatibilité Excel.
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, ['date', 'piece', 'libelle', 'compte', 'intitule', 'debit', 'credit'], ';');

            foreach ($this->journal->entriesForPeriod($period) as $entry) {
                fputcsv($file, [
                    $entry->entry_date->toDateString(),
                    CsvCellSanitizer::neutralize((string) $entry->piece),
                    CsvCellSanitizer::neutralize((string) $entry->description),
                    $entry->account_code,
                    CsvCellSanitizer::neutralize((string) $entry->account_label),
                    number_format((float) $entry->debit, 2, '.', ''),
                    number_format((float) $entry->credit, 2, '.', ''),
                ], ';');
            }

            $totals = $this->journal->totalsForPeriod($period);
            fputcsv($file, [
                'TOTAL',
                '',
                '',
                '',
                '',
                number_format($totals['debit'], 2, '.', ''),
                number_format($totals['credit'], 2, '.', ''),
            ], ';');

            fclose($file);
        };
    }
}
