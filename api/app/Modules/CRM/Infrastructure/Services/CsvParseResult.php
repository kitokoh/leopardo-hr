<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Services;

/**
 * #5714 — Résultat du parsing CSV.
 *
 * @phpstan-type CsvError array{line: int, message: string}
 * @phpstan-type CsvRow array<string, string>
 */
final readonly class CsvParseResult
{
    /**
     * @param  list<string>  $columns
     * @param  list<CsvRow>  $rows
     * @param  list<CsvError>  $errors
     */
    public function __construct(
        public array $columns,
        public array $rows,
        public array $errors,
        public int $validCount,
        public int $errorCount,
    ) {
    }
}
