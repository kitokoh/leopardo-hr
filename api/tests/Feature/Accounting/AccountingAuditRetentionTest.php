<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Application\Services\DocumentWorkflowService;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingDocumentLine;
use App\Modules\Accounting\Domain\Models\AccountingPayment;
use App\Modules\Accounting\Infrastructure\Services\AccountingRetentionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5273 — audit log + rétention RGPD + purge Comptabilité :
 * trail complet (qui/quoi/quand), consultation scope module (RBAC/tenant),
 * purge légale (finalisés, cutoff, dry-run, PDF) et chiffrement au repos.
 */
class AccountingAuditRetentionTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $this->employee = $employee;

        app()->instance('current_company', $company);
    }

    private function workflow(): DocumentWorkflowService
    {
        return app(DocumentWorkflowService::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => DocumentType::Invoice->value,
            'tva_rate' => 19.0,
            'lines' => [['description' => 'Prestation', 'quantity' => 1, 'unit_price' => 1000]],
        ], $overrides);
    }

    private function contact(): AccountingContact
    {
        return AccountingContact::create([
            'company_id' => $this->company->id,
            'type' => 'customer',
            'name' => 'Client audit DZ',
        ]);
    }

    // ── Audit trail (qui / quoi / quand) ─────────────────────────────────

    public function test_document_actions_write_full_audit_trail(): void
    {
        Sanctum::actingAs($this->manager);
        app()->instance('current_company', $this->company);

        $store = $this->postJson('/api/v1/accounting/documents', $this->payload([
            'contact_id' => $this->contact()->id,
        ]));
        $id = $store->json('data.id');
        $store->assertStatus(201);

        $this->postJson("/api/v1/accounting/documents/{$id}/send")->assertOk();
        $this->postJson("/api/v1/accounting/documents/{$id}/payments", [
            'amount' => 1000,
            'method' => 'bank_transfer',
        ])->assertStatus(201);
        $this->postJson("/api/v1/accounting/documents/{$id}/cancel")->assertOk();

        // 4 événements attendus : created, sent, payment, cancelled.
        $events = AuditLog::query()
            ->forCompany($this->company->id)
            ->where('metadata->resource', 'like', 'accounting.%')
            ->orderBy('id')
            ->get();

        $this->assertCount(4, $events);

        $resources = $events->pluck('metadata.resource')->all();
        $this->assertContains('accounting.document_created', $resources);
        $this->assertContains('accounting.document_sent', $resources);
        $this->assertContains('accounting.document_payment', $resources);
        $this->assertContains('accounting.document_cancelled', $resources);

        // Qui / quoi / quand : acteur + cible + horodatage.
        foreach ($events as $event) {
            $this->assertSame($this->manager->id, $event->user_id);
            $this->assertSame($this->company->id, $event->company_id);
            $this->assertNotNull($event->created_at);
            $this->assertSame(AccountingDocument::class, $event->auditable_type);
        }
    }

    public function test_audit_logs_endpoint_is_module_scoped_and_rbac_guarded(): void
    {
        Sanctum::actingAs($this->manager);
        app()->instance('current_company', $this->company);

        // Événement comptable + un événement HR non-comptable.
        $this->postJson('/api/v1/accounting/documents', $this->payload())->assertStatus(201);
        AuditLog::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->manager->id,
            'action' => 'sensitive_data_access',
            'metadata' => ['category' => 'sensitive_data_access', 'resource' => 'hr.payslip_viewed'],
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/accounting/audit-logs');
        $response->assertOk()
            ->assertJsonCount(1, 'data');

        // Filtre par ressource.
        $this->getJson('/api/v1/accounting/audit-logs?resource=accounting.document_created')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/accounting/audit-logs?resource=accounting.document_sent')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // RBAC : employé non-manager → 403.
        Sanctum::actingAs($this->employee);
        $this->getJson('/api/v1/accounting/audit-logs')->assertForbidden();
    }

    public function test_audit_logs_endpoint_is_tenant_isolated(): void
    {
        Sanctum::actingAs($this->manager);
        app()->instance('current_company', $this->company);
        $this->postJson('/api/v1/accounting/documents', $this->payload())->assertStatus(201);

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create();
        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $otherCompany->id]);

        Sanctum::actingAs($otherManager);
        app()->instance('current_company', $otherCompany);

        $this->getJson('/api/v1/accounting/audit-logs')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // ── Rétention + purge ────────────────────────────────────────────────

    public function test_purge_removes_only_finalized_old_documents_with_cascade(): void
    {
        Storage::fake('local');

        $oldPaid = $this->workflow()->createDraft($this->payload([
            'issue_date' => now()->subYears(12)->toDateString(),
        ]), (string) $this->company->id);
        $oldPaid->forceFill(['status' => DocumentStatus::Paid->value, 'pdf_path' => 'pdfs/old.pdf'])->save();
        Storage::disk('local')->put('pdfs/old.pdf', 'pdf-content');

        AccountingDocumentLine::create([
            'company_id' => $this->company->id,
            'document_id' => $oldPaid->id,
            'description' => 'ligne',
            'quantity' => 1,
            'unit_price' => 1,
            'sort_order' => 0,
        ]);
        AccountingPayment::create([
            'company_id' => $this->company->id,
            'document_id' => $oldPaid->id,
            'amount' => 1190,
            'method' => 'bank_transfer',
            'status' => 'recorded',
            'received_at' => now()->subYears(12),
        ]);

        // Récent finalisé + vieux non-finalisé → conservés.
        $recent = $this->workflow()->createDraft($this->payload(), (string) $this->company->id);
        $recent->forceFill(['status' => DocumentStatus::Paid->value])->save();

        $oldDraft = $this->workflow()->createDraft($this->payload([
            'issue_date' => now()->subYears(12)->toDateString(),
        ]), (string) $this->company->id);

        $purged = app(AccountingRetentionService::class)->purge(120);

        $this->assertCount(1, $purged);
        $this->assertSame($oldPaid->id, $purged[0]->id);
        $this->assertDatabaseMissing('accounting_documents', ['id' => $oldPaid->id]);
        $this->assertDatabaseMissing('accounting_document_lines', ['document_id' => $oldPaid->id]);
        $this->assertDatabaseMissing('accounting_payments', ['document_id' => $oldPaid->id]);
        $this->assertDatabaseHas('accounting_documents', ['id' => $recent->id]);
        $this->assertDatabaseHas('accounting_documents', ['id' => $oldDraft->id]);
        Storage::disk('local')->assertMissing('pdfs/old.pdf');
    }

    public function test_purge_command_dry_run_and_older_than(): void
    {
        $old = $this->workflow()->createDraft($this->payload([
            'issue_date' => now()->subYears(12)->toDateString(),
        ]), (string) $this->company->id);
        $old->forceFill(['status' => DocumentStatus::Paid->value])->save();

        // Dry-run : rien supprimé, rapport produit.
        $exit = Artisan::call('accounting:purge-expired', ['--dry-run' => true]);
        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('accounting_documents', ['id' => $old->id]);

        // --older-than court : le document (12 ans) est éligible.
        $exit = Artisan::call('accounting:purge-expired', ['--older-than' => 12]);
        $this->assertSame(0, $exit);
        $this->assertDatabaseMissing('accounting_documents', ['id' => $old->id]);
    }

    // ── Chiffrement au repos ─────────────────────────────────────────────

    public function test_sensitive_accounting_data_is_encrypted_at_rest(): void
    {
        $document = $this->workflow()->createDraft($this->payload([
            'metadata' => ['source_document_id' => 42],
        ]), (string) $this->company->id);

        $payment = AccountingPayment::create([
            'company_id' => $this->company->id,
            'document_id' => $document->id,
            'amount' => 100,
            'method' => 'check',
            'reference' => 'CHEQUE-2026-001',
            'status' => 'recorded',
        ]);

        $rawDocument = DB::table('accounting_documents')->where('id', $document->id)->value('metadata');
        $rawPayment = DB::table('accounting_payments')->where('id', $payment->id)->value('reference');

        $this->assertIsString($rawDocument);
        $this->assertStringContainsString('eyJpdiI6', $rawDocument, 'metadata chiffré attendu (enveloppe Laravel).');
        $this->assertStringContainsString('eyJpdiI6', (string) $rawPayment, 'reference chiffrée attendue (enveloppe Laravel).');
        $this->assertStringNotContainsString('CHEQUE-2026-001', (string) $rawPayment, 'pas de clair en base.');

        // Round-trip : le modèle déchiffre à la lecture.
        $this->assertSame('CHEQUE-2026-001', $payment->refresh()->reference);
        $this->assertSame(42, ($document->refresh()->metadata ?? [])['source_document_id'] ?? null);
    }
}
