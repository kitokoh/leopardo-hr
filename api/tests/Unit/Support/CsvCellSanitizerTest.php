<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\CsvCellSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * #4169 — garde OWASP : une cellule commençant par un préfixe de formule
 * (= + - @ TAB CR) doit être neutralisée par une apostrophe ; les valeurs
 * numériques (montants négatifs compris) restent intactes.
 */
class CsvCellSanitizerTest extends TestCase
{
    public function test_formula_prefixes_are_neutralized(): void
    {
        foreach (['=cmd|', '+1+1', '@SUM(A1)', '-2+3', "\t=cmd|", "\r=cmd|"] as $malicious) {
            $this->assertSame("'{$malicious}", CsvCellSanitizer::neutralize($malicious));
        }
    }

    public function test_plain_text_is_untouched(): void
    {
        $this->assertSame('Jean Dupont', CsvCellSanitizer::neutralize('Jean Dupont'));
        $this->assertSame('Doe, John', CsvCellSanitizer::neutralize('Doe, John'));
    }

    public function test_numeric_values_are_untouched_including_negative_amounts(): void
    {
        $this->assertSame('-1234.5', CsvCellSanitizer::neutralize(-1234.5));
        $this->assertSame('-1234.5', CsvCellSanitizer::neutralize('-1234.5'));
        $this->assertSame('0', CsvCellSanitizer::neutralize(0));
        $this->assertSame('4327.01', CsvCellSanitizer::neutralize('4327.01'));
    }

    public function test_null_and_empty_strings_map_to_empty_cell(): void
    {
        $this->assertSame('', CsvCellSanitizer::neutralize(null));
        $this->assertSame('', CsvCellSanitizer::neutralize(''));
    }

    public function test_apostrophe_prefixed_cells_are_not_doubled(): void
    {
        // Une cellule déjà neutralisée ne doit pas être re-préfixée.
        $this->assertSame("'=cmd|", CsvCellSanitizer::neutralize("'=cmd|"));
    }
}
