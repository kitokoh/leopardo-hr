<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use App\Modules\Accounting\Infrastructure\Services\AccountingLedgerService;
use App\Modules\Accounting\Infrastructure\Services\JournalPostingService;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Grand livre + balance de vérification (issue #5422).
 *
 * Consultation du journal : grand livre par compte (solde cumulé après
 * chaque écriture + solde d'ouverture), balance de vérification (totaux par
 * compte + équilibre), validation de la période et isolation tenant
 * (WHERE company_id explicite, fail-closed #3727).
 */
class AccountingLedgerTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(string $country = 'DZ'): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => $country, 'currency' => $country === 'MA' ? 'MAD' : 'DZD']);

        return $company;
    }

    private function bindCompany(Company $company): void
    {
        app()->instance('current_company', $company);
    }

    private function forgetCompany(): void
    {
        app()->forgetInstance('current_company');
    }

    private function contact(Company $company, string $email = 'client@exemple.dz'): AccountingContact
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Client Test',
            'email' => $email,
        ]);

        return $contact;
    }

    /**
     * Crée un document comptable puis passe ses écritures au journal
     * (JournalPostingService) — la période est dérivée de la date d'émission.
     */
    private function document(
        Company $company,
        string $type = 'invoice',
        string $status = 'sent',
        string $date = '2026-08-05',
        float $ht = 1000.0,
        float $tax = 190.0,
        string $number = 'FAC-2026-0001',
    ): AccountingDocument {
        $contact = $this->contact($company);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::create([
            'company_id' => $company->id,
            'type' => $type,
            'number' => $number,
            'status' => $status,
            'contact_id' => $contact->id,
            'issue_date' => $date,
            'currency' => 'DZD',
            'subtotal_ht' => $ht,
            'tax_amount' => $tax,
            'total_ttc' => $ht + $tax,
            'tva_rate' => $tax > 0 ? round($tax / $ht * 100, 2) : null,
        ]);

        app(JournalPostingService::class)->postDocument($document);

        return $document;
    }

    /**
     * Crée un paiement puis passe son mouvement de trésorerie au journal
     * (512/53 ↔ 411).
     */
    private function payment(Company $company, AccountingDocument $document, float $amount, string $method = 'bank_transfer', string $date = '2026-08-10', string $status = 'recorded', ?string $reference = null): AccountingPayment
    {
        /** @var AccountingPayment $payment */
        $payment = AccountingPayment::create([
            'company_id' => $company->id,
            'document_id' => $document->id,
            'amount' => $amount,
            'method' => $method,
            'reference' => $reference,
            'received_at' => $date,
            'status' => $status,
        ]);

        app(JournalPostingService::class)->postPayment($payment);

        return $payment;
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        return $manager;
    }

    // ── (a) grand livre : running_balance séquentiel sur la période ────────

    public function test_ledger_returns_account_entries_with_running_balance(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        // 3 écritures sur le compte 411 (dans l'ordre de date) :
        //   08-05 D 1190 (facture) → 1190
        //   08-10 C 500  (encaissement) → 690
        //   08-15 D 2380 (facture) → 3070
        $invoice1 = $this->document($company, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0, 'FAC-2026-0001');
        $this->payment($company, $invoice1, 500.0, 'bank_transfer', '2026-08-10');
        $this->document($company, 'invoice', 'sent', '2026-08-15', 2000.0, 380.0, 'FAC-2026-0002');
        $this->forgetCompany();

        $this->manager($company);

        $response = $this->getJson('/api/v1/accounting/ledger?period=2026-08&account_code=411');
        $response->assertOk();
        $response->assertJsonPath('meta.current_page', 1);
        $response->assertJsonPath('meta.last_page', 1);
        $response->assertJsonPath('meta.per_page', 20);
        $response->assertJsonPath('meta.total', 3);
        $response->assertJsonPath('meta.opening_balance', 0);
        $response->assertJsonPath('meta.account_code', '411');
        $response->assertJsonCount(3, 'data');

        $response->assertJsonPath('data.0.entry_date', '2026-08-05');
        $response->assertJsonPath('data.0.account_code', '411');
        $response->assertJsonPath('data.0.debit', 1190);
        $response->assertJsonPath('data.0.credit', 0);
        $response->assertJsonPath('data.0.running_balance', 1190);
        $response->assertJsonPath('data.1.running_balance', 690);
        $response->assertJsonPath('data.2.running_balance', 3070);
    }

    public function test_ledger_running_balance_is_continuous_across_pages(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        $invoice1 = $this->document($company, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0, 'FAC-2026-0001');
        $this->payment($company, $invoice1, 500.0, 'bank_transfer', '2026-08-10');
        $this->document($company, 'invoice', 'sent', '2026-08-15', 2000.0, 380.0, 'FAC-2026-0002');
        $this->forgetCompany();

        $this->manager($company);

        $page1 = $this->getJson('/api/v1/accounting/ledger?period=2026-08&account_code=411&per_page=2');
        $page1->assertOk();
        $page1->assertJsonPath('meta.current_page', 1);
        $page1->assertJsonPath('meta.last_page', 2);
        $page1->assertJsonPath('meta.total', 3);
        $page1->assertJsonCount(2, 'data');
        $page1->assertJsonPath('data.0.running_balance', 1190);
        $page1->assertJsonPath('data.1.running_balance', 690);

        // Page 2 : le solde repart du solde cumulé de fin de page 1.
        $page2 = $this->getJson('/api/v1/accounting/ledger?period=2026-08&account_code=411&per_page=2&page=2');
        $page2->assertOk();
        $page2->assertJsonPath('meta.current_page', 2);
        $page2->assertJsonCount(1, 'data');
        $page2->assertJsonPath('data.0.running_balance', 3070);
    }

    // ── (b) grand livre : filtre compte + solde d'ouverture ─────────────────

    public function test_ledger_filters_by_account_and_exposes_opening_balance(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        // Écriture antérieure à la période (juillet) → solde d'ouverture du 411.
        $this->document($company, 'invoice', 'sent', '2026-07-20', 1000.0, 190.0, 'FAC-2026-0000');
        // Écriture dans la période (août) : D 595 sur le 411.
        $this->document($company, 'invoice', 'sent', '2026-08-05', 500.0, 95.0, 'FAC-2026-0001');
        $this->forgetCompany();

        $this->manager($company);

        $response = $this->getJson('/api/v1/accounting/ledger?period=2026-08&account_code=411');
        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('meta.opening_balance', 1190);
        $response->assertJsonPath('data.0.running_balance', 1785);

        // Sans filtre compte : pas de solde d'ouverture (null), toutes les écritures.
        $all = $this->getJson('/api/v1/accounting/ledger?period=2026-08');
        $all->assertOk();
        $all->assertJsonPath('meta.total', 3);
        $all->assertJsonPath('meta.opening_balance', null);
        $all->assertJsonPath('meta.account_code', null);
    }

    // ── (c) balance de vérification ─────────────────────────────────────────

    public function test_balance_aggregates_accounts_with_balanced_totals(): void
    {
        $company = $this->company();
        $this->bindCompany($company);

        $invoice = $this->document($company, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0, 'FAC-2026-0001');
        $this->payment($company, $invoice, 500.0, 'bank_transfer', '2026-08-10');
        $this->forgetCompany();

        $this->manager($company);

        $response = $this->getJson('/api/v1/accounting/balance?period=2026-08');
        $response->assertOk();
        $response->assertJsonPath('meta.period', '2026-08');
        $response->assertJsonPath('meta.balanced', true);
        $response->assertJsonPath('meta.totals.total_debit', 1690);
        $response->assertJsonPath('meta.totals.total_credit', 1690);
        $response->assertJsonPath('meta.totals.difference', 0);
        $response->assertJsonCount(4, 'data');

        // Ordre par code : 411, 4457, 512, 70.
        $response->assertJsonPath('data.0.account_code', '411');
        $response->assertJsonPath('data.0.account_label', 'Clients');
        $response->assertJsonPath('data.0.total_debit', 1190);
        $response->assertJsonPath('data.0.total_credit', 500);
        $response->assertJsonPath('data.0.balance', 690);
        $response->assertJsonPath('data.1.account_code', '4457');
        $response->assertJsonPath('data.1.balance', -190);
        $response->assertJsonPath('data.2.account_code', '512');
        $response->assertJsonPath('data.2.balance', 500);
        $response->assertJsonPath('data.3.account_code', '70');
        $response->assertJsonPath('data.3.total_debit', 0);
        $response->assertJsonPath('data.3.total_credit', 1000);
        $response->assertJsonPath('data.3.balance', -1000);
    }

    // ── (d) validation de la période ────────────────────────────────────────

    public function test_ledger_and_balance_reject_invalid_period(): void
    {
        $company = $this->company();
        $this->manager($company);

        // Période requise, format strict YYYY-MM (mois 01-12).
        $this->getJson('/api/v1/accounting/ledger')->assertStatus(422);
        $this->getJson('/api/v1/accounting/ledger?period=2026-13')->assertStatus(422);
        $this->getJson('/api/v1/accounting/ledger?period=2026-8')->assertStatus(422);
        $this->getJson('/api/v1/accounting/ledger?period=2026/08')->assertStatus(422);
        $this->getJson('/api/v1/accounting/ledger?period=2026-08&per_page=0')->assertStatus(422);
        $this->getJson('/api/v1/accounting/ledger?period=2026-08&per_page=200')->assertStatus(422);

        $this->getJson('/api/v1/accounting/balance')->assertStatus(422);
        $this->getJson('/api/v1/accounting/balance?period=2026-13')->assertStatus(422);
        $this->getJson('/api/v1/accounting/balance?period=2026/08')->assertStatus(422);
    }

    // ── (e) isolation tenant ────────────────────────────────────────────────

    public function test_ledger_and_balance_are_tenant_scoped(): void
    {
        $companyA = $this->company();
        $this->bindCompany($companyA);
        $this->document($companyA, 'invoice', 'sent', '2026-08-05', 1000.0, 190.0, 'FAC-2026-0001');
        $this->assertSame(3, AccountingJournalEntry::query()->count());
        $this->forgetCompany();

        $companyB = $this->company('MA');
        $this->manager($companyB);

        // B ne voit aucune écriture de A : grand livre vide…
        $ledger = $this->getJson('/api/v1/accounting/ledger?period=2026-08');
        $ledger->assertOk();
        $ledger->assertJsonPath('meta.total', 0);
        $ledger->assertJsonCount(0, 'data');

        // … et balance à zéro, équilibrée par construction.
        $balance = $this->getJson('/api/v1/accounting/balance?period=2026-08');
        $balance->assertOk();
        $balance->assertJsonCount(0, 'data');
        $balance->assertJsonPath('meta.totals.total_debit', 0);
        $balance->assertJsonPath('meta.totals.total_credit', 0);
        $balance->assertJsonPath('meta.balanced', true);

        // Niveau service : même cloisonnement avec le company_id de B.
        $ledgerService = app(AccountingLedgerService::class);
        $this->assertSame(0, $ledgerService->ledger($companyB->id, null, '2026-08', 20)->total());
        $this->assertSame(0.0, $ledgerService->openingBalance($companyB->id, '411', '2026-08'));
        $balanceB = $ledgerService->balance($companyB->id, '2026-08');
        $this->assertTrue($balanceB['balanced']);
    }
}
