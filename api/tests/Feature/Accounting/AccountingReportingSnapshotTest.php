<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Application\Actions\AccountingReportingSnapshotService;
use App\Modules\Accounting\Application\Actions\SeedAccountingDemoData;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingReportingSnapshot;
use App\Modules\Accounting\Infrastructure\Services\PaymentRegistrationService;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-22-D10 (issue #6243) — snapshots horodatés des read models de reporting.
 *
 * - Recomputed idempotent : deux recomputes successifs → même payload, même
 *   version (exigence BC-22 « deux recalculs produisent le même résultat ») ;
 * - version incrémentée UNIQUEMENT quand le contenu change ;
 * - `refreshed_at` exposé à l'API (bloc `data.snapshot`) ;
 * - isolation cross-tenant (le snapshot de A n'est pas visible de B).
 */
class AccountingReportingSnapshotTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'timezone' => 'UTC']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD', 'timezone' => 'UTC']);
        $this->companyB = $companyB;

        (new SeedAccountingDemoData)->seed($companyA);
    }

    private function service(): AccountingReportingSnapshotService
    {
        return app(AccountingReportingSnapshotService::class);
    }

    public function test_recompute_is_idempotent_same_payload_same_version(): void
    {
        $service = $this->service();

        $first = $service->recompute((string) $this->companyA->id, 'accounting_dashboard');
        $second = $service->recompute((string) $this->companyA->id, 'accounting_dashboard');

        // Deux recomputes → même résultat ET même version (idempotence).
        // Égalité sémantique : jsonb normalise l'ordre des clés au stockage,
        // l'ordre d'insertion du premier payload diffère donc du rechargé.
        $this->assertEquals($first->payload, $second->payload);
        $this->assertSame($first->version, $second->version);
        $this->assertSame(1, $second->version);

        // Le payload est le read model du dashboard (agrégats, pas de PII).
        $this->assertArrayHasKey('invoices', $second->payload);
        $this->assertArrayHasKey('outstanding', $second->payload);
        $this->assertArrayHasKey('period', $second->payload);
    }

    public function test_version_increments_only_when_content_changes(): void
    {
        $service = $this->service();
        $service->recompute((string) $this->companyA->id, 'accounting_dashboard');

        // Nouvel encaissement sur la facture partielle → le contenu du read
        // model change (collections + impayés) sans toucher au seed.
        $partialInvoice = AccountingDocument::query()
            ->where('company_id', $this->companyA->id)
            ->where('type', 'invoice')
            ->where('status', 'partially_paid')
            ->firstOrFail();

        app(PaymentRegistrationService::class)
            ->register($partialInvoice, 100.0, 'cash');

        $recomputed = $service->recompute((string) $this->companyA->id, 'accounting_dashboard');

        $this->assertSame(2, $recomputed->version);

        // Nouveau recompute sans changement de données → version stable.
        $again = $service->recompute((string) $this->companyA->id, 'accounting_dashboard');
        $this->assertSame(2, $again->version);
    }

    public function test_unknown_report_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->recompute((string) $this->companyA->id, 'report_inconnu');
    }

    public function test_api_exposes_freshness_snapshot_block(): void
    {
        $manager = $this->manager($this->companyA);

        // Avant activation : source live.
        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/accounting/dashboard')
            ->assertOk()
            ->assertJsonPath('data.snapshot.source', 'live');

        // Après recompute : source snapshot + version + refreshed_at.
        $snapshot = $this->service()->recompute((string) $this->companyA->id, 'accounting_dashboard');

        $this->getJson('/api/v1/accounting/dashboard')
            ->assertOk()
            ->assertJsonPath('data.snapshot.source', 'snapshot')
            ->assertJsonPath('data.snapshot.report', 'accounting_dashboard')
            ->assertJsonPath('data.snapshot.version', $snapshot->version)
            ->assertJsonStructure(['data' => ['snapshot' => ['refreshed_at']]]);
    }

    public function test_snapshot_is_tenant_isolated(): void
    {
        $this->service()->recompute((string) $this->companyA->id, 'accounting_dashboard');

        // B ne voit ni le snapshot de A, ni sa fraîcheur.
        $this->assertNull($this->service()->latest((string) $this->companyB->id, 'accounting_dashboard'));
        $this->assertSame(['source' => 'live'], $this->service()->metadata((string) $this->companyB->id));

        $this->assertSame(1, AccountingReportingSnapshot::query()
            ->withoutGlobalScopes()
            ->where('company_id', $this->companyA->id)
            ->count());
        $this->assertSame(0, AccountingReportingSnapshot::query()
            ->withoutGlobalScopes()
            ->where('company_id', $this->companyB->id)
            ->count());
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'comptable',
            'status' => 'active',
        ]);

        return $manager;
    }
}
