<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Exports;

use App\Modules\EduManager\Domain\Models\EduAccountingEntry;
use App\Support\CsvCellSanitizer;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Export CSV du rapprochement comptable des frais scolaires (#9791).
 *
 * L'écran de rapprochement affichait un contrôle d'équilibre débit/crédit,
 * mais rien ne sortait du produit : l'expert-comptable ne pouvait pas
 * récupérer les lignes pour les rapprocher de sa propre comptabilité, et le
 * contrôle d'équilibre n'était vérifiable qu'à l'œil, page par page.
 *
 * Format : UTF-8 avec BOM, séparateur `;`, montants numériques à deux
 * décimales, DEUX lignes de totaux finales — `TOTAL` (somme des lignes
 * exportées) et `EQUILIBRE` (écart débit − crédit). L'écart est écrit dans le
 * fichier pour que le contrôle survive à l'export : un fichier déséquilibré se
 * voit à l'ouverture, sans recalcul manuel.
 *
 * Isolation : la requête est construite par l'appelant, DÉJÀ bornée au tenant.
 */
final class FeeAccountingEntriesCsvExporter
{
    /**
     * @param  Builder<EduAccountingEntry>  $query  requête bornée au tenant et filtrée
     */
    public function generateCsvClosure(Builder $query): Closure
    {
        return function () use ($query): void {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                throw new \RuntimeException('Impossible d\'ouvrir le flux CSV de sortie.');
            }

            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'date',
                'source',
                'source_id',
                'compte',
                'intitule',
                'debit',
                'credit',
                'reference',
            ], ';');

            $totalDebit = 0.0;
            $totalCredit = 0.0;

            foreach ($query->cursor() as $entry) {
                $debit = (float) $entry->debit;
                $credit = (float) $entry->credit;
                $totalDebit += $debit;
                $totalCredit += $credit;

                fputcsv($file, [
                    $entry->entry_date->toDateString(),
                    CsvCellSanitizer::neutralize((string) $entry->source_type),
                    (int) $entry->source_id,
                    CsvCellSanitizer::neutralize((string) $entry->account_code),
                    CsvCellSanitizer::neutralize((string) $entry->account_label),
                    number_format($debit, 2, '.', ''),
                    number_format($credit, 2, '.', ''),
                    CsvCellSanitizer::neutralize((string) $entry->reference),
                ], ';');
            }

            fputcsv($file, [
                'TOTAL',
                '',
                '',
                '',
                '',
                number_format($totalDebit, 2, '.', ''),
                number_format($totalCredit, 2, '.', ''),
                '',
            ], ';');

            // Écart arrondi au centime : les flottants accumulés sur des
            // milliers de lignes ne tombent pas exactement sur 0.
            fputcsv($file, [
                'EQUILIBRE',
                '',
                '',
                '',
                '',
                number_format(round($totalDebit - $totalCredit, 2), 2, '.', ''),
                '',
                '',
            ], ';');

            fclose($file);
        };
    }
}
