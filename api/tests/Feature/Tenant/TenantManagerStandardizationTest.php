<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Exceptions\TenantContextMissingException;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Infrastructure\Services\TenantCacheService;
use App\Core\Tenant\TenantManager;
use App\Jobs\Middleware\EnsureTenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\Support\Fixtures\TenantContextProbeJob;
use Tests\Support\Fixtures\TenantlessCrmProbeJob;
use Tests\TestCase;

/**
 * #5736 (CRM PRE) — Standardiser TenantManager dans routes, jobs, events et tests.
 *
 * Verrouille le contrat unique d'établissement et de restauration du tenant :
 *
 *  1. `withinTenant()` restaure contexte + `search_path` après succès ;
 *  2. `withinTenant()` restaure contexte + `search_path` après exception
 *     (`finally` — jamais de fuite de contexte) ;
 *  3. les scopes imbriqués restaurent vers l'état intermédiaire, pas vers
 *     l'état initial ;
 *  4. `setTenant()` / `resetToPrevious()` / `clearTenant()` sont cohérents ;
 *  5. un job CRM sans tenant est rejeté AVANT l'accès aux données
 *     (fail-closed `tenant_scope_required` → TenantContextMissingException) ;
 *  6. `EnsureTenantContext` restaure le contexte après le handle() ;
 *  7. les clés de cache contiennent le tenant (TenantCacheService) ;
 *  8. deux tenants simultanés ne peuvent pas se lire mutuellement.
 *
 * Conventions : docs/architecture/TENANT_RUNTIME_CONTRACT.md.
 * Complète le verrouillage HTTP/jobs/events de #5706 (TenantContextLockdownTest).
 */
class TenantManagerStandardizationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function manager(): TenantManager
    {
        return app(TenantManager::class);
    }

    private function searchPath(): string
    {
        $path = DB::scalar('SHOW search_path');

        return is_string($path) ? $path : '';
    }

    // ── 1/2. withinTenant : établissement + restauration ─────────────────────

    public function test_within_tenant_establishes_and_restores_context_after_success(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        $manager = $this->manager();
        $manager->clearTenant();

        $inside = null;
        $result = $manager->withinTenant($company, function () use (&$inside): string {
            $inside = [
                'company_id' => $this->manager()->current()?->id,
                'search_path' => $this->searchPath(),
            ];

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertSame($company->id, $inside['company_id']);
        $this->assertStringContainsString($company->schema_name, (string) $inside['search_path']);
        $this->assertStringContainsString('public', (string) $inside['search_path']);

        // Restauré après succès.
        $this->assertFalse($manager->hasTenant());
        $this->assertStringContainsString('shared_tenants', $this->searchPath());
    }

    public function test_within_tenant_restores_context_after_exception(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        $manager = $this->manager();
        $manager->clearTenant();

        try {
            $manager->withinTenant($company, function (): void {
                $this->assertTrue($this->manager()->hasTenant());

                throw new \RuntimeException('boom');
            });
            $this->fail('L’exception devait remonter.');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        // Le finally restaure contexte + search_path même sur exception.
        $this->assertFalse($manager->hasTenant());
        $this->assertStringContainsString('shared_tenants', $this->searchPath());
    }

    // ── 3. scopes imbriqués ──────────────────────────────────────────────────

    public function test_nested_within_tenant_restores_intermediate_context(): void
    {
        /** @var Company $a */
        $a = Company::factory()->create();
        /** @var Company $b */
        $b = Company::factory()->create();
        $manager = $this->manager();
        $manager->clearTenant();

        $seen = [];
        $manager->withinTenant($a, function () use ($manager, $b, &$seen): void {
            $seen[] = $manager->current()?->id;
            $manager->withinTenant($b, function () use ($manager, &$seen): void {
                $seen[] = $manager->current()?->id;
            });
            // Après le scope imbriqué, on revient sur A, pas sur l'état initial.
            $seen[] = $manager->current()?->id;
        });

        $this->assertSame([$a->id, $b->id, $a->id], $seen);
        $this->assertFalse($manager->hasTenant());
    }

    public function test_nested_within_tenant_restores_intermediate_context_after_exception(): void
    {
        /** @var Company $a */
        $a = Company::factory()->create();
        /** @var Company $b */
        $b = Company::factory()->create();
        $manager = $this->manager();
        $manager->clearTenant();

        $afterInner = null;
        try {
            $manager->withinTenant($a, function () use ($manager, $b, &$afterInner): void {
                try {
                    $manager->withinTenant($b, function (): void {
                        throw new \RuntimeException('inner boom');
                    });
                } catch (\RuntimeException) {
                    // swallow
                }
                $afterInner = $manager->current()?->id;
            });
        } catch (\RuntimeException) {
            $this->fail('Le finally du scope imbriqué a déjà consommé l’exception.');
        }

        $this->assertSame($a->id, $afterInner);
        $this->assertFalse($manager->hasTenant());
    }

    // ── 4. setTenant / resetToPrevious / clearTenant ─────────────────────────

    public function test_set_tenant_and_reset_to_previous_restore_company_and_search_path(): void
    {
        /** @var Company $a */
        $a = Company::factory()->create();
        /** @var Company $b */
        $b = Company::factory()->create();
        $manager = $this->manager();
        $manager->clearTenant();

        $manager->setTenant($a);
        $this->assertSame($a->id, $manager->current()?->id);

        $manager->setTenant($b);
        $this->assertSame($b->id, $manager->current()?->id);

        $manager->resetToPrevious();
        $this->assertSame($a->id, $manager->current()?->id);

        $manager->resetToPrevious();
        $this->assertFalse($manager->hasTenant());
        $this->assertStringContainsString('shared_tenants', $this->searchPath());
    }

    public function test_clear_tenant_removes_context(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        $manager = $this->manager();

        $manager->setTenant($company);
        $this->assertTrue($manager->hasTenant());

        $manager->clearTenant();
        $this->assertFalse($manager->hasTenant());
        $this->assertNull($manager->current());
        $this->assertStringContainsString('shared_tenants', $this->searchPath());
    }

    // ── 5. job CRM sans tenant rejeté avant accès aux données ────────────────

    public function test_tenantless_crm_job_is_rejected_before_data_access(): void
    {
        app()->instance('tenant_scope_required', true);

        try {
            (new TenantlessCrmProbeJob)->handle();
            $this->fail('Le job CRM sans tenant aurait dû être rejeté.');
        } catch (TenantContextMissingException) {
            // Attendu : rejet fail-closed AVANT qu'aucune ligne ne soit lue.
        } finally {
            app()->forgetInstance('tenant_scope_required');
        }
    }

    // ── 6. EnsureTenantContext restaure après le handle ──────────────────────

    public function test_ensure_tenant_context_restores_after_handle(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        $manager = $this->manager();
        $manager->clearTenant();

        $context = null;
        (new EnsureTenantContext)->handle(new TenantContextProbeJob($company->id), function () use (&$context): void {
            $context = [
                'company_id' => $this->manager()->current()?->id,
                'search_path' => $this->searchPath(),
            ];
        });

        $this->assertNotNull($context);
        $this->assertSame($company->id, $context['company_id']);
        $this->assertStringContainsString($company->schema_name, (string) $context['search_path']);

        // Restauré après le middleware (fin de job) : aucun contexte résiduel.
        $this->assertFalse($manager->hasTenant());
    }

    // ── 7. clés de cache tenant-scopées ──────────────────────────────────────

    public function test_cache_keys_contain_tenant_company_id(): void
    {
        /** @var Company $a */
        $a = Company::factory()->create();
        /** @var Company $b */
        $b = Company::factory()->create();
        $service = app(TenantCacheService::class);

        $service->put($a->id, 'crm:summary', 'data-A');
        $service->put($b->id, 'crm:summary', 'data-B');

        // Même clé logique, valeurs distinctes par tenant.
        $this->assertSame('data-A', $service->get($a->id, 'crm:summary'));
        $this->assertSame('data-B', $service->get($b->id, 'crm:summary'));

        // Les clés réelles embarquent le tenant : aucune collision possible.
        $this->assertTrue(Cache::has("tenant:{$a->id}:crm:summary"));
        $this->assertTrue(Cache::has("tenant:{$b->id}:crm:summary"));
        $this->assertNotSame(
            Cache::get("tenant:{$a->id}:crm:summary"),
            Cache::get("tenant:{$b->id}:crm:summary"),
        );
    }

    // ── 8. deux tenants simultanés ne se lisent pas mutuellement ─────────────

    public function test_two_tenants_do_not_read_each_other_data(): void
    {
        /** @var Company $a */
        $a = Company::factory()->create();
        /** @var Company $b */
        $b = Company::factory()->create();
        $manager = $this->manager();

        $manager->withinTenant($a, function () use ($a): void {
            Employee::factory()->create([
                'first_name' => 'Alice',
                'last_name' => 'TenantA',
                'company_id' => $a->id,
            ]);
        });
        $manager->withinTenant($b, function () use ($b): void {
            Employee::factory()->create([
                'first_name' => 'Bob',
                'last_name' => 'TenantB',
                'company_id' => $b->id,
            ]);
        });

        $manager->withinTenant($a, function (): void {
            $this->assertSame(1, Employee::query()->count());
            $this->assertTrue(Employee::query()->where('first_name', 'Alice')->exists());
            $this->assertFalse(Employee::query()->where('first_name', 'Bob')->exists());
        });

        $manager->withinTenant($b, function (): void {
            $this->assertSame(1, Employee::query()->count());
            $this->assertTrue(Employee::query()->where('first_name', 'Bob')->exists());
            $this->assertFalse(Employee::query()->where('first_name', 'Alice')->exists());
        });

        // Pas de contexte résiduel après les scopes.
        $this->assertFalse($manager->hasTenant());
    }
}
