<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Exports;

use App\Modules\EduManager\Domain\Models\EduFeePayment;
use App\Support\CsvCellSanitizer;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Export CSV du journal des encaissements scolaires (#9791).
 *
 * Le guichet relisait sa journée à l'écran, page par page : impossible de
 * remettre un état de caisse à la comptabilité, ni de vérifier un dépôt
 * bancaire contre la liste réelle des encaissements. Cet export produit le
 * journal FILTRÉ (mêmes filtres que `GET /fee-payments`), sans pagination.
 *
 * Format : UTF-8 avec BOM (Excel), séparateur `;`, montants numériques à deux
 * décimales, ligne de TOTAUX finale. Les cellules texte sont neutralisées
 * contre l'injection de formules CSV (`CsvCellSanitizer`, issue #4169) — une
 * référence de bordereau saisie par un caissier est une donnée non fiable.
 *
 * Isolation : la requête est construite par l'appelant, DÉJÀ bornée au tenant.
 * L'exporteur ne décide jamais du périmètre — il ne fait que sérialiser.
 */
final class FeePaymentsCsvExporter
{
    /**
     * @param  Builder<EduFeePayment>  $query  requête bornée au tenant et filtrée
     */
    public function generateCsvClosure(Builder $query): Closure
    {
        return function () use ($query): void {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                throw new \RuntimeException('Impossible d\'ouvrir le flux CSV de sortie.');
            }

            // BOM UTF-8 : sans lui, Excel lit « Élève » en mojibake.
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'date',
                'eleve',
                'matricule',
                'facturation',
                'montant',
                'devise',
                'moyen',
                'reference',
            ], ';');

            $total = 0.0;
            $currency = null;

            // `cursor()` : le journal d'une année scolaire dépasse la mémoire
            // d'un worker PHP si on hydrate tout ; on streame ligne à ligne.
            foreach ($query->with('charge.student:id,student_number,display_name')->cursor() as $payment) {
                $student = $payment->charge?->student;
                $amount = (float) $payment->amount;
                $total += $amount;
                $currency ??= (string) $payment->currency;

                fputcsv($file, [
                    $payment->paid_at->toDateString(),
                    CsvCellSanitizer::neutralize($student?->display_name),
                    CsvCellSanitizer::neutralize($student?->student_number),
                    (int) $payment->fee_charge_id,
                    number_format($amount, 2, '.', ''),
                    CsvCellSanitizer::neutralize((string) $payment->currency),
                    CsvCellSanitizer::neutralize((string) $payment->method),
                    CsvCellSanitizer::neutralize($payment->reference),
                ], ';');
            }

            fputcsv($file, [
                'TOTAL',
                '',
                '',
                '',
                number_format($total, 2, '.', ''),
                CsvCellSanitizer::neutralize($currency),
                '',
                '',
            ], ';');

            fclose($file);
        };
    }
}
