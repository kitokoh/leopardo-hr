<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\SocialDeclarationGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class SocialDeclarationGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-05-20 09:15:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_generates_cnas_dz_quarterly_declaration_with_totals_and_sanitized_fields(): void
    {
        $generator = new SocialDeclarationGenerator;

        $content = $generator->generateCnasDz(
            'Leo|RH',
            'NIS;123',
            'Q2',
            2026,
            new Collection([
                [
                    'employee_id' => 1,
                    'num_ss' => 'SS|001',
                    'last_name' => 'benali',
                    'first_name' => 'amina',
                    'date_naissance' => '1990-01-05',
                    'gross_salary' => 100000.129,
                    'months_worked' => 3,
                ],
                [
                    'employee_id' => 2,
                    'num_ss' => 'SS002',
                    'last_name' => 'karim',
                    'first_name' => 'ali',
                    'date_naissance' => '1988-09-12',
                    'gross_salary' => 50000,
                    'months_worked' => 2,
                ],
            ]),
        );

        self::assertStringStartsWith("ENTETE|LeoRH|NIS123|Q2|2026|20/05/2026|2\r\n", $content);
        self::assertStringContainsString("LIGNE|000001|SS001|BENALI|AMINA|1990-01-05|100000.13|3|9000.01|26000.03\r\n", $content);
        self::assertStringContainsString("LIGNE|000002|SS002|KARIM|ALI|1988-09-12|50000.00|2|4500.00|13000.00\r\n", $content);
        self::assertStringEndsWith("TOTAL|2|150000.13|13500.01|39000.03\r\n", $content);
    }

    public function test_generates_cnss_ma_declaration_with_default_days_and_csv_safe_delimiter(): void
    {
        $generator = new SocialDeclarationGenerator;

        $content = $generator->generateCnssMa(
            'Atlas;RH',
            'AFF|456',
            'Q1',
            2026,
            new Collection([
                [
                    'employee_id' => 7,
                    'num_cnss' => 'CN;001',
                    'last_name' => 'el fassi',
                    'first_name' => 'salma',
                    'cin' => 'AB;123',
                    'gross_salary' => 12345.678,
                ],
            ]),
        );

        self::assertStringStartsWith("ENTETE;AtlasRH;AFF456;Q1;2026;20/05/2026;1\r\n", $content);
        self::assertStringContainsString("SALARIE;000001;CN001;EL FASSI;SALMA;AB123;12345.68;78\r\n", $content);
        self::assertStringEndsWith("TOTAL;1;12345.68;78\r\n", $content);
    }

    public function test_generates_dsn_fr_with_contract_mapping_and_quote_sanitizing(): void
    {
        $generator = new SocialDeclarationGenerator;

        $content = $generator->generateDsnFr(
            "Leopardo 'France'",
            '12345678900011',
            '05',
            2026,
            new Collection([
                [
                    'employee_id' => 3,
                    'nir' => "1'90",
                    'last_name' => "d'upont",
                    'first_name' => "leo\npaul",
                    'date_naissance' => '1990-03-02',
                    'gross_salary' => 3200.5,
                    'net_salary' => 2450.25,
                    'net_imposable' => 2600.75,
                    'hours_worked' => 151.67,
                    'contract_type' => 'CDD',
                    'start_date' => '2026-01-01',
                ],
                [
                    'employee_id' => 4,
                    'nir' => '288',
                    'last_name' => 'martin',
                    'first_name' => 'lea',
                    'date_naissance' => '1988-08-08',
                    'gross_salary' => 1000,
                    'net_salary' => 800,
                    'contract_type' => 'unknown',
                    'start_date' => '2026-02-01',
                ],
            ]),
            '99887766554433',
        );

        self::assertStringContainsString("S10.G00.00.001,'99887766554433'\r\n", $content);
        self::assertStringContainsString("S20.G00.05.002,'Leopardo France'\r\n", $content);
        self::assertStringContainsString("S21.G00.30.001,'190'\r\n", $content);
        self::assertStringContainsString("S21.G00.30.002,'DUPONT'\r\n", $content);
        self::assertStringContainsString("S21.G00.40.007,'02'\r\n", $content);
        self::assertStringContainsString("S21.G00.40.007,'01'\r\n", $content);
        self::assertStringContainsString("S44.G00.00.001,'4200.50'\r\n", $content);
        self::assertStringContainsString("S44.G00.00.002,'2'\r\n", $content);
        self::assertStringContainsString("S44.G00.00.003,'3250.25'\r\n", $content);
    }
}
