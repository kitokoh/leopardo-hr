<?php

declare(strict_types=1);

namespace Tests\Unit\Crm;

use App\Modules\CRM\Domain\Enums\CrmImportEntityType;
use App\Modules\CRM\Domain\Exceptions\CrmImportException;
use App\Modules\CRM\Infrastructure\Services\CsvParser;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * #5714 — Bornes et sécurité du moteur de parsing CSV CRM.
 *
 * Tests écrits avant l'implémentation (DoD) : encodage, extension, taille,
 * colonnes/lignes bornées, whitelist d'en-tête, formules CSV neutralisées,
 * erreurs par ligne.
 */
class CsvParserTest extends TestCase
{
    private CsvParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CsvParser;
    }

    public function test_parses_valid_accounts_csv(): void
    {
        $csv = "name,email,notes\nAcme,acme@example.com,BTP\nGlobex,globex@example.com,Finance\n";

        $result = $this->parser->parse($this->writeTemp($csv), CrmImportEntityType::Accounts);

        self::assertSame(['name', 'email', 'notes'], $result->columns);
        self::assertSame(2, $result->validCount);
        self::assertSame(0, $result->errorCount);
        self::assertSame('Acme', $result->rows[0]['name']);
        self::assertSame('acme@example.com', $result->rows[0]['email']);
    }

    public function test_rejects_unknown_column(): void
    {
        $csv = "name,secret_column\nAcme,leak\n";

        $this->expectException(CrmImportException::class);
        $this->expectExceptionMessage('colonnes inconnues');

        $this->parser->parse($this->writeTemp($csv), CrmImportEntityType::Accounts);
    }

    public function test_rejects_duplicate_header_columns(): void
    {
        $csv = "name,name\nAcme,Acme2\n";

        $this->expectException(CrmImportException::class);
        $this->expectExceptionMessage('dupliquées');

        $this->parser->parse($this->writeTemp($csv), CrmImportEntityType::Accounts);
    }

    public function test_reports_per_line_errors_without_stopping(): void
    {
        $csv = "name,email\nAcme,acme@x.fr\n,finance@x.fr\nGlobex,\n";

        $result = $this->parser->parse($this->writeTemp($csv), CrmImportEntityType::Accounts);

        self::assertSame(1, $result->validCount);
        self::assertSame(2, $result->errorCount);
        self::assertCount(2, $result->errors);
        self::assertSame(3, $result->errors[0]['line']);
        self::assertSame(4, $result->errors[1]['line']);
    }

    public function test_reports_wrong_column_count_per_line(): void
    {
        $csv = "name,email\nAcme,acme@x.fr,EXTRA\n";

        $result = $this->parser->parse($this->writeTemp($csv), CrmImportEntityType::Accounts);

        self::assertSame(0, $result->validCount);
        self::assertSame(1, $result->errorCount);
        self::assertStringContainsString('nombre de colonnes', $result->errors[0]['message']);
    }

    public function test_neutralizes_csv_formula_cells(): void
    {
        $csv = "name,notes\nAcme,=HYPERLINK(\"http://evil\")\nGlobex,+SUM(A1:A9)\n";

        $result = $this->parser->parse($this->writeTemp($csv), CrmImportEntityType::Accounts);

        self::assertStringStartsWith("'=", $result->rows[0]['notes']);
        self::assertStringStartsWith("'+", $result->rows[1]['notes']);
    }

    public function test_keeps_numeric_cells_untouched(): void
    {
        $csv = "name,email\nAcme,-1234\n";

        $result = $this->parser->parse($this->writeTemp($csv), CrmImportEntityType::Accounts);

        // CsvCellSanitizer : les valeurs numériques ne sont jamais préfixées.
        self::assertSame('-1234', $result->rows[0]['email']);
    }

    public function test_accepts_windows_1252_encoding(): void
    {
        $content = "name,email\nRené,rene@x.fr\n";
        $windows1252 = mb_convert_encoding($content, 'Windows-1252', 'UTF-8');

        $result = $this->parser->parse($this->writeTemp($windows1252), CrmImportEntityType::Accounts);

        self::assertSame(1, $result->validCount);
        self::assertSame('René', $result->rows[0]['name']);
    }

    public function test_accepts_utf8_bom(): void
    {
        $csv = "\xEF\xBB\xBFname,email\nAcme,acme@x.fr\n";

        $result = $this->parser->parse($this->writeTemp($csv), CrmImportEntityType::Accounts);

        self::assertSame(1, $result->validCount);
    }

    public function test_skips_empty_lines(): void
    {
        $csv = "name,email\nAcme,acme@x.fr\n\n\nGlobex,globex@x.fr\n";

        $result = $this->parser->parse($this->writeTemp($csv), CrmImportEntityType::Accounts);

        self::assertSame(2, $result->validCount);
    }

    public function test_handles_quoted_multiline_field(): void
    {
        $csv = "name,notes\nAcme,\"note sur\ndeux lignes\"\n";

        $result = $this->parser->parse($this->writeTemp($csv), CrmImportEntityType::Accounts);

        self::assertSame(1, $result->validCount);
        self::assertSame("note sur\ndeux lignes", $result->rows[0]['notes']);
    }

    public function test_rejects_too_long_cell(): void
    {
        $long = str_repeat('a', CsvParser::MAX_CELL_LENGTH + 1);
        $csv = "name,notes\nAcme,{$long}\n";

        $result = $this->parser->parse($this->writeTemp($csv), CrmImportEntityType::Accounts);

        self::assertSame(0, $result->validCount);
        self::assertSame(1, $result->errorCount);
        self::assertStringContainsString('trop longue', $result->errors[0]['message']);
    }

    public function test_rejects_too_many_columns(): void
    {
        $header = implode(',', array_map(fn (int $i): string => "col{$i}", range(1, CsvParser::MAX_COLUMNS + 1)));
        $csv = $header."\n";

        $this->expectException(CrmImportException::class);
        $this->expectExceptionMessage('trop de colonnes');

        $this->parser->parse($this->writeTemp($csv), CrmImportEntityType::Accounts);
    }

    public function test_rejects_too_many_rows(): void
    {
        $rows = ["name,industry"];
        for ($i = 0; $i <= CsvParser::MAX_ROWS; $i++) {
            $rows[] = "Company{$i},BTP";
        }

        $this->expectException(CrmImportException::class);
        $this->expectExceptionMessage('trop de lignes');

        $this->parser->parse($this->writeTemp(implode("\n", $rows)), CrmImportEntityType::Accounts);
    }

    public function test_validate_upload_rejects_bad_extension(): void
    {
        $file = UploadedFile::fake()->createWithContent('data.xlsx', 'name,industry');

        $this->expectException(CrmImportException::class);
        $this->expectExceptionMessage('extension');

        $this->parser->validateUpload($file);
    }

    public function test_validate_upload_rejects_oversized_file(): void
    {
        // 3 Mo > 2 Mo
        $file = UploadedFile::fake()->create('data.csv', 3072);

        $this->expectException(CrmImportException::class);
        $this->expectExceptionMessage('trop volumineux');

        $this->parser->validateUpload($file);
    }

    public function test_validate_upload_accepts_csv(): void
    {
        $file = UploadedFile::fake()->createWithContent('data.csv', 'name,industry');

        $this->parser->validateUpload($file);
        $this->addToAssertionCount(1);
    }

    private function writeTemp(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'crm_csv_');
        self::assertNotFalse($path);
        file_put_contents($path, $content);

        return $path;
    }
}
