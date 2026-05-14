<?php

namespace Tests\Unit;

use App\Models\PayrollRun;
use App\Models\PaySlip;
use App\Services\BankExportGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class BankExportGeneratorTest extends TestCase
{
    public function test_virement_ma_uses_csv_extension_and_mime_type(): void
    {
        $generator = new BankExportGenerator;

        self::assertSame('csv', $generator->fileExtension('virement_ma'));
        self::assertSame('text/csv', $generator->mimeType('virement_ma'));
    }

    public function test_csv_export_uses_real_employee_bank_account_column(): void
    {
        $generator = new BankExportGenerator;
        $method = new ReflectionMethod($generator, 'generateCsvGeneric');
        $method->setAccessible(true);

        $run = new PayrollRun;
        $run->setRawAttributes([
            'period_start' => Carbon::parse('2026-05-01'),
            'country_code' => 'MA',
        ]);

        $employee = (object) [
            'first_name' => 'Amina',
            'last_name' => 'Benali',
            'iban' => 'MA64011519000001205000534921',
            'bank_account' => '001205000534921',
        ];

        $slip = new PaySlip;
        $slip->setRawAttributes([
            'employee_id' => 42,
            'net_salary' => 8750.25,
        ]);
        $slip->setRelation('employee', $employee);

        $csv = $method->invoke($generator, $run, new Collection([$slip]));

        self::assertStringContainsString('employee_id,first_name,last_name,iban,bank_account,net_salary,currency,period', $csv);
        self::assertStringContainsString('42,Amina,Benali,MA64011519000001205000534921,001205000534921,8750.25,MA,2026-05', $csv);
        self::assertStringNotContainsString('bank_name', $csv);
    }
}
