<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Infrastructure\Exports\FecExporter;
use App\Modules\Accounting\Infrastructure\Services\JournalPostingService;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7927 — export FEC France conforme : devise résolue depuis le pays
 * du tenant (EUR pour FR, plus de DZD codé en dur), réglage SIREN validé
 * (9 chiffres) et nommage officiel DGFiP `SIRENFECAAAAMMJJ` (art. A. 47 A-1
 * LPF — clôture d'exercice civile au 31/12). Hors FR : inchangé.
 */
class FecExportFranceTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function frenchCompany(array $metadata = []): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'FR',
            'currency' => 'EUR',
            'metadata' => $metadata,
        ]);

        return $company;
    }

    private function postAugustInvoice(Company $company): void
    {
        app()->instance('current_company', $company);

        /** @var AccountingContact $contact */
        $contact = AccountingContact::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Client FR',
        ]);

        /** @var AccountingDocument $invoice */
        $invoice = AccountingDocument::create([
            'company_id' => $company->id,
            'type' => 'invoice',
            'number' => 'FAC-2026-0001',
            'status' => 'sent',
            'contact_id' => $contact->id,
            'issue_date' => '2026-08-05',
            'currency' => 'EUR',
            'subtotal_ht' => 1000.0,
            'tax_amount' => 200.0,
            'total_ttc' => 1200.0,
            'tva_rate' => 20.0,
        ]);

        app(JournalPostingService::class)->postDocument($invoice);
        app()->forgetInstance('current_company');
    }

    private function actingAsManager(Company $company): void
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);
    }

    // ── Nommage officiel + devise FR ─────────────────────────────────────

    public function test_french_export_uses_official_dgfip_filename_and_eur_currency(): void
    {
        $company = $this->frenchCompany(['siren' => '552100554']);
        $this->postAugustInvoice($company);
        $this->actingAsManager($company);

        $response = $this->get('/api/v1/accounting/journal/export-fec?period=2026-08');

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename="552100554FEC20261231.txt"');
        $this->assertStringContainsString(';EUR;', (string) $response->getContent());
        $this->assertStringNotContainsString(';DZD;', (string) $response->getContent());
    }

    public function test_siren_is_derived_from_a_14_digit_siret_setting(): void
    {
        $company = $this->frenchCompany(['siret' => '55210055400013']);
        $this->postAugustInvoice($company);
        $this->actingAsManager($company);

        $response = $this->get('/api/v1/accounting/journal/export-fec?period=2026-08');

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename="552100554FEC20261231.txt"');
    }

    public function test_french_export_without_siren_is_rejected_explicitly(): void
    {
        $company = $this->frenchCompany();
        $this->postAugustInvoice($company);
        $this->actingAsManager($company);

        $response = $this->get('/api/v1/accounting/journal/export-fec?period=2026-08');

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'FEC_SIREN_MISSING');
    }

    public function test_french_export_with_invalid_siren_is_rejected(): void
    {
        $company = $this->frenchCompany(['siren' => '12AB']);
        $this->postAugustInvoice($company);
        $this->actingAsManager($company);

        $response = $this->get('/api/v1/accounting/journal/export-fec?period=2026-08');

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'FEC_SIREN_INVALID');
    }

    // ── Helpers unitaires ────────────────────────────────────────────────

    public function test_siren_validation_and_official_filename_helpers(): void
    {
        $this->assertTrue(FecExporter::isValidSiren('552100554'));
        $this->assertFalse(FecExporter::isValidSiren('55210055'));
        $this->assertFalse(FecExporter::isValidSiren('5521005540'));
        $this->assertFalse(FecExporter::isValidSiren('55210055A'));

        $this->assertSame('552100554FEC20251231.txt', FecExporter::officialFrFilename('552100554', 2025));

        $this->assertNull(FecExporter::resolveSiren(null));
        $this->assertNull(FecExporter::resolveSiren(['siret' => '123']));
        $this->assertSame('552100554', FecExporter::resolveSiren(['siren' => ' 552100554 ']));
        $this->assertSame('552100554', FecExporter::resolveSiren(['siret' => '55210055400013']));
    }
}
