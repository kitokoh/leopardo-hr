<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Payroll\Infrastructure\Services\CnasDeclarationGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Audit #6556 — le toCsv des déclarations sociales doit neutraliser les
 * formules CSV (= + - @ TAB CR) sans toucher aux montants numériques.
 */
class CnasDeclarationGeneratorFormulaTest extends TestCase
{
    public function test_to_csv_neutralizes_formula_prefixes_but_keeps_amounts(): void
    {
        $generator = new CnasDeclarationGenerator;
        $method = new ReflectionMethod($generator, 'toCsv');
        $method->setAccessible(true);

        $csv = $method->invoke($generator, [
            ['=HYPERLINK("https://evil")', '1234.50'],
            ['-500.00', 'Benali'],
            ['@sum(A1)', '50000.00'],
        ]);

        self::assertStringContainsString("'=HYPERLINK", $csv);
        self::assertStringContainsString('"1234.50"', $csv);
        // montant négatif numérique : NON préfixé (parse Excel numérique)
        self::assertStringContainsString('"-500.00"', $csv);
        self::assertStringContainsString('"Benali"', $csv);
        self::assertStringContainsString("'@sum", $csv);
        self::assertStringContainsString('"50000.00"', $csv);
    }
}
