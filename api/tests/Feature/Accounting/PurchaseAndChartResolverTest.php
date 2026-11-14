<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Enums\PaymentMethod;
use App\Modules\Accounting\Domain\Enums\PaymentStatus;
use App\Modules\Accounting\Domain\Exceptions\DocumentWorkflowException;
use App\Modules\Accounting\Domain\Models\AccountingChartAccount;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use App\Modules\Accounting\Infrastructure\Services\AccountingChartOfAccounts;
use App\Modules\Accounting\Infrastructure\Services\ChartAccountResolver;
use App\Modules\Accounting\Infrastructure\Services\JournalPostingService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9367 / #9368 — comptabilité fournisseurs et résolution des comptes par pays.
 *
 * Deux défauts de l'audit sont couverts ici :
 *
 *  1. **Aucune comptabilité fournisseurs** : `DocumentType` ne connaissait que
 *     des documents de vente, le compte `401` n'était jamais mouvementé, et une
 *     facture d'achat saisie en `invoice` produisait une écriture de vente.
 *  2. **Le posting ignorait le plan comptable par pays** : les comptes
 *     `411`/`70`/`4457`/`512`/`53` étaient codés en dur alors que le tenant
 *     turc ou OHADA reçoit un plan comptable différent au provisioning.
 *
 * Les montants attendus sont calculés à la main (HT 1 000 + TVA 19 % = 190 →
 * TTC 1 190), jamais repris de l'implémentation.
 */
class PurchaseAndChartResolverTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(string $country = 'DZ', string $currency = 'DZD'): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => $country, 'currency' => $currency]);

        return $company;
    }

    private function contact(Company $company, string $type = 'customer', string $name = 'Tiers'): AccountingContact
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::create([
            'company_id' => $company->id,
            'type' => $type,
            'name' => $name,
            'email' => strtolower($type).'-'.uniqid().'@exemple.dz',
        ]);

        return $contact;
    }

    private function document(
        Company $company,
        string $type,
        AccountingContact $contact,
        float $ht = 1000.0,
        float $tvaRate = 19.0,
        string $date = '2026-08-05',
    ): AccountingDocument {
        $tax = round($ht * $tvaRate / 100, 2);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::create([
            'company_id' => $company->id,
            'type' => $type,
            'number' => strtoupper($type).'-'.uniqid(),
            'status' => DocumentStatus::Sent->value,
            'contact_id' => $contact->id,
            'issue_date' => $date,
            'currency' => $company->currency,
            'subtotal_ht' => $ht,
            'tax_amount' => $tax,
            'total_ttc' => round($ht + $tax, 2),
            'tva_rate' => $tvaRate,
        ]);

        return $document;
    }

    /** @return array<string, array{debit: float, credit: float}> */
    private function linesOf(AccountingDocument $document): array
    {
        /** @var array<string, array{debit: float, credit: float}> $lines */
        $lines = [];

        // Boucle explicite (et non `->each(closure)`) : PHPStan ne voit pas les
        // écritures faites par référence dans une closure et croirait `$lines`
        // toujours vide, d'où des « Offset '607' does not exist ».
        foreach (AccountingJournalEntry::query()
            ->where('source_type', 'document')
            ->where('source_id', $document->id)
            ->get() as $entry) {
            $lines[(string) $entry->account_code] = [
                'debit' => (float) $entry->debit,
                'credit' => (float) $entry->credit,
            ];
        }

        return $lines;
    }

    /**
     * Ligne d'écriture d'un compte, ou zéro si le compte n'a pas été mouvementé.
     * Évite les « Offset does not exist » de PHPStan sur un tableau construit
     * dynamiquement, sans affaiblir les assertions (un compte absent vaut 0).
     *
     * @param  array<string, array{debit: float, credit: float}>  $lines
     * @return array{debit: float, credit: float}
     */
    private function line(array $lines, string $code): array
    {
        return $lines[$code] ?? ['debit' => 0.0, 'credit' => 0.0];
    }

    // ── #9367 : comptabilité fournisseurs ─────────────────────────────────

    public function test_purchase_invoice_posts_supplier_charge_and_deductible_vat(): void
    {
        $company = $this->company();
        $supplier = $this->contact($company, 'supplier', 'Fournisseur SARL');
        $invoice = $this->document($company, DocumentType::PurchaseInvoice->value, $supplier);

        $count = app(JournalPostingService::class)->postDocument($invoice);

        // Calcul manuel : HT 1 000 + TVA 19 % (190) = TTC 1 190.
        //   D 607  Achats          1 000
        //   D 4456 TVA déductible    190
        //   C 401  Fournisseurs    1 190
        $this->assertSame(3, $count);

        $lines = $this->linesOf($invoice);

        $this->assertSame(1000.0, $this->line($lines, '607')['debit']);
        $this->assertSame(190.0, $this->line($lines, '4456')['debit']);
        $this->assertSame(1190.0, $this->line($lines, '401')['credit']);
        $this->assertSame(0.0, $this->line($lines, '401')['debit']);

        $this->assertTrue(app(JournalPostingService::class)->isPeriodBalanced('2026-08'));
    }

    public function test_purchase_credit_note_reverses_the_purchase_entry(): void
    {
        $company = $this->company();
        $supplier = $this->contact($company, 'supplier');
        $creditNote = $this->document($company, DocumentType::PurchaseCreditNote->value, $supplier);

        app(JournalPostingService::class)->postDocument($creditNote);

        $lines = $this->linesOf($creditNote);

        // Avoir fournisseur : 401 au débit, charge et TVA au crédit.
        $this->assertSame(1190.0, $this->line($lines, '401')['debit']);
        $this->assertSame(1000.0, $this->line($lines, '609')['credit']);
        $this->assertSame(190.0, $this->line($lines, '4456')['credit']);

        $this->assertTrue(app(JournalPostingService::class)->isPeriodBalanced('2026-08'));
    }

    public function test_payment_on_purchase_invoice_is_a_disbursement(): void
    {
        $company = $this->company();
        $supplier = $this->contact($company, 'supplier');
        $invoice = $this->document($company, DocumentType::PurchaseInvoice->value, $supplier);

        /** @var AccountingPayment $payment */
        $payment = AccountingPayment::create([
            'company_id' => $company->id,
            'document_id' => $invoice->id,
            'amount' => 1190.0,
            'method' => PaymentMethod::BankTransfer->value,
            'reference' => 'VIREMENT-001',
            'received_at' => '2026-08-10',
            'status' => PaymentStatus::Recorded->value,
        ]);

        app(JournalPostingService::class)->postPayment($payment);

        $treasury = AccountingJournalEntry::query()
            ->where('source_type', 'payment')
            ->where('source_id', $payment->id)
            ->where('account_code', '512')
            ->first();

        $supplierLine = AccountingJournalEntry::query()
            ->where('source_type', 'payment')
            ->where('source_id', $payment->id)
            ->where('account_code', '401')
            ->first();

        $this->assertInstanceOf(AccountingJournalEntry::class, $treasury);
        $this->assertInstanceOf(AccountingJournalEntry::class, $supplierLine);

        // Décaissement : 401 au débit, banque au crédit.
        $this->assertSame(1190.0, (float) $supplierLine->debit);
        $this->assertSame(1190.0, (float) $treasury->credit);
    }

    public function test_purchase_document_rejects_a_customer_contact(): void
    {
        $company = $this->company();
        $customer = $this->contact($company, 'customer');

        $this->expectException(DocumentWorkflowException::class);

        app(\App\Modules\Accounting\Application\Services\DocumentWorkflowService::class)
            ->createDraft([
                'type' => DocumentType::PurchaseInvoice->value,
                'contact_id' => $customer->id,
                'lines' => [['description' => 'Prestation', 'quantity' => 1, 'unit_price' => 100.0]],
                'tva_rate' => 19.0,
            ], (string) $company->id);
    }

    public function test_sales_document_rejects_a_supplier_contact(): void
    {
        $company = $this->company();
        $supplier = $this->contact($company, 'supplier');

        $this->expectException(DocumentWorkflowException::class);

        app(\App\Modules\Accounting\Application\Services\DocumentWorkflowService::class)
            ->createDraft([
                'type' => DocumentType::Invoice->value,
                'contact_id' => $supplier->id,
                'lines' => [['description' => 'Prestation', 'quantity' => 1, 'unit_price' => 100.0]],
                'tva_rate' => 19.0,
            ], (string) $company->id);
    }

    // ── #9368 : résolution du plan comptable par pays ─────────────────────

    public function test_tekduzen_tenant_posts_on_its_own_chart(): void
    {
        $company = $this->company('TR', 'TRY');
        $customer = $this->contact($company, 'customer');
        $invoice = $this->document($company, DocumentType::Invoice->value, $customer);

        app(JournalPostingService::class)->postDocument($invoice);

        $lines = $this->linesOf($invoice);

        // Tekdüzen Hesap Planı : 120 Alıcılar / 600 Yurtiçi satışlar / 391 Hesaplanan KDV.
        // Les comptes français ne doivent PAS apparaître.
        $this->assertArrayNotHasKey('411', $lines);
        $this->assertArrayNotHasKey('706', $lines);
        $this->assertArrayNotHasKey('4457', $lines);
    }

    public function test_syscohada_tenant_posts_on_its_own_chart(): void
    {
        $company = $this->company('SN', 'XOF');
        $customer = $this->contact($company, 'customer');
        $invoice = $this->document($company, DocumentType::Invoice->value, $customer);

        app(JournalPostingService::class)->postDocument($invoice);

        $lines = $this->linesOf($invoice);

        // SYSCOHADA révisé : 411 Clients / 706 Services vendus / 44571 TVA collectée.
        // Montants identiques au PCG (HT 1 000 + TVA 19 % = 1 190) : c'est la
        // numérotation des comptes qui change, d'où l'assertion sur 44571 et
        // l'absence du 4457 français. Sans ces assertions le test était signalé
        // « risky » (aucune assertion exécutée).
        $this->assertSame(1190.0, $this->line($lines, '411')['debit']);
        $this->assertSame(1000.0, $this->line($lines, '706')['credit']);
        $this->assertSame(190.0, $this->line($lines, '44571')['credit']);
        $this->assertArrayNotHasKey('4457', $lines);
    }

    public function test_pcg_tenant_keeps_historical_accounts(): void
    {
        $company = $this->company('DZ', 'DZD');
        $customer = $this->contact($company, 'customer');
        $invoice = $this->document($company, DocumentType::Invoice->value, $customer);

        app(JournalPostingService::class)->postDocument($invoice);

        $lines = $this->linesOf($invoice);

        $this->assertSame(1190.0, $this->line($lines, '411')['debit']);
        $this->assertSame(1000.0, $this->line($lines, '706')['credit']);
        $this->assertSame(190.0, $this->line($lines, '4457')['credit']);
    }

    public function test_resolver_uses_the_tenant_chart_when_it_diverges_from_the_country_registry(): void
    {
        $company = $this->company('DZ', 'DZD');

        // Le comptable du tenant a renommé le compte client (libellé
        // personnalisé). Le code reste 411 : le résolveur doit reprendre le
        // libellé du tenant, pas celui du registre pays.
        AccountingChartAccount::create([
            'company_id' => $company->id,
            'code' => '411',
            'label' => 'Clients — grand compte',
            'type' => 'asset',
            'is_active' => true,
        ]);

        $resolver = ChartAccountResolver::forCompany($company);

        $this->assertSame('411', $resolver->accountCode('clients'));
        $this->assertSame('Clients — grand compte', $resolver->label('clients'));
    }

    public function test_resolver_exposes_every_posting_family_for_every_supported_country(): void
    {
        $families = [
            'clients', 'suppliers', 'bank', 'cash', 'vat_collected', 'vat_deductible',
            'sales_revenue', 'sales_discount', 'purchases', 'purchase_discount',
        ];

        foreach (AccountingChartOfAccounts::supportedCountries() as $country) {
            $resolver = ChartAccountResolver::forCountry($country);

            foreach ($families as $family) {
                $entry = $resolver->entry($family);
                $this->assertNotSame('', $entry['code'], "Code vide pour {$family} en {$country}");
                $this->assertNotSame('', $entry['label'], "Libellé vide pour {$family} en {$country}");
            }
        }
    }

    public function test_cash_and_bank_mapping_follows_the_payment_method(): void
    {
        $resolver = ChartAccountResolver::forCountry('DZ');

        $this->assertSame('cash', $resolver->treasuryFamilyFor(PaymentMethod::Cash->value));
        $this->assertSame('bank', $resolver->treasuryFamilyFor(PaymentMethod::BankTransfer->value));
        $this->assertSame('bank', $resolver->treasuryFamilyFor(PaymentMethod::Card->value));
        // Moyen inconnu → banque (jamais la caisse par défaut).
        $this->assertSame('bank', $resolver->treasuryFamilyFor('unknown-method'));
    }

    // ── #9367 : numérotation et export ────────────────────────────────────

    public function test_purchase_types_use_their_own_numbering_series(): void
    {
        $company = $this->company();

        $service = app(\App\Modules\Accounting\Domain\Contracts\DocumentNumberingInterface::class);

        $purchase = $service->nextNumber((string) $company->id, DocumentType::PurchaseInvoice);
        $sale = $service->nextNumber((string) $company->id, DocumentType::Invoice);

        // #9367 — séries achats distinctes : sans cela, une facture fournisseur
        // aurait consommé la numérotation client et cassé la chaîne légale.
        $this->assertStringStartsWith('FACF-', $purchase);
        $this->assertStringStartsWith('FAC-', $sale);
        // Une facture d'achat ne doit JAMAIS produire un numéro de vente.
        $this->assertStringNotContainsString('FACF', $sale);
    }

    public function test_purchase_credit_note_has_its_own_series_too(): void
    {
        $company = $this->company();
        $service = app(\App\Modules\Accounting\Domain\Contracts\DocumentNumberingInterface::class);

        $this->assertStringStartsWith('AVOF-', $service->nextNumber((string) $company->id, DocumentType::PurchaseCreditNote));
        $this->assertStringStartsWith('AVO-', $service->nextNumber((string) $company->id, DocumentType::CreditNote));
    }
}
