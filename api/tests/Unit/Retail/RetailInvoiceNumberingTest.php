<?php

declare(strict_types=1);

namespace Tests\Unit\Retail;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Application\Services\RetailInvoiceService;
use App\Modules\Retail\Domain\Models\RetailInvoiceSequence;
use App\Modules\Retail\Domain\Models\RetailOrder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-17 RETAIL (#7813) — numérotation légale des factures de vente.
 *
 * Vérifie le contrat du compteur `retail_invoice_sequences` : numéros
 * uniques et strictement croissants sans trou sous appels répétés (le
 * `lockForUpdate` sérialise les accès concurrents — la contention réelle
 * multi-connexions n'est pas simulable dans un test single-process, la
 * sérialisation par verrou de ligne est le mécanisme testé ici),
 * idempotence (numéro attribué une fois puis STABLE), compteur par
 * (tenant, année) indépendant, refus des commandes non complétées.
 */
class RetailInvoiceNumberingTest extends TestCase
{
    use RefreshTenantDatabase;

    private function service(): RetailInvoiceService
    {
        /** @var RetailInvoiceService $service */
        $service = $this->app->make(RetailInvoiceService::class);

        return $service;
    }

    private function makeCompletedOrder(Company $company, string $reference): RetailOrder
    {
        /** @var RetailOrder $order */
        $order = RetailOrder::query()->forceCreate([
            'company_id' => (string) $company->id,
            'location_id' => 1,
            'pos_session_id' => null,
            'reference' => $reference,
            'status' => 'completed',
            'subtotal_minor' => 1_000,
            'discount_minor' => 0,
            'total_minor' => 1_000,
            'currency' => 'XOF',
            'source' => 'pos',
            'version' => 1,
        ]);

        return $order;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_numbers_are_unique_and_gapless_across_repeated_assignments(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        $service = $this->service();
        $year = Carbon::now()->year;

        $numbers = [];

        foreach (range(1, 10) as $i) {
            $order = $this->makeCompletedOrder($company, 'POS-TEST-'.$i);
            $numbers[] = $service->ensureInvoiceNumber($order)->invoice_number;
        }

        // 10 numéros distincts, strictement croissants, sans trou.
        $this->assertCount(10, array_unique($numbers));

        foreach ($numbers as $index => $number) {
            $expected = sprintf('FAC-%d-%06d', $year, $index + 1);
            $this->assertSame($expected, $number);
        }

        // Le compteur pointe sur le prochain numéro.
        /** @var RetailInvoiceSequence $sequence */
        $sequence = RetailInvoiceSequence::query()
            ->where('company_id', (string) $company->id)
            ->where('year', $year)
            ->sole();
        $this->assertSame(11, $sequence->next_number);
    }

    public function test_assignment_is_idempotent_number_never_changes(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        $service = $this->service();

        $order = $this->makeCompletedOrder($company, 'POS-TEST-STABLE');

        $first = $service->ensureInvoiceNumber($order);
        $number = $first->invoice_number;
        $invoicedAt = $first->invoiced_at?->toIso8601String();
        $this->assertNotNull($number);

        $second = $service->ensureInvoiceNumber($first);
        $this->assertSame($number, $second->invoice_number);
        $this->assertSame($invoicedAt, $second->invoiced_at?->toIso8601String());

        // Rejeu depuis une instance FRAÎCHE (autre requête) : toujours stable.
        /** @var RetailOrder $fresh */
        $fresh = RetailOrder::query()
            ->where('company_id', (string) $company->id)
            ->findOrFail($order->getKey());
        $third = $service->ensureInvoiceNumber($fresh);
        $this->assertSame($number, $third->invoice_number);

        // Un seul incrément consommé.
        $sequence = RetailInvoiceSequence::query()
            ->where('company_id', (string) $company->id)
            ->sole();
        $this->assertSame(2, $sequence->next_number);
    }

    public function test_sequences_are_independent_per_tenant(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create();
        /** @var Company $companyB */
        $companyB = Company::factory()->create();
        $service = $this->service();
        $year = Carbon::now()->year;

        $a1 = $service->ensureInvoiceNumber($this->makeCompletedOrder($companyA, 'POS-A-1'));
        $a2 = $service->ensureInvoiceNumber($this->makeCompletedOrder($companyA, 'POS-A-2'));
        $b1 = $service->ensureInvoiceNumber($this->makeCompletedOrder($companyB, 'POS-B-1'));

        $this->assertSame(sprintf('FAC-%d-000001', $year), $a1->invoice_number);
        $this->assertSame(sprintf('FAC-%d-000002', $year), $a2->invoice_number);
        // Le tenant B a SON compteur : il repart à 1.
        $this->assertSame(sprintf('FAC-%d-000001', $year), $b1->invoice_number);
    }

    public function test_sequence_restarts_each_civil_year(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        $service = $this->service();

        Carbon::setTestNow(Carbon::create(2026, 12, 31, 23, 0, 0));
        $late = $service->ensureInvoiceNumber($this->makeCompletedOrder($company, 'POS-Y-2026'));
        $this->assertSame('FAC-2026-000001', $late->invoice_number);

        Carbon::setTestNow(Carbon::create(2027, 1, 1, 8, 0, 0));
        $early = $service->ensureInvoiceNumber($this->makeCompletedOrder($company, 'POS-Y-2027'));
        $this->assertSame('FAC-2027-000001', $early->invoice_number);

        // Deux compteurs coexistent (unique company_id + year).
        $this->assertSame(2, RetailInvoiceSequence::query()
            ->where('company_id', (string) $company->id)
            ->count());
    }

    public function test_non_completed_orders_are_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        $service = $this->service();

        /** @var RetailOrder $draft */
        $draft = RetailOrder::query()->forceCreate([
            'company_id' => (string) $company->id,
            'location_id' => 1,
            'reference' => 'POS-DRAFT-1',
            'status' => 'draft',
            'subtotal_minor' => 1_000,
            'discount_minor' => 0,
            'total_minor' => 1_000,
            'currency' => 'XOF',
            'source' => 'pos',
            'version' => 1,
        ]);

        $this->expectException(ValidationException::class);

        $service->ensureInvoiceNumber($draft);
    }

    public function test_existing_sequence_row_is_locked_and_incremented(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        $service = $this->service();
        $year = Carbon::now()->year;

        // Compteur pré-existant (ex. repris d'une migration de données).
        RetailInvoiceSequence::query()->forceCreate([
            'company_id' => (string) $company->id,
            'year' => $year,
            'next_number' => 42,
        ]);

        $order = $service->ensureInvoiceNumber($this->makeCompletedOrder($company, 'POS-SEQ-42'));

        $this->assertSame(sprintf('FAC-%d-000042', $year), $order->invoice_number);
        $this->assertSame(43, RetailInvoiceSequence::query()
            ->where('company_id', (string) $company->id)
            ->sole()->next_number);
    }
}
