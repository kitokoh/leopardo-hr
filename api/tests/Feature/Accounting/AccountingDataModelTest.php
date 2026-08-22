<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\ContactType;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Enums\PaymentMethod;
use App\Modules\Accounting\Domain\Enums\PaymentStatus;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentLine;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5221 — socle data du module Comptabilité (Phase A).
 *
 * Vérifie que les 5 migrations tenant additives créent bien les tables
 * `accounting_*` avec `company_id` NON nullable, que les modèles DDD exposent
 * les casts/relations attendus, et que les colonnes sensibles sont chiffrées
 * au repos (mêmes politiques que Payroll/Cabinet).
 */
class AccountingDataModelTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        app()->instance('current_company', $company);
    }

    public function test_tenant_migrations_create_all_accounting_tables(): void
    {
        $this->assertTrue(Schema::hasTable('accounting_contacts'));
        $this->assertTrue(Schema::hasTable('accounting_documents'));
        $this->assertTrue(Schema::hasTable('accounting_document_lines'));
        $this->assertTrue(Schema::hasTable('accounting_payments'));
        $this->assertTrue(Schema::hasTable('accounting_settings'));
    }

    public function test_company_id_is_non_nullable_on_every_table(): void
    {
        foreach ([
            'accounting_contacts',
            'accounting_documents',
            'accounting_document_lines',
            'accounting_payments',
            'accounting_settings',
        ] as $table) {
            $found = false;
            foreach (Schema::getColumns($table) as $column) {
                if (($column['name'] ?? null) !== 'company_id') {
                    continue;
                }
                $found = true;
                /** @var array{name: string, nullable: bool} $column */
                $this->assertFalse($column['nullable'], "company_id doit être NON nullable sur {$table}.");
            }
            $this->assertTrue($found, "La table {$table} doit porter la colonne company_id.");
        }
    }

    public function test_accounting_settings_is_unique_per_company(): void
    {
        $first = AccountingSettings::query()->create([
            'currency' => 'DZD',
            'document_language' => 'fr',
        ]);
        $this->assertSame($this->company->id, $first->company_id);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Deuxième ligne pour le MÊME tenant → violation d'unicité company_id.
        DB::table('accounting_settings')->insert([
            'company_id' => $this->company->id,
            'currency' => 'DZD',
            'document_language' => 'fr',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_contact_document_line_payment_relations_round_trip(): void
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::query()->create([
            'type' => ContactType::Customer->value,
            'name' => 'Client Alpha',
            'tax_id' => 'NIF-2026-ALPHA',
            'email' => 'alpha@client.test',
            'metadata' => ['vat' => 'standard', 'note' => 'compte prioritaire'],
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
            'metadata' => ['linked' => ['credit_note' => null]],
        ]);

        AccountingDocumentLine::query()->create([
            'document_id' => $document->id,
            'description' => 'Prestation de conseil',
            'quantity' => 1,
            'unit_price' => 1000.0,
            'tax_id' => 'TVA-19',
            'sort_order' => 1,
        ]);

        AccountingPayment::query()->create([
            'document_id' => $document->id,
            'amount' => 500.0,
            'method' => PaymentMethod::BankTransfer->value,
            'reference' => 'VIR-2026-00842',
            'received_at' => '2026-08-22',
            'status' => PaymentStatus::Recorded->value,
            'metadata' => ['bank' => 'BNA', 'account' => '004-XXX'],
        ]);

        $fresh = AccountingDocument::query()->with(['contact', 'lines', 'payments'])->findOrFail($document->id);

        $this->assertNotNull($fresh->contact);
        $this->assertSame($contact->id, $fresh->contact->id);
        $this->assertCount(1, $fresh->lines);
        $this->assertCount(1, $fresh->payments);

        $line = $fresh->lines->first();
        $payment = $fresh->payments->first();
        $this->assertNotNull($line);
        $this->assertNotNull($payment);
        $this->assertSame('Prestation de conseil', $line->description);
        $this->assertSame(500.0, $payment->amount);
        // Les métadonnées chiffrées doivent revenir intactes (cast encrypted:array).
        $this->assertSame(['linked' => ['credit_note' => null]], $fresh->metadata);
    }

    public function test_sensitive_columns_are_encrypted_at_rest(): void
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::query()->create([
            'type' => ContactType::Supplier->value,
            'name' => 'Fournisseur Beta',
            'tax_id' => 'NIF-SENSIBLE-777',
        ]);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::query()->create([
            'type' => DocumentType::Receipt->value,
            'number' => 'RCU-2026-0001',
            'status' => DocumentStatus::Draft->value,
            'issue_date' => '2026-08-22',
            'currency' => 'DZD',
        ]);

        /** @var AccountingPayment $payment */
        $payment = AccountingPayment::query()->create([
            'document_id' => $document->id,
            'amount' => 100.0,
            'method' => PaymentMethod::Check->value,
            'reference' => 'CHQ-00001234',
        ]);

        $rawContact = DB::table('accounting_contacts')->whereKey($contact->id)->first();
        $rawPayment = DB::table('accounting_payments')->whereKey($payment->id)->first();

        $this->assertNotNull($rawContact);
        $this->assertNotNull($rawPayment);
        $this->assertIsString($rawContact->tax_id);
        $this->assertNotSame('NIF-SENSIBLE-777', $rawContact->tax_id, 'Le NIF ne doit pas être stocké en clair.');
        $this->assertStringContainsString('eyJpdiI6', $rawContact->tax_id, 'Valeur chiffrée attendue (enveloppe Laravel).');

        $this->assertIsString($rawPayment->reference);
        $this->assertNotSame('CHQ-00001234', $rawPayment->reference, 'La référence de paiement ne doit pas être stockée en clair.');

        // Round-trip : le modèle déchiffre à la lecture.
        $this->assertSame('NIF-SENSIBLE-777', $contact->fresh()->tax_id);
        $this->assertSame('CHQ-00001234', $payment->fresh()->reference);
    }

    public function test_enum_values_match_domain_contract(): void
    {
        $this->assertSame(['customer', 'supplier', 'both'], ContactType::values());
        $this->assertSame(['invoice', 'proforma', 'quote', 'credit_note', 'delivery_note', 'receipt'], DocumentType::values());
        $this->assertSame(['draft', 'sent', 'partially_paid', 'paid', 'cancelled', 'overdue'], DocumentStatus::values());
        $this->assertSame(['cash', 'bank_transfer', 'check', 'card', 'other'], PaymentMethod::values());
        $this->assertSame(['pending', 'recorded', 'matched'], PaymentStatus::values());
    }
}
