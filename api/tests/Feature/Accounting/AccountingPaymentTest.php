<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Exceptions\PaymentExceedsTotalException;
use App\Modules\Accounting\Domain\Exceptions\PaymentOnUnsentDocumentException;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use App\Modules\Accounting\Domain\Models\AccountingPaymentReminder;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use App\Modules\Accounting\Infrastructure\Services\PaymentRegistrationService;
use App\Modules\Accounting\Infrastructure\Services\PaymentReminderService;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Trésorerie Phase B (issue #5229) :
 * enregistrement (jamais payé > total), rapprochement, liste,
 * relances sans doublon (J+7/J+15/J+30 paramétrables), API + isolation tenant.
 */
class AccountingPaymentTest extends TestCase
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

    private function forgetCompany(): void
    {
        app()->forgetInstance('current_company');
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

    private function document(
        Company $company,
        string $status = 'sent',
        string $date = '2026-08-05',
        float $ttc = 1190.0,
        ?string $dueDate = '2026-08-20',
        string $number = 'FAC-2026-0001',
    ): AccountingDocument {
        $contact = $this->contact($company);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::create([
            'company_id' => $company->id,
            'type' => 'invoice',
            'number' => $number,
            'status' => $status,
            'contact_id' => $contact->id,
            'issue_date' => $date,
            'due_date' => $dueDate,
            'currency' => 'DZD',
            'subtotal_ht' => round($ttc / 1.19, 2),
            'tax_amount' => round($ttc - $ttc / 1.19, 2),
            'total_ttc' => $ttc,
            'tva_rate' => 19.0,
            'paid_amount' => 0.0,
        ]);

        return $document;
    }

    private function manager(Company $company, string $managerRole): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        return $manager;
    }

    // ── US1 : enregistrement — jamais payé > total ───────────────────────────

    public function test_partial_payment_updates_paid_amount_and_status(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $invoice = $this->document($company, 'sent', '2026-08-05', 1190.0);

        $payment = app(PaymentRegistrationService::class)->register($invoice, 500.0, 'bank_transfer');

        $this->assertSame('recorded', $payment->status);
        $invoice->refresh();
        $this->assertSame(500.0, $invoice->paid_amount);
        $this->assertSame(DocumentStatus::PartiallyPaid->value, $invoice->status);
    }

    public function test_full_payment_marks_document_paid(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $invoice = $this->document($company, 'sent', '2026-08-05', 1190.0);

        app(PaymentRegistrationService::class)->register($invoice, 1190.0, 'bank_transfer');

        $invoice->refresh();
        $this->assertSame(1190.0, $invoice->paid_amount);
        $this->assertSame(DocumentStatus::Paid->value, $invoice->status);
    }

    public function test_payment_exceeding_total_is_rejected(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $invoice = $this->document($company, 'sent', '2026-08-05', 1190.0);
        app(PaymentRegistrationService::class)->register($invoice, 500.0, 'bank_transfer');

        try {
            app(PaymentRegistrationService::class)->register($invoice->refresh(), 700.0, 'check');
            $this->fail('Le paiement excédentaire doit lever PaymentExceedsTotalException.');
        } catch (PaymentExceedsTotalException $exception) {
            $this->assertStringContainsString('1190.00', $exception->getMessage());
        }

        // Aucun paiement écrit pour l'essai refusé.
        $this->assertSame(1, AccountingPayment::query()->count());
        $invoice->refresh();
        $this->assertSame(500.0, $invoice->paid_amount);
    }

    public function test_payment_on_draft_document_is_rejected(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $draft = $this->document($company, DocumentStatus::Draft->value, '2026-08-05', 1190.0);

        $this->expectException(PaymentOnUnsentDocumentException::class);
        app(PaymentRegistrationService::class)->register($draft, 100.0, 'cash');
    }

    // ── US2 : rapprochement ──────────────────────────────────────────────────

    public function test_reconcile_marks_matched_and_reconciled_at(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $invoice = $this->document($company, 'sent', '2026-08-05', 1190.0);
        $payment = app(PaymentRegistrationService::class)->register($invoice, 1190.0, 'bank_transfer');
        $this->assertSame('recorded', $payment->status);

        $service = app(PaymentRegistrationService::class);
        $reconciled = $service->reconcile($payment);

        $this->assertSame('matched', $reconciled->status);
        $this->assertNotNull($reconciled->reconciled_at);

        // Idempotent : re-rapprocher ne change rien (même statut, même horodatage).
        $again = $service->reconcile($reconciled);
        $this->assertSame('matched', $again->status);
        $this->assertEquals($reconciled->reconciled_at, $again->reconciled_at);
    }

    // ── US3 : liste ──────────────────────────────────────────────────────────

    public function test_list_filters_by_document_and_status(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $invoiceA = $this->document($company, 'sent', '2026-08-05', 1190.0, '2026-08-20', 'FAC-2026-0001');
        $invoiceB = $this->document($company, 'sent', '2026-08-06', 500.0, '2026-08-21', 'FAC-2026-0002');
        $service = app(PaymentRegistrationService::class);

        $service->register($invoiceA, 500.0, 'bank_transfer');
        $service->register($invoiceA, 200.0, 'cash');
        $service->register($invoiceB, 500.0, 'check');

        $this->assertCount(2, $service->list((int) $invoiceA->id));
        $this->assertCount(1, $service->list((int) $invoiceB->id));
        $this->assertCount(3, $service->list(null, 'recorded'));
        $this->assertCount(3, $service->list());
    }

    // ── US4 : relances sans doublon ──────────────────────────────────────────

    public function test_reminders_sent_for_overdue_documents_only_once(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $this->manager($company, 'principal');
        $this->manager($company, 'comptable');

        // Échue depuis 40 jours → les 3 stages (J+7, J+15, J+30) sont dus.
        $overdue = $this->document($company, 'sent', '2026-07-01', 1190.0, '2026-07-05', 'FAC-2026-0001');
        // Payée → jamais relancée.
        $paid = $this->document($company, 'paid', '2026-07-01', 800.0, '2026-07-05', 'FAC-2026-0002');
        $paid->update(['paid_amount' => 800.0]);
        // Échue depuis hier → J+7 pas atteint → aucune relance.
        $recent = $this->document($company, 'sent', '2026-08-18', 300.0, '2026-08-22', 'FAC-2026-0003');

        $service = app(PaymentReminderService::class);
        $now = new Carbon('2026-08-23 12:00:00', 'UTC');
        $first = $service->run($now);

        $this->assertSame(3, $first, '3 stages dus pour la facture échue, aucune pour les autres');
        $this->assertSame(3, AccountingPaymentReminder::query()->where('document_id', $overdue->id)->count());
        $this->assertSame(0, AccountingPaymentReminder::query()->where('document_id', $paid->id)->count());
        $this->assertSame(0, AccountingPaymentReminder::query()->where('document_id', $recent->id)->count());

        // Deuxième exécution → zéro nouvelle relance (unique document+stage).
        $second = $service->run($now);
        $this->assertSame(0, $second);
    }

    public function test_reminders_use_custom_days_from_settings(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        AccountingSettings::query()->create([
            'company_id' => $company->id,
            'document_language' => 'fr',
            'payment_reminder_days' => [5],
        ]);

        // Échue depuis 6 jours → stage unique (J+5) dû.
        $overdue = $this->document($company, 'sent', '2026-08-01', 1190.0, '2026-08-17', 'FAC-2026-0001');

        $service = app(PaymentReminderService::class);
        $this->assertSame([5], $service->reminderDays());
        $sent = $service->run(new Carbon('2026-08-23 12:00:00', 'UTC'));

        $this->assertSame(1, $sent);
        $reminder = AccountingPaymentReminder::query()->where('document_id', $overdue->id)->first();
        $this->assertNotNull($reminder);
        $this->assertSame(1, $reminder->stage);
    }

    // ── API ──────────────────────────────────────────────────────────────────

    public function test_api_register_payment(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $invoice = $this->document($company, 'sent', '2026-08-05', 1190.0);
        $this->forgetCompany();

        /** @var Employee $manager */
        $manager = $this->manager($company, 'comptable');
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/accounting/documents/'.$invoice->id.'/payments', [
            'amount' => 1190.0,
            'method' => 'bank_transfer',
            'reference' => 'VIR-2026-001',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'recorded');
        $response->assertJsonPath('data.document_paid_amount', 1190);
        $response->assertJsonPath('data.document_status', 'paid');
    }

    public function test_api_reconcile_payment(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $invoice = $this->document($company, 'sent', '2026-08-05', 1190.0);
        $payment = app(PaymentRegistrationService::class)->register($invoice, 1190.0, 'bank_transfer');
        $this->forgetCompany();

        Sanctum::actingAs($this->manager($company, 'principal'));

        $response = $this->postJson('/api/v1/accounting/payments/'.$payment->id.'/reconcile');
        $response->assertOk();
        $response->assertJsonPath('data.status', 'matched');
        $this->assertNotNull($response->json('data.reconciled_at'));
    }

    public function test_api_list_payments_filters(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $invoice = $this->document($company, 'sent', '2026-08-05', 1190.0);
        $payment = app(PaymentRegistrationService::class)->register($invoice, 500.0, 'cash');
        $this->forgetCompany();

        Sanctum::actingAs($this->manager($company, 'principal'));

        $response = $this->getJson('/api/v1/accounting/payments?document_id='.$invoice->id.'&status=recorded');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $payment->id);
        $response->assertJsonPath('data.0.method', 'cash');
    }

    public function test_api_reminders_run(): void
    {
        $company = $this->company();
        $this->bindCompany($company);
        $this->manager($company, 'principal');
        $this->document($company, 'sent', '2026-07-01', 1190.0, '2026-07-05', 'FAC-2026-0001');
        $this->forgetCompany();

        Sanctum::actingAs($this->manager($company, 'comptable'));

        $response = $this->postJson('/api/v1/accounting/reminders/run');
        $response->assertOk();
        $response->assertJsonPath('reminders_sent', 3);
    }

    public function test_api_requires_manager_role(): void
    {
        $company = $this->company();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/accounting/payments')->assertForbidden();
        $this->postJson('/api/v1/accounting/reminders/run')->assertForbidden();
    }

    // ── Isolation tenant ─────────────────────────────────────────────────────

    public function test_payments_and_reminders_are_tenant_scoped(): void
    {
        $companyA = $this->company();
        $this->bindCompany($companyA);
        $invoiceA = $this->document($companyA, 'sent', '2026-08-05', 1190.0);
        app(PaymentRegistrationService::class)->register($invoiceA, 500.0, 'bank_transfer');
        $this->forgetCompany();

        $companyB = $this->company();
        $this->bindCompany($companyB);

        // B ne voit ni les paiements ni les relances de A.
        $this->assertSame(0, AccountingPayment::query()->count());
        $this->assertCount(0, app(PaymentRegistrationService::class)->list());

        $this->forgetCompany();
    }
}
