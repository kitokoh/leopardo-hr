<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Infrastructure\Services\AccountingRetentionService;
use Illuminate\Support\Facades\Artisan;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7929 — rétention comptable paramétrée par pays : OHADA 120 mois
 * (AUDCIF art. 24), FR 120 (C. com. L123-22), TR 120 (TTK 82), CA 72
 * (LIR 230(4)), défaut conservateur 120 ; override opérateur conservé
 * (--older-than). La purge par pays
 * supprime un document CA de 7 ans mais conserve son jumeau FR.
 */
class AccountingRetentionByCountryTest extends TestCase
{
    use RefreshTenantDatabase;

    private function finalizedDocument(Company $company, string $issueDate): AccountingDocument
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Client rétention',
        ]);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::create([
            'company_id' => $company->id,
            'type' => DocumentType::Invoice->value,
            'number' => 'FAC-'.strtoupper(uniqid()),
            'status' => DocumentStatus::Paid->value,
            'contact_id' => $contact->id,
            'issue_date' => $issueDate,
            'currency' => 'EUR',
            'subtotal_ht' => 100.0,
            'tax_amount' => 20.0,
            'total_ttc' => 120.0,
        ]);

        return $document;
    }

    // ── Résolution par pays (goldens sourcés) ────────────────────────────

    public function test_retention_months_golden_by_jurisdiction(): void
    {
        $service = app(AccountingRetentionService::class);

        // OHADA — AUDCIF art. 24 (10 ans).
        foreach (['SN', 'CI', 'CM', 'GA', 'BF', 'BJ', 'TD', 'GQ'] as $country) {
            $this->assertSame(120, $service->retentionMonthsFor($country), $country);
        }

        $this->assertSame(120, $service->retentionMonthsFor('FR')); // L123-22
        $this->assertSame(120, $service->retentionMonthsFor('TR')); // TTK 82
        $this->assertSame(72, $service->retentionMonthsFor('CA'));  // LIR 230(4)

        // Défaut conservateur (pays inconnu / hors table) : 120 mois.
        $this->assertSame(120, $service->retentionMonthsFor('DZ'));
        $this->assertSame(120, $service->retentionMonthsFor('XX'));
        $this->assertSame(120, $service->retentionMonthsFor(null));
    }

    public function test_configured_default_applies_only_to_countries_outside_the_table(): void
    {
        config(['accounting.retention_months' => 36]);

        $service = app(AccountingRetentionService::class);

        // Les durées légales par pays ne sont PAS raccourcies par le défaut.
        $this->assertSame(72, $service->retentionMonthsFor('CA'));
        $this->assertSame(120, $service->retentionMonthsFor('FR'));
        // Pays hors table : le défaut configuré s'applique.
        $this->assertSame(36, $service->retentionMonthsFor('XX'));

        config(['accounting.retention_months' => 120]);
    }

    // ── Purge par pays ───────────────────────────────────────────────────

    public function test_purge_by_country_removes_ca_document_but_keeps_fr_twin(): void
    {
        /** @var Company $ca */
        $ca = Company::factory()->create(['country' => 'CA', 'currency' => 'CAD']);
        /** @var Company $fr */
        $fr = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);

        $sevenYearsAgo = now()->subYears(7)->toDateString();

        app()->instance('current_company', $ca);
        $caDoc = $this->finalizedDocument($ca, $sevenYearsAgo);
        app()->instance('current_company', $fr);
        $frDoc = $this->finalizedDocument($fr, $sevenYearsAgo);
        app()->forgetInstance('current_company');

        $exit = Artisan::call('accounting:purge-expired');

        $this->assertSame(0, $exit);
        // CA : 7 ans > 72 mois → purgé ; FR : 7 ans < 120 mois → conservé.
        $this->assertDatabaseMissing('accounting_documents', ['id' => $caDoc->id]);
        $this->assertDatabaseHas('accounting_documents', ['id' => $frDoc->id]);
    }

    public function test_purge_by_country_dry_run_deletes_nothing(): void
    {
        /** @var Company $ca */
        $ca = Company::factory()->create(['country' => 'CA', 'currency' => 'CAD']);

        app()->instance('current_company', $ca);
        $caDoc = $this->finalizedDocument($ca, now()->subYears(7)->toDateString());
        app()->forgetInstance('current_company');

        $exit = Artisan::call('accounting:purge-expired', ['--dry-run' => true]);

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('accounting_documents', ['id' => $caDoc->id]);
    }

    public function test_older_than_option_still_forces_a_uniform_retention(): void
    {
        /** @var Company $fr */
        $fr = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);

        app()->instance('current_company', $fr);
        $frDoc = $this->finalizedDocument($fr, now()->subYears(7)->toDateString());
        app()->forgetInstance('current_company');

        // Override opérateur : 12 mois → le document FR de 7 ans est purgé.
        $exit = Artisan::call('accounting:purge-expired', ['--older-than' => 12]);

        $this->assertSame(0, $exit);
        $this->assertDatabaseMissing('accounting_documents', ['id' => $frDoc->id]);
    }
}
