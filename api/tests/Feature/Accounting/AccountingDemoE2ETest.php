<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Application\Actions\SeedAccountingDemoData;
use App\Modules\Accounting\Domain\Contracts\PdfRendererInterface;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Enums\PaymentMethod;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentLine;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use App\Modules\Accounting\Infrastructure\Services\DocumentWorkflowService;
use App\Modules\Accounting\Infrastructure\Services\SequentialDocumentNumbering;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5274 — Données démo/seed + E2E parcours facture.
 *
 * E2E : contact → devis → facture → PDF → email → paiement → rapprochement,
 * sur le code RÉEL mergé sur main. PDF et email sont fakes (implémentations
 * #5224 / #5225 en vol) — le reste du parcours passe par l'API, les modèles,
 * la numérotation et le workflow réels. Le test doit rester vert sans dépendre
 * des PRs en vol (ajout : brancher le vrai renderer/mailable quand #5224/#5225
 * seront mergés).
 */
class AccountingDemoE2ETest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private DocumentWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'timezone' => 'UTC']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD', 'timezone' => 'UTC']);
        $this->companyB = $companyB;

        $this->workflow = new DocumentWorkflowService();

        // PDF : fake tant que l'implémentation #5224 n'est pas mergée.
        app()->instance(PdfRendererInterface::class, new class implements PdfRendererInterface
        {
            public function render(AccountingDocument $document, string $locale): string
            {
                return sprintf('/demo/pdfs/%s-%s.pdf', $document->number, $locale);
            }
        });

        Mail::fake();
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(Company $company, string $managerRole = 'comptable'): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        return $manager;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // US1 — Le seed crée une vitrine réaliste et idempotente
    // ─────────────────────────────────────────────────────────────────────────

    public function test_demo_seed_creates_realistic_showcase(): void
    {
        $seeder = new SeedAccountingDemoData();

        $result = $seeder->seed($this->companyA);

        $this->assertTrue($result['seeded']);
        $this->assertSame('SEEDED', $result['status']);
        $this->assertSame(5, $result['contacts']);
        $this->assertSame(7, $result['documents']);
        $this->assertSame(2, $result['payments']);

        // Paramétrage provisionné (séries + devise pays).
        $settings = AccountingSettings::query()->where('company_id', $this->companyA->id)->firstOrFail();
        $this->assertSame('DZD', $settings->currency);
        $this->assertIsArray($settings->number_series);

        // Documents dans des états variés (vitrine).
        $this->assertSame(1, AccountingDocument::query()->where('company_id', $this->companyA->id)
            ->where('type', DocumentType::Invoice->value)->where('status', DocumentStatus::Paid->value)->count());
        $this->assertSame(1, AccountingDocument::query()->where('company_id', $this->companyA->id)
            ->where('type', DocumentType::Invoice->value)->where('status', DocumentStatus::PartiallyPaid->value)->count());
        $this->assertSame(1, AccountingDocument::query()->where('company_id', $this->companyA->id)
            ->where('type', DocumentType::CreditNote->value)->count());

        // L'avoir est lié à sa facture source (règle du workflow).
        $creditNote = AccountingDocument::query()->where('company_id', $this->companyA->id)
            ->where('type', DocumentType::CreditNote->value)->firstOrFail();
        $this->assertNotNull($creditNote->source_document_id);

        // Numéros uniques par entreprise.
        $numbers = AccountingDocument::query()->where('company_id', $this->companyA->id)->pluck('number');
        $this->assertSame($numbers->count(), $numbers->unique()->count());

        // Facture payée : paiement complet rapproché (matched + reconciled_at).
        $paidInvoice = AccountingDocument::query()->where('company_id', $this->companyA->id)
            ->where('type', DocumentType::Invoice->value)->where('status', DocumentStatus::Paid->value)->firstOrFail();
        $this->assertEqualsWithDelta((float) $paidInvoice->total_ttc, (float) $paidInvoice->paid_amount, 0.001);
        $payment = $paidInvoice->payments()->firstOrFail();
        $this->assertSame('matched', $payment->status);
        $this->assertNotNull($payment->reconciled_at);

        // Idempotence : re-seed sans --force = no-op.
        $again = $seeder->seed($this->companyA);
        $this->assertFalse($again['seeded']);
        $this->assertSame('ALREADY_SEEDED', $again['status']);
        $this->assertSame(5, AccountingContact::query()->where('company_id', $this->companyA->id)->count());
        $this->assertSame(7, AccountingDocument::query()->where('company_id', $this->companyA->id)->count());
    }

    public function test_demo_seed_force_resets_only_demo_records(): void
    {
        $seeder = new SeedAccountingDemoData();
        $seeder->seed($this->companyA);

        // Donnée réelle créée AVANT le --force (jamais marquée demo).
        $real = AccountingContact::query()->create([
            'company_id' => $this->companyA->id,
            'type' => 'customer',
            'name' => 'Client Réel Non-Demo',
            'email' => 'reel@exemple.dz',
            'metadata' => ['real' => true],
        ]);

        $result = $seeder->seed($this->companyA, force: true);

        $this->assertTrue($result['seeded']);
        $this->assertSame('SEEDED', $result['status']);
        $this->assertSame(0, $result['skipped_documents'], 'Aucun document démo ne porte de paiement réel ici.');

        // Le contact réel survit ; la vitrine demo est recréée.
        $this->assertDatabaseHas('accounting_contacts', ['id' => $real->id, 'name' => 'Client Réel Non-Demo']);
        $this->assertSame(5, AccountingContact::query()->where('company_id', $this->companyA->id)
            ->where('id', '!=', $real->id)->count());
        $this->assertSame(7, AccountingDocument::query()->where('company_id', $this->companyA->id)->count());
        $this->assertSame(2, AccountingPayment::query()->where('company_id', $this->companyA->id)->count());
    }

    public function test_demo_seed_force_preserves_document_with_real_payment(): void
    {
        $seeder = new SeedAccountingDemoData();
        $seeder->seed($this->companyA);

        // Paiement RÉEL rattaché à une facture demo (scénario réaliste : un
        // encaissement client arrivé après le seed).
        $paidInvoice = AccountingDocument::query()->where('company_id', $this->companyA->id)
            ->where('type', DocumentType::Invoice->value)->where('status', DocumentStatus::Paid->value)->firstOrFail();
        AccountingPayment::query()->create([
            'company_id' => $this->companyA->id,
            'document_id' => $paidInvoice->id,
            'amount' => 1000.0,
            'method' => PaymentMethod::Cash->value,
            'reference' => 'ENCAISSEMENT-REEL-001',
            'received_at' => now(),
            'status' => 'recorded',
            'metadata' => ['real' => true],
        ]);

        $result = $seeder->seed($this->companyA, force: true);

        $this->assertTrue($result['seeded']);
        // Le document demo porteur du paiement réel est préservé, pas supprimé.
        $this->assertSame(1, $result['skipped_documents']);
        $this->assertDatabaseHas('accounting_documents', ['id' => $paidInvoice->id]);

        // Le paiement réel survit (jamais de perte de données réelles par cascade).
        $realPayments = AccountingPayment::query()->where('company_id', $this->companyA->id)->get()
            ->filter(static fn (AccountingPayment $payment): bool => ($payment->metadata['real'] ?? false) === true);
        $this->assertSame(1, $realPayments->count());
        $this->assertEqualsWithDelta(1000.0, (float) $realPayments->firstOrFail()->amount, 0.001);
    }

    public function test_demo_contacts_tax_id_is_encrypted_at_rest(): void
    {
        (new SeedAccountingDemoData())->seed($this->companyA);

        $raw = DB::table('accounting_contacts')
            ->where('company_id', $this->companyA->id)
            ->where('name', 'SARL Atlas Bâtiment')
            ->value('tax_id');

        $this->assertNotSame('000016001234567', $raw);

        $contact = AccountingContact::query()->where('company_id', $this->companyA->id)
            ->where('name', 'SARL Atlas Bâtiment')->firstOrFail();
        $this->assertSame('000016001234567', $contact->tax_id);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // US2 — E2E : contact → devis → facture → PDF → email → paiement → rapprochement
    // ─────────────────────────────────────────────────────────────────────────

    public function test_e2e_journey_contact_to_paid_invoice(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        // ── 1. Contact via l'API réelle ─────────────────────────────────────
        $response = $this->postJson('/api/v1/accounting/contacts', [
            'type' => 'customer',
            'name' => 'Client E2E Parcours',
            'email' => 'e2e@parcours.dz',
            'currency' => 'DZD',
        ]);
        $response->assertStatus(201);

        $contact = AccountingContact::query()->where('company_id', $this->companyA->id)
            ->where('name', 'Client E2E Parcours')->firstOrFail();

        // ── 2. Devis : numérotation + workflow réels ────────────────────────
        $quote = $this->createDraftDocument(DocumentType::Quote, $contact, now()->startOfDay()->subDays(10));
        $this->addLine($quote, 'Prestation E2E — lot pilote', 2.0, 75000.0);
        $this->recomputeTotals($quote, 19.0);
        $this->workflow->transition($quote, DocumentStatus::Sent);

        $this->assertSame(DocumentStatus::Sent->value, $quote->status);
        $this->assertStringStartsWith('DEV-', $quote->number);

        // ── 3. Facture issue du devis : envoyée + PDF + email (fakes) ───────
        $invoice = $this->createDraftDocument(DocumentType::Invoice, $contact, now()->startOfDay()->subDays(5), now()->startOfDay()->addDays(25));
        $this->addLine($invoice, 'Prestation E2E — livraison', 2.0, 75000.0);
        $this->addLine($invoice, 'Frais de mise en service', 1.0, 25000.0);
        $this->recomputeTotals($invoice, 19.0);
        $this->workflow->transition($invoice, DocumentStatus::Sent);

        // PDF : fake PdfRendererInterface (implémentation réelle #5224 en vol).
        $pdfPath = app(PdfRendererInterface::class)->render($invoice, 'fr');
        $this->assertIsString($pdfPath);
        $invoice->update([
            'pdf_path' => $pdfPath,
            'sent_at' => now(),
        ]);

        // Email : fake (envoi réel #5225 en vol) — rien ne part dans le test.
        Mail::assertNothingSent();

        $this->assertSame(DocumentStatus::Sent->value, $invoice->refresh()->status);
        $this->assertStringStartsWith('FAC-', $invoice->number);
        $this->assertNotNull($invoice->sent_at);
        $this->assertNotNull($invoice->pdf_path);

        // ── 4. Paiement partiel → partiellement payée (workflow réel) ───────
        $partialAmount = round((float) $invoice->total_ttc * 0.40, 2);
        $this->makePayment($invoice, $partialAmount, PaymentMethod::BankTransfer->value, 'VIR-E2E-0001', null);
        $this->workflow->transition($invoice, DocumentStatus::PartiallyPaid);

        $this->assertSame(DocumentStatus::PartiallyPaid->value, $invoice->status);

        // ── 5. Solde + rapprochement → payée ────────────────────────────────
        $restAmount = round((float) $invoice->total_ttc - $partialAmount, 2);
        $this->makePayment($invoice, $restAmount, PaymentMethod::BankTransfer->value, 'VIR-E2E-0002', now());
        $this->workflow->transition($invoice, DocumentStatus::Paid);

        $this->assertSame(DocumentStatus::Paid->value, $invoice->status);
        $this->assertEqualsWithDelta((float) $invoice->total_ttc, (float) $invoice->payments()->sum('amount'), 0.001);

        // Le solde est rapproché (matched + reconciled_at) — DoD rapprochement.
        // NB : `reference` est chiffrée au repos — on retrouve le paiement par
        // le statut (colonne non chiffrée) puis on vérifie la référence en clair.
        $matched = $invoice->payments()->where('status', 'matched')->firstOrFail();
        $this->assertSame('VIR-E2E-0002', $matched->reference);
        $this->assertNotNull($matched->reconciled_at);
        $this->assertDatabaseHas('accounting_payments', [
            'company_id' => $this->companyA->id,
            'document_id' => $invoice->id,
            'status' => 'matched',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // US2 (suite) — relance : refreshOverdue marque les impayés échus
    // ─────────────────────────────────────────────────────────────────────────

    public function test_overdue_relance_marks_past_due_invoices(): void
    {
        $seeder = new SeedAccountingDemoData();
        $seeder->seed($this->companyA);

        // La facture partielle de la vitrine demo (échéance J-5) est candidate.
        $demoPartialInvoice = AccountingDocument::query()
            ->where('company_id', $this->companyA->id)
            ->where('type', DocumentType::Invoice->value)
            ->where('status', DocumentStatus::PartiallyPaid->value)
            ->firstOrFail();

        $contact = AccountingContact::query()->where('company_id', $this->companyA->id)->firstOrFail();
        $invoice = $this->createDraftDocument(DocumentType::Invoice, $contact, now()->startOfDay()->subDays(30), now()->startOfDay()->subDays(5));
        $this->addLine($invoice, 'Prestation impayée', 1.0, 50000.0);
        $this->recomputeTotals($invoice, 19.0);
        $this->workflow->transition($invoice, DocumentStatus::Sent);

        $count = $this->workflow->refreshOverdue($this->companyA);

        // La facture du test est échue ; la facture partielle de la vitrine demo
        // (échéance J-5) l'est aussi → au moins 2 factures overdue.
        $this->assertGreaterThanOrEqual(2, $count);
        $this->assertSame(DocumentStatus::Overdue->value, $invoice->refresh()->status);
        $this->assertSame(DocumentStatus::Overdue->value, $demoPartialInvoice->refresh()->status);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // US3 — Démo exploitable en 1 clic (commande artisan) + isolation tenant
    // ─────────────────────────────────────────────────────────────────────────

    public function test_demo_seed_command_is_one_click_and_idempotent(): void
    {
        $exit = Artisan::call('accounting:demo-seed', ['company' => $this->companyA->slug]);
        $this->assertSame(Command::SUCCESS, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('Données de démonstration comptabilité créées', $output);

        // Re-run : no-op (idempotent), exit 0.
        $secondExit = Artisan::call('accounting:demo-seed', ['company' => $this->companyA->id]);
        $this->assertSame(Command::SUCCESS, $secondExit);
        $this->assertStringContainsString('ALREADY_SEEDED', Artisan::output());

        // Entreprise inconnue : échec explicite.
        $missingExit = Artisan::call('accounting:demo-seed', ['company' => 'aucune-entreprise']);
        $this->assertSame(Command::FAILURE, $missingExit);
        $this->assertStringContainsString('Entreprise introuvable', Artisan::output());
    }

    public function test_demo_data_is_tenant_isolated(): void
    {
        (new SeedAccountingDemoData())->seed($this->companyA);

        // L'entreprise B ne voit aucune donnée demo de A (API + modèles).
        Sanctum::actingAs($this->manager($this->companyB));
        $this->getJson('/api/v1/accounting/contacts')->assertOk()->assertJsonCount(0, 'data');

        $this->assertSame(0, AccountingDocument::query()->where('company_id', $this->companyB->id)->count());
        $this->assertSame(0, AccountingContact::query()->where('company_id', $this->companyB->id)->count());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function createDraftDocument(DocumentType $type, AccountingContact $contact, Carbon $issueDate, ?Carbon $dueDate = null): AccountingDocument
    {
        $numbering = new SequentialDocumentNumbering();

        /** @var AccountingDocument $document */
        $document = AccountingDocument::query()->create([
            'company_id' => $this->companyA->id,
            'type' => $type->value,
            'number' => $numbering->nextNumber($this->companyA->id, $type),
            'status' => DocumentStatus::Draft->value,
            'contact_id' => $contact->id,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'currency' => $contact->currency ?? $this->companyA->currency,
            'subtotal_ht' => 0,
            'tax_amount' => 0,
            'total_ttc' => 0,
        ]);

        return $document;
    }

    private function addLine(AccountingDocument $document, string $description, float $quantity, float $unitPrice): void
    {
        $sortOrder = (int) $document->lines()->count();

        AccountingDocumentLine::query()->create([
            'company_id' => $this->companyA->id,
            'document_id' => $document->id,
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount' => 0,
            'sort_order' => $sortOrder,
        ]);
    }

    private function recomputeTotals(AccountingDocument $document, float $tvaRate): void
    {
        $subtotal = 0.0;
        foreach ($document->lines()->get() as $line) {
            $subtotal += (float) $line->quantity * (float) $line->unit_price * (1 - (float) $line->discount / 100);
        }

        $tax = round($subtotal * $tvaRate / 100, 2);
        $total = round($subtotal + $tax, 2);

        $document->update([
            'subtotal_ht' => round($subtotal, 2),
            'tax_amount' => $tax,
            'total_ttc' => $total,
            'tva_rate' => $tvaRate,
        ]);
    }

    private function makePayment(AccountingDocument $document, float $amount, string $method, string $reference, ?Carbon $reconciledAt): AccountingPayment
    {
        /** @var AccountingPayment $payment */
        $payment = AccountingPayment::query()->create([
            'company_id' => $this->companyA->id,
            'document_id' => $document->id,
            'amount' => $amount,
            'method' => $method,
            'reference' => $reference,
            'received_at' => now(),
            'reconciled_at' => $reconciledAt,
            'status' => $reconciledAt !== null ? 'matched' : 'recorded',
        ]);

        $document->update(['paid_amount' => (float) $document->payments()->sum('amount')]);

        return $payment;
    }
}
