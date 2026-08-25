<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Infrastructure\Services\DocumentPdfRenderer;
use Illuminate\Support\Facades\App;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5227 — i18n ×4 du module Comptabilité.
 *
 * Parité des catalogues (accounting.php / errors.php ×4), messages d'erreur
 * API localisés par locale (workflow documents, paiements, validation) et
 * labels PDF localisés (golden via buildViewData, sans dépendre du binaire).
 */
class AccountingI18nTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        return $company;
    }

    private function bindCompany(Company $company): void
    {
        app()->instance('current_company', $company);
    }

    private function manager(Company $company, string $managerRole = 'comptable', ?string $locale = null): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
            'preferred_language' => $locale,
        ]);

        return $manager;
    }

    private function contact(Company $company): AccountingContact
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Client Test',
            'email' => 'client@exemple.dz',
        ]);

        return $contact;
    }

    /**
     * Document SANS ligne (pour déclencher l'erreur métier à l'envoi).
     */
    private function documentWithoutLines(Company $company, string $status = 'draft'): AccountingDocument
    {
        $contact = $this->contact($company);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::create([
            'company_id' => $company->id,
            'type' => 'invoice',
            'number' => 'FAC-2026-5227',
            'status' => $status,
            'contact_id' => $contact->id,
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-31',
            'currency' => 'DZD',
            'subtotal_ht' => 0.0,
            'tax_amount' => 0.0,
            'total_ttc' => 0.0,
            'tva_rate' => 19.0,
            'paid_amount' => 0.0,
        ]);

        return $document;
    }

    private function documentSent(Company $company, float $ttc = 1190.0): AccountingDocument
    {
        $contact = $this->contact($company);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::create([
            'company_id' => $company->id,
            'type' => 'invoice',
            'number' => 'FAC-2026-5228',
            'status' => 'sent',
            'contact_id' => $contact->id,
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-31',
            'currency' => 'DZD',
            'subtotal_ht' => round($ttc / 1.19, 2),
            'tax_amount' => round($ttc - $ttc / 1.19, 2),
            'total_ttc' => $ttc,
            'tva_rate' => 19.0,
            'paid_amount' => 0.0,
        ]);

        return $document;
    }

    // ── 1. Parité des catalogues ×4 ──────────────────────────────────────────

    public function test_accounting_catalog_has_full_key_parity_across_locales(): void
    {
        $keysByLocale = [];
        foreach (['fr', 'en', 'tr', 'ar'] as $locale) {
            $catalog = require lang_path("{$locale}/accounting.php");
            $keys = array_keys($catalog);
            sort($keys);
            $keysByLocale[$locale] = $keys;
        }

        foreach (['en', 'tr', 'ar'] as $locale) {
            $this->assertSame(
                $keysByLocale['fr'],
                $keysByLocale[$locale],
                "api/lang/{$locale}/accounting.php : parité des clés attendue avec fr.",
            );
        }
    }

    public function test_errors_catalog_has_payment_codes_in_all_locales(): void
    {
        foreach (['fr', 'en', 'tr', 'ar'] as $locale) {
            $catalog = require lang_path("{$locale}/errors.php");

            $this->assertArrayHasKey('PAYMENT_EXCEEDS_TOTAL', $catalog, "errors.php ({$locale}) : code PAYMENT_EXCEEDS_TOTAL manquant.");
            $this->assertArrayHasKey('PAYMENT_ON_UNSENT_DOCUMENT', $catalog, "errors.php ({$locale}) : code PAYMENT_ON_UNSENT_DOCUMENT manquant.");
        }
    }

    // ── 2. Messages API localisés par locale ─────────────────────────────────

    public function test_send_without_lines_returns_localized_message_fr(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $draft = $this->documentWithoutLines($company);

        Sanctum::actingAs($this->manager($company, 'comptable', 'fr'));

        $response = $this->postJson('/api/v1/accounting/documents/'.$draft->id.'/send');

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Impossible d\'envoyer un document sans ligne.');
    }

    public function test_send_without_lines_returns_localized_message_en(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $draft = $this->documentWithoutLines($company);

        Sanctum::actingAs($this->manager($company, 'comptable', 'en'));

        $response = $this->postJson('/api/v1/accounting/documents/'.$draft->id.'/send');

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Cannot send a document without lines.');
    }

    public function test_payment_validation_amount_required_is_localized(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $invoice = $this->documentSent($company);

        Sanctum::actingAs($this->manager($company, 'comptable', 'fr'));

        $response = $this->postJson('/api/v1/accounting/documents/'.$invoice->id.'/payments', [
            'method' => 'cash',
        ]);

        $response->assertStatus(422);
        $this->assertSame('Le montant est requis.', $response->json('errors.amount.0'));
    }

    public function test_payment_exceeding_total_returns_localized_error_code(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $invoice = $this->documentSent($company, 1190.0);

        Sanctum::actingAs($this->manager($company, 'comptable', 'fr'));

        $this->postJson('/api/v1/accounting/documents/'.$invoice->id.'/payments', [
            'amount' => 500.0,
            'method' => 'bank_transfer',
        ])->assertCreated();

        $response = $this->postJson('/api/v1/accounting/documents/'.$invoice->id.'/payments', [
            'amount' => 700.0,
            'method' => 'bank_transfer',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'PAYMENT_EXCEEDS_TOTAL');
        $response->assertJsonPath('localized_message', 'Le montant du paiement dépasse le solde restant du document.');
    }

    public function test_payment_on_draft_document_returns_localized_error_code(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $draft = $this->documentWithoutLines($company, 'draft');

        Sanctum::actingAs($this->manager($company, 'comptable', 'fr'));

        $response = $this->postJson('/api/v1/accounting/documents/'.$draft->id.'/payments', [
            'amount' => 100.0,
            'method' => 'cash',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'PAYMENT_ON_UNSENT_DOCUMENT');
        $response->assertJsonPath('localized_message', 'Impossible d\'enregistrer un paiement sur un document non émis.');
    }

    // ── 3. Labels PDF localisés (golden, sans binaire) ───────────────────────

    public function test_pdf_labels_localized_per_locale(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $document = $this->documentSent($company);

        $renderer = app(DocumentPdfRenderer::class);

        App::setLocale('fr');
        $fr = $renderer->buildViewData($document, 'fr');
        $this->assertSame('Facture', $fr['document_type_label']);
        $this->assertSame('Envoyé', $fr['status_label']);

        App::setLocale('en');
        $en = $renderer->buildViewData($document, 'en');
        $this->assertSame('Invoice', $en['document_type_label']);
        $this->assertSame('Sent', $en['status_label']);

        App::setLocale('ar');
        $ar = $renderer->buildViewData($document, 'ar');
        $this->assertSame('فاتورة', $ar['document_type_label']);
        $this->assertSame('أُرسلت', $ar['status_label']);

        App::setLocale('fr');
    }
}
