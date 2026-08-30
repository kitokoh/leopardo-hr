<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Exports;

use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Support\CsvCellSanitizer;

/**
 * TRAVEL-505 (#6075) — Génération CSV des rapports travel.
 *
 * Colonnes ALLOWLISTÉES par type de rapport (aucune donnée libre) ; jeu
 * déterministe (tri par id) → un rejeu du job produit le MÊME fichier.
 * Cellules texte neutralisées contre l'injection de formules (CsvCellSanitizer,
 * #4169) ; montants numériques non préfixés.
 */
final class TravelCsvExporter
{
    /**
     * @var array<string, array<int, string>>
     */
    private const ALLOWED_COLUMNS = [
        'sales' => ['reference', 'created_at', 'trip_id', 'booking_source', 'status', 'passenger_count', 'total_amount_minor', 'currency'],
    ];

    public function generate(string $companyId, string $reportType, string $from, string $to): string
    {
        $columns = self::ALLOWED_COLUMNS[$reportType] ?? abort(422, sprintf('Unsupported report type "%s".', $reportType));

        $rows = $this->rowsFor($companyId, $reportType, $from, $to);

        $output = "\xEF\xBB\xBF";
        $output .= $this->line($columns);

        foreach ($rows as $row) {
            $output .= $this->line($this->sanitizeRow($row, $columns));
        }

        return $output;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rowsFor(string $companyId, string $reportType, string $from, string $to): array
    {
        if ($reportType !== 'sales') {
            return [];
        }

        return TravelBooking::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', [BookingStatus::CANCELLED->value, BookingStatus::REFUNDED->value])
            ->orderBy('id')
            ->get(['reference', 'created_at', 'trip_id', 'booking_source', 'status', 'passenger_count', 'total_amount_minor', 'currency'])
            ->map(fn (TravelBooking $booking): array => [
                'reference' => $booking->reference,
                'created_at' => $booking->created_at?->toIso8601String() ?? '',
                'trip_id' => (int) $booking->trip_id,
                'booking_source' => $booking->booking_source->value,
                'status' => $booking->status->value,
                'passenger_count' => (int) $booking->passenger_count,
                'total_amount_minor' => (int) $booking->total_amount_minor,
                'currency' => $booking->currency,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    private function sanitizeRow(array $row, array $columns): array
    {
        $out = [];

        foreach ($columns as $column) {
            $value = $row[$column] ?? '';
            $out[] = is_int($value) || is_float($value)
                ? (string) $value
                : CsvCellSanitizer::neutralize($value);
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function line(array $cells): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open CSV temp stream.');
        }

        fputcsv($handle, $cells, ';');
        rewind($handle);
        $line = (string) stream_get_contents($handle);
        fclose($handle);

        return $line;
    }
}
