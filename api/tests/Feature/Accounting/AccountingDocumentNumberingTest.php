<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Application\Services\DocumentWorkflowService;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5223 — numérotation des documents : séries paramétrables (défaut et
 * surcharge entreprise), idempotence, COURSE concurrente (23505 → retry,
 * pattern upsert #4978) et isolation tenant des compteurs.
 */
class AccountingDocumentNumberingTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;

        app()->instance('current_company', $company);
    }

    private function workflow(): DocumentWorkflowService
    {
        return app(DocumentWorkflowService::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => DocumentType::Invoice->value,
            'lines' => [['description' => 'Prestation', 'quantity' => 1, 'unit_price' => 1000]],
        ], $overrides);
    }

    public function test_default_series_sequences_without_duplicates(): void
    {
        $numbers = [];
        for ($i = 0; $i < 3; $i++) {
            $document = $this->workflow()->createDraft($this->payload(), (string) $this->company->id);
            $numbers[] = $document->number;
        }

        $expected = sprintf('FAC-%s-0001', now()->format('Y'));
        $this->assertSame([
            $expected,
            sprintf('FAC-%s-0002', now()->format('Y')),
            sprintf('FAC-%s-0003', now()->format('Y')),
        ], $numbers);
        $this->assertSame(3, AccountingDocument::query()->where('type', 'invoice')->distinct('number')->count('number'));
    }

    public function test_custom_series_from_settings(): void
    {
        AccountingSettings::create([
            'company_id' => $this->company->id,
            'number_series' => [
                'invoice' => ['prefix' => 'FAC', 'year' => true, 'pad' => 6],
                'quote' => ['prefix' => 'DEVIS', 'year' => false, 'pad' => 3],
            ],
        ]);

        $invoice = $this->workflow()->createDraft($this->payload(), (string) $this->company->id);
        $this->assertSame(sprintf('FAC-%s-000001', now()->format('Y')), $invoice->number);

        $quote = $this->workflow()->createDraft($this->payload([
            'type' => DocumentType::Quote->value,
        ]), (string) $this->company->id);
        $this->assertSame('DEVIS-001', $quote->number);
    }

    public function test_year_disabled_series(): void
    {
        AccountingSettings::create([
            'company_id' => $this->company->id,
            'number_series' => ['invoice' => ['prefix' => 'FAC', 'year' => false, 'pad' => 4]],
        ]);

        $first = $this->workflow()->createDraft($this->payload(), (string) $this->company->id);
        $this->assertSame('FAC-0001', $first->number);

        $second = $this->workflow()->createDraft($this->payload(), (string) $this->company->id);
        $this->assertSame('FAC-0002', $second->number);
    }

    public function test_concurrent_creation_never_duplicates_number(): void
    {
        // Course simulée (pattern #3726/#4978) : un document concurrent a déjà
        // pris FAC-2026-0001 avant notre appel → l'insert échoue en 23505 et le
        // service doit retenter avec le candidat suivant, jamais échouer.
        DB::table('accounting_documents')->insert([
            'company_id' => $this->company->id,
            'type' => DocumentType::Invoice->value,
            'number' => sprintf('FAC-%s-0001', now()->format('Y')),
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'subtotal_ht' => 0,
            'tax_amount' => 0,
            'total_ttc' => 0,
            'paid_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $document = $this->workflow()->createDraft($this->payload(), (string) $this->company->id);

        $this->assertSame(sprintf('FAC-%s-0002', now()->format('Y')), $document->number);

        // Pas de doublon en base sur (company_id, number).
        $this->assertSame(2, AccountingDocument::query()
            ->where('company_id', $this->company->id)
            ->where('type', DocumentType::Invoice->value)
            ->count());
    }

    public function test_counters_are_isolated_between_companies(): void
    {
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create();
        AccountingSettings::create([
            'company_id' => $otherCompany->id,
            'number_series' => ['invoice' => ['prefix' => 'FAC', 'year' => true, 'pad' => 4]],
        ]);

        $a1 = $this->workflow()->createDraft($this->payload(), (string) $this->company->id);
        $b1 = $this->workflow()->createDraft($this->payload(), (string) $otherCompany->id);
        $a2 = $this->workflow()->createDraft($this->payload(), (string) $this->company->id);

        $this->assertSame(sprintf('FAC-%s-0001', now()->format('Y')), $a1->number);
        $this->assertSame(sprintf('FAC-%s-0001', now()->format('Y')), $b1->number);
        $this->assertSame(sprintf('FAC-%s-0002', now()->format('Y')), $a2->number);
    }

    public function test_next_number_endpoint_previews_series(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/accounting/documents/next-number?type=invoice')
            ->assertOk()
            ->assertJsonPath('data.type', 'invoice')
            ->assertJsonPath('data.number', sprintf('FAC-%s-0001', now()->format('Y')));

        $this->getJson('/api/v1/accounting/documents/next-number?type=bogus')
            ->assertStatus(422);
    }
}
