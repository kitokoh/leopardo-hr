<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Contracts\DocumentNumberingInterface;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use Illuminate\Support\Facades\DB;

/**
 * Numérotation séquentielle concurrent-safe des documents comptables
 * (issue #5223).
 *
 * Séries paramétrables par type via `AccountingSettings.number_series`
 * (ex. `['invoice' => 'FAC', ...]`), défauts par type sinon. Format :
 * `{SERIE}-{ANNEE}-{NUMERO}` (ex. FAC-2026-0001).
 *
 * L'incrément est ATOMIQUE : `INSERT ... ON CONFLICT (company_id, type,
 * series, year) DO UPDATE ... RETURNING last_number` — deux appels
 * concurrents obtiennent toujours des numéros distincts (pas de doublon,
 * pattern upsert exigé par la DoD).
 */
final class SequentialDocumentNumbering implements DocumentNumberingInterface
{
    private const DEFAULT_SERIES = [
        DocumentType::Invoice->value => 'FAC',
        DocumentType::Proforma->value => 'PRF',
        DocumentType::Quote->value => 'DEV',
        DocumentType::CreditNote->value => 'AVR',
        DocumentType::DeliveryNote->value => 'BL',
        DocumentType::Receipt->value => 'RCP',
    ];

    public function nextNumber(string $companyId, DocumentType $type): string
    {
        $series = $this->resolveSeries($companyId, $type);
        $year = (int) now()->format('Y');
        $number = $this->incrementCounter($companyId, $type, $series, $year);

        return sprintf('%s-%d-%04d', $series, $year, $number);
    }

    private function resolveSeries(string $companyId, DocumentType $type): string
    {
        $settings = AccountingSettings::query()
            ->where('company_id', $companyId)
            ->first();

        $configured = $settings?->number_series;

        if (is_array($configured) && isset($configured[$type->value])) {
            $value = $configured[$type->value];

            if (is_string($value) && trim($value) !== '') {
                return strtoupper(trim($value));
            }
        }

        return self::DEFAULT_SERIES[$type->value];
    }

    /**
     * Incrément atomique (upsert ON CONFLICT) — thread/concurrent-safe.
     */
    private function incrementCounter(string $companyId, DocumentType $type, string $series, int $year): int
    {
        $row = DB::selectOne(
            <<<'SQL'
            INSERT INTO accounting_number_counters (company_id, type, series, year, last_number, created_at, updated_at)
            VALUES (?, ?, ?, ?, 1, now(), now())
            ON CONFLICT (company_id, type, series, year)
            DO UPDATE SET last_number = accounting_number_counters.last_number + 1, updated_at = now()
            RETURNING last_number
            SQL,
            [$companyId, $type->value, $series, $year],
        );

        if ($row === null) {
            throw new \RuntimeException('accounting.numbering_increment_failed');
        }

        return (int) $row->last_number;
    }
}
