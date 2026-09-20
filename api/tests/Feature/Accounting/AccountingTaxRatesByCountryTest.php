<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use App\Modules\Accounting\Infrastructure\Services\AccountingSettingsDefaults;
use App\Modules\Accounting\Infrastructure\Services\VatDeclarationService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7926 — fiscalité indirecte complète Turquie (KDV 20/10/1, source
 * GİB) et Canada (TPS/TVH/TVP/TVQ par province, source ARC) : défauts de
 * réglage multi-taux + ventilation de la déclaration TVA par taux.
 */
class AccountingTaxRatesByCountryTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @param  array<int, array{label: string, label_key: string, rate: int|float}>  $rates
     * @return array<int, float>
     */
    private function ratesOf(array $rates): array
    {
        return array_map(static fn (array $row): float => (float) $row['rate'], $rates);
    }

    // ── Turquie : KDV 20/10/1 ────────────────────────────────────────────

    public function test_turkey_defaults_expose_kdv_20_10_1(): void
    {
        $rates = AccountingSettingsDefaults::for('TR')['tva_rates'];

        $this->assertSame([20.0, 10.0, 1.0], $this->ratesOf($rates));
        $this->assertSame(['standard', 'reduced', 'super_reduced'], array_column($rates, 'label_key'));
    }

    // ── Canada : structure par province ──────────────────────────────────

    public function test_canada_without_province_defaults_to_federal_gst_only(): void
    {
        $rates = AccountingSettingsDefaults::for('CA')['tva_rates'];

        $this->assertSame([5.0], $this->ratesOf($rates));
        $this->assertSame(['gst'], array_column($rates, 'label_key'));
    }

    public function test_canada_hst_provinces_golden(): void
    {
        // NS 14 % depuis le 2025-04-01 (ARC, Notice 342).
        $expected = ['ON' => 13.0, 'NB' => 15.0, 'NS' => 14.0, 'NL' => 15.0, 'PE' => 15.0];

        foreach ($expected as $province => $rate) {
            $rates = AccountingSettingsDefaults::for('CA', $province)['tva_rates'];

            $this->assertSame([$rate], $this->ratesOf($rates), $province);
            $this->assertSame(['hst'], array_column($rates, 'label_key'), $province);
        }
    }

    public function test_quebec_combines_gst_and_qst(): void
    {
        $rates = AccountingSettingsDefaults::for('CA', 'QC')['tva_rates'];

        $this->assertSame([5.0, 9.975], $this->ratesOf($rates));
        $this->assertSame(['gst', 'qst'], array_column($rates, 'label_key'));
    }

    public function test_pst_provinces_combine_gst_and_pst_golden(): void
    {
        $expected = ['BC' => 7.0, 'SK' => 6.0, 'MB' => 7.0];

        foreach ($expected as $province => $pst) {
            $rates = AccountingSettingsDefaults::for('CA', $province)['tva_rates'];

            $this->assertSame([5.0, $pst], $this->ratesOf($rates), $province);
            $this->assertSame(['gst', 'pst'], array_column($rates, 'label_key'), $province);
        }
    }

    public function test_gst_only_provinces_and_unknown_province_stay_federal(): void
    {
        foreach (['AB', 'YT', 'NT', 'NU', 'ZZ', null] as $province) {
            $rates = AccountingSettingsDefaults::tvaRates('CA', $province);

            $this->assertSame([5.0], $this->ratesOf($rates), (string) $province);
        }
    }

    public function test_dz_and_fr_defaults_unchanged(): void
    {
        $this->assertSame([19.0, 9.0], $this->ratesOf(AccountingSettingsDefaults::for('DZ')['tva_rates']));
        $this->assertSame([20.0], $this->ratesOf(AccountingSettingsDefaults::for('FR')['tva_rates']));
    }

    // ── Déclaration TVA : ventilation par taux KDV ───────────────────────

    public function test_vat_declaration_ventilates_turkish_kdv_rates(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'TR', 'currency' => 'TRY']);
        app()->instance('current_company', $company);

        AccountingSettings::query()->create(array_merge(
            ['company_id' => $company->id],
            AccountingSettingsDefaults::for('TR'),
        ));

        /** @var AccountingContact $contact */
        $contact = AccountingContact::query()->create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Müşteri',
        ]);

        $mkInvoice = function (float $ht, float $rate, string $number) use ($company, $contact): void {
            AccountingDocument::query()->create([
                'company_id' => $company->id,
                'type' => DocumentType::Invoice->value,
                'number' => $number,
                'status' => DocumentStatus::Sent->value,
                'contact_id' => $contact->id,
                'issue_date' => '2026-08-10',
                'currency' => 'TRY',
                'subtotal_ht' => $ht,
                'tax_amount' => round($ht * $rate / 100, 2),
                'total_ttc' => round($ht * (1 + $rate / 100), 2),
                'tva_rate' => $rate,
            ]);
        };

        $mkInvoice(1000.0, 20.0, 'FAC-TR-0001');
        $mkInvoice(2000.0, 10.0, 'FAC-TR-0002');
        $mkInvoice(500.0, 1.0, 'FAC-TR-0003');

        $declaration = app(VatDeclarationService::class)->declaration($company, '2026-08');

        $byRate = $declaration['collected']['by_rate'];
        $this->assertCount(3, $byRate);
        $this->assertSame([1.0, 10.0, 20.0], array_column($byRate, 'rate'));
        $this->assertSame(5.0, $byRate[0]['tax']);   // 500 × 1 %
        $this->assertSame(200.0, $byRate[1]['tax']); // 2000 × 10 %
        $this->assertSame(200.0, $byRate[2]['tax']); // 1000 × 20 %
        $this->assertSame(405.0, $declaration['collected']['tax']);
        $this->assertSame('TRY', $declaration['currency']);

        app()->forgetInstance('current_company');
    }
}
