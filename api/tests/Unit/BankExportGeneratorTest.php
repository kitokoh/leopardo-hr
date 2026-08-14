<?php

namespace Tests\Unit;

use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\BankExportGenerator;
use App\Support\CountryDefaults;
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

        $csv = $method->invoke($generator, $run, new Collection([$slip]), 'MAD');

        self::assertStringContainsString('employee_id,first_name,last_name,iban,bank_account,net_salary,currency,period', $csv);
        self::assertStringContainsString('42,Amina,Benali,MA64011519000001205000534921,001205000534921,8750.25,MAD,2026-05', $csv);
        self::assertStringNotContainsString('bank_name', $csv);
    }

    public function test_sepa_export_escapes_employee_fields_and_sums_validated_slips(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-20 10:00:00'));

        $generator = new BankExportGenerator;
        $method = new ReflectionMethod($generator, 'generateSepaXml');
        $method->setAccessible(true);

        $run = new PayrollRun;
        $run->setRawAttributes([
            'id' => 77,
            'company_id' => 1,
            'period_start' => Carbon::parse('2026-05-01'),
        ]);
        // Issue #2223 : identité bancaire de l'émetteur depuis le profil
        // entreprise — plus jamais de placeholder.
        $company = new \App\Core\Tenant\Domain\Models\Company;
        $company->setRawAttributes(['id' => 1, 'metadata' => [
            'iban' => 'FR7630006000011234567890189',
            'bic' => 'AGRIFRPP',
        ]]);
        $run->setRelation('company', $company);

        $slip = new PaySlip;
        $slip->setRawAttributes([
            'employee_id' => 42,
            'net_salary' => 8750.25,
        ]);
        $slip->setRelation('employee', (object) [
            'first_name' => 'Amina & Co',
            'last_name' => 'Benali <Finance>',
            'iban' => 'FR7630006000011234567890189',
        ]);

        $xml = $method->invoke($generator, $run, new Collection([$slip]), 'MAD');

        self::assertStringContainsString('<MsgId>LEO-20260520100000-77</MsgId>', $xml);
        self::assertStringContainsString('<NbOfTxs>1</NbOfTxs>', $xml);
        self::assertStringContainsString('<CtrlSum>8750.25</CtrlSum>', $xml);
        self::assertStringContainsString('<Cdtr><Nm>Amina &amp; Co Benali &lt;Finance&gt;</Nm></Cdtr>', $xml);
        self::assertStringContainsString('<RmtInf><Ustrd>Salaire 05/2026</Ustrd></RmtInf>', $xml);
        // Issue #2223 : l'IBAN/BIC émetteur vient du profil entreprise.
        self::assertStringContainsString('<IBAN>FR7630006000011234567890189</IBAN></Id></DbtrAcct>', $xml);
        self::assertStringContainsString('<BIC>AGRIFRPP</BIC>', $xml);
        self::assertStringNotContainsString('PLACEHOLDER', $xml);
        self::assertStringNotContainsString('UNKNOWN', $xml);

        // PA2-I18N-003: the SEPA transfer currency must follow the payroll
        // run's own country instead of a hardcoded EUR literal.
        self::assertStringContainsString('<InstdAmt Ccy="MAD">8750.25</InstdAmt>', $xml);

        Carbon::setTestNow();
    }

    public function test_sepa_export_defaults_to_eur_when_no_currency_is_given(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-20 10:00:00'));

        $generator = new BankExportGenerator;
        $method = new ReflectionMethod($generator, 'generateSepaXml');
        $method->setAccessible(true);

        $run = new PayrollRun;
        $run->setRawAttributes([
            'id' => 78,
            'company_id' => 1,
            'period_start' => Carbon::parse('2026-05-01'),
        ]);
        $company = new \App\Core\Tenant\Domain\Models\Company;
        $company->setRawAttributes(['id' => 1, 'metadata' => [
            'iban' => 'FR7630006000011234567890189',
            'bic' => 'AGRIFRPP',
        ]]);
        $run->setRelation('company', $company);

        $slip = new PaySlip;
        $slip->setRawAttributes([
            'employee_id' => 43,
            'net_salary' => 1200,
        ]);
        $slip->setRelation('employee', (object) [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'iban' => 'FR7630006000011234567890189',
        ]);

        $xml = $method->invoke($generator, $run, new Collection([$slip]));

        self::assertStringContainsString('<InstdAmt Ccy="EUR">1200.00</InstdAmt>', $xml);
        self::assertStringNotContainsString('PLACEHOLDER', $xml);
        self::assertStringNotContainsString('UNKNOWN', $xml);

        Carbon::setTestNow();
    }

    public function test_sepa_export_skips_employees_without_iban_and_requires_company_bank_identity(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-20 10:00:00'));

        $generator = new BankExportGenerator;
        $method = new ReflectionMethod($generator, 'generateSepaXml');
        $method->setAccessible(true);

        $run = new PayrollRun;
        $run->setRawAttributes([
            'id' => 79,
            'company_id' => 1,
            'period_start' => Carbon::parse('2026-05-01'),
        ]);
        $company = new \App\Core\Tenant\Domain\Models\Company;
        $company->setRawAttributes(['id' => 1, 'metadata' => [
            'iban' => 'FR7630006000011234567890189',
            'bic' => 'AGRIFRPP',
        ]]);
        $run->setRelation('company', $company);

        // Un employé avec IBAN, un sans → le second est exclu (jamais UNKNOWN).
        $withIban = new PaySlip;
        $withIban->setRawAttributes(['employee_id' => 1, 'net_salary' => 1000]);
        $withIban->setRelation('employee', (object) [
            'first_name' => 'A', 'last_name' => 'B', 'iban' => 'FR7630006000011234567890189',
        ]);
        $withoutIban = new PaySlip;
        $withoutIban->setRawAttributes(['employee_id' => 2, 'net_salary' => 2000]);
        $withoutIban->setRelation('employee', (object) [
            'first_name' => 'C', 'last_name' => 'D', 'iban' => null,
        ]);

        $xml = $method->invoke($generator, $run, new Collection([$withIban, $withoutIban]), 'EUR');

        self::assertStringContainsString('<NbOfTxs>1</NbOfTxs>', $xml);
        self::assertStringContainsString('<CtrlSum>1000.00</CtrlSum>', $xml);
        self::assertStringNotContainsString('UNKNOWN', $xml);
        self::assertStringNotContainsString('SAL-79-2', $xml); // employé sans IBAN exclu

        // Sans identité bancaire entreprise → exception (export bloqué).
        $runNoCompany = new PayrollRun;
        $runNoCompany->setRawAttributes([
            'id' => 80,
            'company_id' => 999,
            'period_start' => Carbon::parse('2026-05-01'),
        ]);
        $this->expectException(\RuntimeException::class);
        $method->invoke($generator, $runNoCompany, new Collection([$withIban]), 'EUR');

        Carbon::setTestNow();
    }

    public function test_algerian_bank_exports_include_headers_details_and_footers(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-20 10:00:00'));

        $generator = new BankExportGenerator;
        $cpaMethod = new ReflectionMethod($generator, 'generateCpaBna');
        $cpaMethod->setAccessible(true);
        $ccpMethod = new ReflectionMethod($generator, 'generateCcpAlgerie');
        $ccpMethod->setAccessible(true);

        $run = new PayrollRun;
        $run->setRawAttributes([
            'id' => 88,
            'period_start' => Carbon::parse('2026-04-01'),
        ]);

        $slip = new PaySlip;
        $slip->setRawAttributes([
            'employee_id' => 9,
            'net_salary' => 45000.75,
        ]);
        $slip->setRelation('employee', (object) [
            'id' => 9,
            'first_name' => 'Nour',
            'last_name' => 'Haddad',
            'bank_account' => '00123456789',
        ]);

        $slips = new Collection([$slip]);
        $cpa = $cpaMethod->invoke($generator, $run, $slips, 'CPA');
        $ccp = $ccpMethod->invoke($generator, $run, $slips);

        self::assertStringContainsString("HEADER|CPA|CPA-20260520100000-88|20052026|1|45000.75|DZD|LEOPARDO RH\r\n", $cpa);
        self::assertStringContainsString("DETAIL|000001|00123456789|HADDAD NOUR|45000.75|DZD|SAL-88-9|Salaire 04/2026\r\n", $cpa);
        self::assertStringEndsWith("FOOTER|1|45000.75\r\n", $cpa);

        self::assertStringContainsString('HLEOPARDO', $ccp);
        self::assertStringContainsString('D00000100123456789', $ccp);
        self::assertStringEndsWith("T000001000000004500075\r\n", $ccp);

        Carbon::setTestNow();
    }

    public function test_unknown_bank_export_metadata_falls_back(): void
    {
        $generator = new BankExportGenerator;

        self::assertSame('dat', $generator->fileExtension('unknown'));
        self::assertSame('application/octet-stream', $generator->mimeType('unknown'));
    }

    /**
     * PA2-I18N-003 — `CountryDefaults::for()` resolves the currency actually
     * used by `generate()` for the multi-country export formats (csv_generic,
     * virement_ma, sepa_xml); a payroll run in Cote d'Ivoire must resolve to
     * XOF, never to the raw 'CI' country code or a hardcoded EUR/DZD.
     */
    public function test_country_defaults_resolves_xof_for_cedeao_country_code(): void
    {
        self::assertSame('XOF', CountryDefaults::for('CI')['currency']);
        self::assertSame('XAF', CountryDefaults::for('CM')['currency']);
        self::assertSame('MAD', CountryDefaults::for('MA')['currency']);
        // Unknown/empty country code technical fallback only, per ticket.
        self::assertSame('DZD', CountryDefaults::for(null)['currency']);
    }
}
