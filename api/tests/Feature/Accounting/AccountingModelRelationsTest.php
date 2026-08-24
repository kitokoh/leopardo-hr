<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\ContactSource;
use App\Modules\Accounting\Domain\Enums\ContactType;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentLine;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5228 — complément de couverture du socle Accounting (Phase A).
 *
 * Les relations et enums restants non exercés par AccountingDataModelTest
 * (qui crée les enregistrements mais n'appelle pas les méthodes de relation)
 * sont couverts ici : objectif = gate module ≥ 70 % robuste.
 */
class AccountingModelRelationsTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->app->instance('current_company', $company);
    }

    private function seedDocument(): AccountingDocument
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::query()->create([
            'type' => ContactType::Customer->value,
            'name' => 'Client Relations',
            'source' => ContactSource::Manual->value,
        ]);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::query()->create([
            'type' => DocumentType::Invoice->value,
            'number' => 'FAC-2026-0001',
            'status' => DocumentStatus::Draft->value,
            'contact_id' => $contact->id,
            'issue_date' => '2026-08-22',
            'currency' => 'DZD',
            'subtotal_ht' => 1000.0,
            'tax_amount' => 190.0,
            'total_ttc' => 1190.0,
        ]);

        AccountingDocumentLine::query()->create([
            'document_id' => $document->id,
            'description' => 'Prestation de conseil',
            'quantity' => 1,
            'unit_price' => 1000.0,
            'sort_order' => 1,
        ]);

        AccountingPayment::query()->create([
            'document_id' => $document->id,
            'amount' => 1190.0,
            'method' => 'bank_transfer',
            'status' => 'pending',
        ]);

        return $document;
    }

    public function test_contact_documents_relation_returns_related_documents(): void
    {
        $document = $this->seedDocument();

        /** @var AccountingContact $contact */
        $contact = AccountingContact::query()->findOrFail($document->contact_id);

        $this->assertSame(1, $contact->documents()->count());
        $this->assertSame($document->id, $contact->documents()->firstOrFail()->id);
    }

    public function test_line_document_relation_returns_parent(): void
    {
        $document = $this->seedDocument();

        /** @var AccountingDocumentLine $line */
        $line = AccountingDocumentLine::query()->where('document_id', $document->id)->firstOrFail();

        $this->assertSame($document->id, $line->document->id);
    }

    public function test_payment_document_relation_returns_parent(): void
    {
        $document = $this->seedDocument();

        /** @var AccountingPayment $payment */
        $payment = AccountingPayment::query()->where('document_id', $document->id)->firstOrFail();

        $this->assertSame($document->id, $payment->document->id);
    }

    public function test_document_lines_and_payments_relations(): void
    {
        $document = $this->seedDocument();

        $this->assertSame(1, $document->lines()->count());
        $this->assertSame(1, $document->payments()->count());
        $this->assertSame($document->contact_id, $document->contact?->id);
    }

    public function test_contact_source_enum_values(): void
    {
        $this->assertSame(
            ['manual', 'marketing_lead'],
            ContactSource::values()
        );
        $this->assertSame(ContactSource::Manual, ContactSource::from('manual'));
        $this->assertSame(ContactSource::MarketingLead, ContactSource::from('marketing_lead'));
    }

    public function test_settings_array_casts_round_trip(): void
    {
        /** @var AccountingSettings $settings */
        $settings = AccountingSettings::query()->create([
            'number_series' => ['FAC' => 'FAC-2026-{:04}', 'PRO' => 'PRO-2026-{:04}'],
            'tva_rates' => [19, 9, 0],
            'currency' => 'DZD',
            'document_language' => 'fr',
        ]);

        $settings->refresh();
        $this->assertIsArray($settings->number_series);
        $this->assertSame('FAC-2026-{:04}', $settings->number_series['FAC']);
        $this->assertSame([19, 9, 0], $settings->tva_rates);
    }
}
