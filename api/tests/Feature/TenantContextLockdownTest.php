<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Exceptions\TenantContextMissingException;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Infrastructure\Services\TenantContextGuard;
use App\Core\Tenant\Infrastructure\Services\TenantEventDispatcher;
use App\Core\Tenant\TenantManager;
use App\Jobs\Middleware\EnsureTenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\Support\Fixtures\ProbeTenantEvent;
use Tests\Support\Fixtures\ProbeTenantJob;
use Tests\TestCase;

/**
 * #5706 (CRM-V0-02) — verrouillage du contexte tenant.
 *
 * Verrouille les conventions documentées dans
 * `docs/architecture/TENANT_CONTEXT_CONVENTIONS.md` :
 *
 *  1. HTTP : l'accès cross-tenant à une ressource d'un autre tenant retourne
 *     404 (jamais la ressource, jamais 403 — ne pas révéler l'existence).
 *  2. Jobs : `TenantScopedJob` + `EnsureTenantContext` établissent
 *     search_path + current_company avant handle() ; un job dont la compagnie
 *     n'existe pas n'est pas exécuté (release, pas de traitement orphelin).
 *  3. Events : `TenantScopedEvent` + `TenantEventDispatcher` — fail-closed
 *     sans tenant, dispatch dans le contexte de la compagnie sinon.
 *  4. Garde générique : `TenantContextGuard::assertHasTenant()` fail-closed.
 *
 * Le cache est déjà verrouillé par signature (companyId requis) — couvert par
 * `TenantCacheServiceTest`.
 */
class TenantContextLockdownTest extends TestCase
{
    use RefreshTenantDatabase;

    // ── HTTP : accès cross-tenant → 404 ──────────────────────────────────────

    public function test_cross_tenant_leave_balance_access_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        /** @var Employee $managerB */
        $managerB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);
        /** @var Employee $employeeA */
        $employeeA = Employee::factory()->create(['company_id' => $companyA->id]);

        Sanctum::actingAs($managerB);

        $this->getJson("/api/v1/employees/{$employeeA->id}/leave-balances?year=2026")
            ->assertNotFound();
    }

    // ── Jobs : contexte tenant obligatoire ───────────────────────────────────

    public function test_tenant_scoped_job_runs_within_company_context(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $job = new ProbeTenantJob($company->id);

        /** @var array{company_id: string|null, search_path: string}|null $context */
        $context = null;

        (new EnsureTenantContext)->handle($job, function () use (&$context): void {
            $context = [
                'company_id' => app(TenantManager::class)->current()?->id,
                'search_path' => (string) DB::scalar('SHOW search_path'),
            ];
        });

        $this->assertNotNull($context);
        $this->assertSame($company->id, $context['company_id']);
        $this->assertStringContainsString($company->schema_name, $context['search_path']);
    }

    public function test_tenant_scoped_job_with_missing_company_is_not_executed(): void
    {
        $job = new ProbeTenantJob('00000000-0000-0000-0000-000000000000');

        $executed = false;

        (new EnsureTenantContext)->handle($job, function () use (&$executed): void {
            $executed = true;
        });

        $this->assertFalse($executed);
    }

    // ── Events : contexte tenant obligatoire ─────────────────────────────────

    public function test_tenant_scoped_event_without_tenant_fails_closed(): void
    {
        $dispatcher = app(TenantEventDispatcher::class);

        $this->expectException(TenantContextMissingException::class);

        $dispatcher->dispatch(new ProbeTenantEvent('11111111-1111-1111-1111-111111111111'));
    }

    public function test_tenant_scoped_event_dispatches_within_company_context(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        /** @var array{event_company_id: string|null, current_company_id: string|null, search_path: string}|null $received */
        $received = null;

        Event::listen(ProbeTenantEvent::class, static function (ProbeTenantEvent $event) use (&$received): void {
            $received = [
                'event_company_id' => $event->tenantCompanyId(),
                'current_company_id' => app(TenantManager::class)->current()?->id,
                'search_path' => (string) DB::scalar('SHOW search_path'),
            ];
        });

        app(TenantEventDispatcher::class)->dispatch(new ProbeTenantEvent($company->id), $company);

        $this->assertNotNull($received);
        $this->assertSame($company->id, $received['event_company_id']);
        $this->assertSame($company->id, $received['current_company_id']);
        $this->assertStringContainsString($company->schema_name, $received['search_path']);
    }

    // ── Garde générique fail-closed ──────────────────────────────────────────

    public function test_tenant_context_guard_returns_current_company(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        app()->instance('current_company', $company);

        $guard = app(TenantContextGuard::class);

        $this->assertSame($company->id, $guard->assertHasTenant('test')->id);
    }

    public function test_tenant_context_guard_fails_closed_without_tenant(): void
    {
        app()->forgetInstance('current_company');

        $this->expectException(TenantContextMissingException::class);

        app(TenantContextGuard::class)->assertHasTenant('test');
    }
}
