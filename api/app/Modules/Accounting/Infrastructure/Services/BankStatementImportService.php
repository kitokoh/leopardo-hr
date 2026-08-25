<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Enums\BankStatementStatus;
use App\Modules\Accounting\Domain\Exceptions\BankStatementDuplicateImportException;
use App\Modules\Accounting\Domain\Exceptions\BankStatementImportFormatException;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use App\Modules\Accounting\Domain\Models\BankStatement;
use App\Modules\Accounting\Domain\Models\BankStatementLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Import CSV de relevé bancaire — rapprochement bancaire #5435.
 *
 * Mapping configurable par entreprise (`accounting_settings.bank_statement_mapping`) :
 * colonnes date/label/amount/reference, séparateur, format de date, signe.
 * Validation stricte ligne par ligne (aucune ligne insérée si l'en-tête est
 * invalide ; lignes invalides signalées dans `errors[]` sans échec silencieux).
 * Idempotence : unique (company_id, statement_period, import_reference) → 409.
 *
 * Le pattern de parsing (str_getcsv par ligne + erreurs par ligne) suit
 * EmployeeImportController (#3726).
 */
final class BankStatementImportService
{
    /** Valeurs par défaut du mapping (entreprise sans paramétrage). */
    private const DEFAULT_MAPPING = [
        'columns' => ['date' => 'date', 'label' => 'label', 'amount' => 'amount', 'reference' => 'reference'],
        'separator' => ';',
        'date_format' => 'Y-m-d',
        'sign' => 'positive_debit', // positive_debit | positive_credit
        'tolerance_amount' => 0.01,
        'tolerance_days' => 3,
    ];

    /** @var array<string, mixed> */
    private array $mapping;

    public function __construct()
    {
        $this->mapping = self::DEFAULT_MAPPING;
    }

    /**
     * Importe un relevé bancaire depuis un contenu CSV.
     *
     * @param  string  $companyId  tenant courant
     * @param  string  $statementPeriod  ex. "2026-08"
     * @param  string  $importReference  réf. externe du relevé
     * @param  string  $csvContent  contenu brut du fichier
     * @return array{statement: BankStatement, imported: int, skipped: int, errors: list<array{line: int, error: string}>}
     *
     * @throws BankStatementDuplicateImportException
     * @throws BankStatementImportFormatException
     */
    public function import(string $companyId, string $statementPeriod, string $importReference, string $csvContent): array
    {
        $this->loadMapping($companyId);

        $duplicate = BankStatement::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('statement_period', $statementPeriod)
            ->where('import_reference', $importReference)
            ->exists();
        if ($duplicate) {
            throw new BankStatementDuplicateImportException($statementPeriod, $importReference);
        }

        $rows = $this->parseRows($csvContent);
        /** @var string $headersLine */
        $headersLine = array_shift($rows) ?? '';
        /** @var list<string> $headers */
        $headers = array_map(static fn (mixed $h): string => strtolower(trim((string) $h)), str_getcsv($headersLine, $this->separator()));
        $this->assertHeaders($headers);

        $fileHash = hash('sha256', $csvContent);
        $imported = 0;
        $errors = [];

        $statement = DB::transaction(function () use ($companyId, $statementPeriod, $importReference, $fileHash, $rows, $headers, &$imported, &$errors): BankStatement {
            $statement = BankStatement::query()->withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'statement_period' => $statementPeriod,
                'import_reference' => $importReference,
                'status' => BankStatementStatus::Imported->value,
                'file_hash' => $fileHash,
            ]);

            $columns = $this->columns();
            $colDate = $columns['date'];
            $colLabel = $columns['label'];
            $colAmount = $columns['amount'];
            $colReference = $columns['reference'];

            foreach ($rows as $index => $rawLine) {
                $lineNumber = $index + 2; // 1-based, en-tête = ligne 1
                $fields = $this->parseLine([$rawLine]);
                if ($fields === []) {
                    $errors[] = ['line' => $lineNumber, 'error' => __('accounting.errors.bank_line_empty')];

                    continue;
                }

                $row = [];
                foreach ($headers as $i => $header) {
                    $row[(string) $header] = $fields[$i] ?? '';
                }

                $dateValue = trim((string) ($row[$colDate] ?? ''));
                $label = trim((string) ($row[$colLabel] ?? ''));
                $amountValue = trim((string) ($row[$colAmount] ?? ''));
                $reference = $colReference !== null ? trim((string) ($row[$colReference] ?? '')) : null;

                $date = $this->parseDate($dateValue);
                if ($date === null) {
                    $errors[] = ['line' => $lineNumber, 'error' => __('accounting.errors.bank_line_invalid_date', ['value' => $dateValue])];

                    continue;
                }
                if ($label === '') {
                    $errors[] = ['line' => $lineNumber, 'error' => __('accounting.errors.bank_line_missing_label')];

                    continue;
                }
                $amount = $this->parseAmount($amountValue);
                if ($amount === null) {
                    $errors[] = ['line' => $lineNumber, 'error' => __('accounting.errors.bank_line_invalid_amount', ['value' => $amountValue])];

                    continue;
                }

                BankStatementLine::query()->withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'statement_id' => $statement->id,
                    'line_number' => $lineNumber,
                    'line_date' => $date->toDateString(),
                    'label' => $label,
                    'amount' => $amount,
                    'external_reference' => $reference !== '' ? $reference : null,
                ]);
                $imported++;
            }

            return $statement;
        });

        return [
            'statement' => $statement,
            'imported' => $imported,
            'skipped' => count($rows) - $imported,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapping(): array
    {
        return $this->mapping;
    }

    private function loadMapping(string $companyId): void
    {
        /** @var AccountingSettings|null $settings */
        $settings = AccountingSettings::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->first();

        $configured = $settings?->bank_statement_mapping;
        if (! is_array($configured)) {
            return;
        }

        /** @var array<string, mixed> $merged */
        $merged = array_replace_recursive(self::DEFAULT_MAPPING, $configured);
        $this->mapping = $merged;
        $columns = $this->columns();
        $this->mapping['columns'] = $columns;
    }

    private function separator(): string
    {
        $separator = $this->mapping['separator'] ?? ';';

        return is_string($separator) ? $separator : ';';
    }

    private function dateFormat(): string
    {
        $format = $this->mapping['date_format'] ?? 'Y-m-d';

        return is_string($format) ? $format : 'Y-m-d';
    }

    private function sign(): string
    {
        $sign = $this->mapping['sign'] ?? 'positive_debit';

        return is_string($sign) ? $sign : 'positive_debit';
    }

    /**
     * @return array{date: string, label: string, amount: string, reference: string|null}
     */
    private function columns(): array
    {
        /** @var array<mixed> $columns */
        $columns = $this->mapping['columns'] ?? [];
        $date = $columns['date'] ?? null;
        $label = $columns['label'] ?? null;
        $amount = $columns['amount'] ?? null;
        $reference = $columns['reference'] ?? null;

        return [
            'date' => is_string($date) ? $date : 'date',
            'label' => is_string($label) ? $label : 'label',
            'amount' => is_string($amount) ? $amount : 'amount',
            'reference' => is_string($reference) ? $reference : null,
        ];
    }

    /**
     * @return list<string>
     */
    private function parseRows(string $csvContent): array
    {
        $normalized = str_replace("\r\n", "\n", $csvContent);
        $normalized = str_replace("\r", "\n", $normalized);
        $rawLines = array_values(array_filter(explode("\n", $normalized), static fn (string $l): bool => trim($l) !== ''));
        if ($rawLines === []) {
            throw new BankStatementImportFormatException(__('accounting.errors.bank_empty_file'));
        }

        return $rawLines;
    }

    /**
     * @param  list<string>  $headers
     *
     * @throws BankStatementImportFormatException
     */
    private function assertHeaders(array $headers): void
    {
        $columns = $this->columns();
        $required = [
            $columns['date'],
            $columns['label'],
            $columns['amount'],
        ];

        $missing = array_values(array_filter($required, static fn (string $c): bool => ! in_array($c, $headers, true)));
        if ($missing !== []) {
            throw new BankStatementImportFormatException(
                __('accounting.errors.bank_missing_columns', ['columns' => implode(', ', $missing)])
            );
        }
    }

    /**
     * @param  list<string>  $rawLine
     * @return list<string>
     */
    private function parseLine(array $rawLine): array
    {
        return array_map(
            static fn (mixed $v): string => trim((string) $v),
            str_getcsv((string) ($rawLine[0] ?? ''), $this->separator())
        );
    }

    private function parseDate(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            $date = Carbon::createFromFormat($this->dateFormat(), $value);
        } catch (\Throwable) {
            return null;
        }

        return $date;
    }

    private function parseAmount(string $value): ?float
    {
        $normalized = str_replace([',', ' '], ['.', ''], $value);
        if (! is_numeric($normalized)) {
            return null;
        }

        $amount = (float) $normalized;
        if ($this->sign() === 'positive_credit') {
            return -$amount;
        }

        return $amount;
    }
}
