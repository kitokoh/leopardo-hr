<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Exceptions\TenantContextMissingException;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Infrastructure\Services\TenantCacheService;
use App\Core\Tenant\TenantManager;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5862 (MAT-004) — Contrat TenantManager global.
 *
 * Couvre TenantManager / current_company / search_path / withinTenant dans
 * HTTP, jobs (withinTenant), events, cache et exports (read models).
 *
 * Critères d'acceptation :
 *  - toute requête tenant sans contexte échoue ;
 *  - les tests prouvent l'absence de fuite (données écrites sous le bon
 *    company_id, cache scopé) et la restauration du contexte en finally.
 */
class TenantManagerContractTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        return $company;
    }

    private function searchPath(): string
    {
        $row = DB::selectOne('SHOW search_path');

        return $row->search_path ?? '';
    }

    private function makeManager(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $employee;
    }

    private function seedAbsence(Company $company, Employee $employee): Absence
    {
        $type = AbsenceType::query()->create([
            'company_id' => $company->id,
            'name' => 'Congé payé',
            'code' => 'CP-'.substr($company->id, 0, 8),
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);

        return Absence::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'days_count' => 3,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);
    }

    // ── HTTP ──────────────────────────────────────────────────────────────────

    public function test_http_tenant_request_without_context_fails(): void
    {
        // Aucun token : la requête tenant échoue (401 UNAUTHENTICATED).
        $this->getJson('/api/v1/me/balance')
            ->assertStatus(401);
    }

    public function test_http_tenant_request_resolves_and_restores_context(): void
    {
        $company = $this->makeCompany();
        $manager = $this->makeManager($company);

        Sanctum::actingAs($manager);

        // 200 = le contexte tenant a été résolu pendant la requête
        // (le solde est calculé pour la company de l'acteur).
        $this->getJson('/api/v1/me/balance')
            ->assertStatus(200);

        // Et le contexte est restauré après la requête (finally du
        // TenantMiddleware → resetToPrevious) : pas de fuite de contexte
        // entre requêtes.
        $this->assertFalse(app(TenantManager::class)->hasTenant());
    }

    // ── Jobs / traitements : withinTenant ─────────────────────────────────────

    public function test_within_tenant_restores_previous_context_and_search_path(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $manager = app(TenantManager::class);

        $manager->setTenant($companyA);
        $pathA = $this->searchPath();

        $seenInside = $manager->withinTenant($companyB, function () use ($companyB): string {
            $current = app(TenantManager::class)->current();
            $seenId = $current?->id;
            $this->assertSame($companyB->id, $seenId);

            return $seenId;
        });

        $this->assertSame($companyB->id, $seenInside);
        // Restauration du contexte précédent (company A) après le scope.
        $this->assertSame($companyA->id, $manager->current()?->id);
        // Restauration du search_path en finally.
        $this->assertSame($pathA, $this->searchPath());

        $manager->clearTenant();
        $this->assertFalse($manager->hasTenant());
        // clearTenant désactive le contexte et force search_path public
        // (contrat TenantManager — « sans restaurer »).
        $this->assertSame('public', $this->searchPath());
    }

    public function test_within_tenant_restores_context_even_on_exception(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $manager = app(TenantManager::class);

        $manager->setTenant($companyA);
        $pathA = $this->searchPath();

        try {
            $manager->withinTenant($companyB, function (): never {
                throw new \RuntimeException('boom tenant job');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('boom tenant job', $e->getMessage());
        }

        // Le finally a restauré company + search_path malgré l'exception.
        $this->assertSame($companyA->id, $manager->current()?->id);
        $this->assertSame($pathA, $this->searchPath());
    }

    // ── Events : le contexte est visible par les listeners et restauré ────────

    public function test_events_see_tenant_context_and_context_is_restored(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $manager = app(TenantManager::class);

        $manager->setTenant($companyA);

        $seen = null;
        $event = new class {};
        Event::listen(get_class($event), function () use (&$seen) {
            $seen = app(TenantManager::class)->current()?->id;
        });

        $manager->withinTenant($companyB, fn () => event($event));

        // Le listener a vu le contexte du tenant courant (B), pas A.
        $this->assertSame($companyB->id, $seen);
        // Et le contexte du caller (A) est restauré après le dispatch.
        $this->assertSame($companyA->id, $manager->current()?->id);
    }

    // ── Isolation des données : absence de fuite cross-tenant ─────────────────

    public function test_no_cross_tenant_leak_when_switching_tenants(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $employeeA = $this->makeManager($companyA);
        $manager = app(TenantManager::class);

        $manager->withinTenant($companyA, function () use ($companyA, $employeeA) {
            $this->seedAbsence($companyA, $employeeA);

            // La donnée est écrite côté A : visible uniquement dans le
            // contexte A (scope global company_id).
            $this->assertSame(1, Absence::query()->where('company_id', $companyA->id)->count());
        });

        // Côté tenant B : aucune donnée de A n'est visible (ni la sienne).
        $manager->withinTenant($companyB, function () use ($companyA, $companyB) {
            $this->assertSame(0, Absence::query()->where('company_id', $companyA->id)->count());
            $this->assertSame(0, Absence::query()->where('company_id', $companyB->id)->count());
        });

        // Contexte restauré après les scopes.
        $this->assertFalse($manager->hasTenant());
    }

    public function test_nested_within_tenant_restores_innermost_context(): void
    {
        // Cas unique apporté par la contribution parallèle (agent 5862) :
        // l'imbrication de scopes withinTenant ne doit jamais fuir.
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $companyC = $this->makeCompany();
        $manager = app(TenantManager::class);

        $manager->setTenant($companyA);
        $pathA = $this->searchPath();

        $manager->withinTenant($companyB, function () use ($companyB, $companyC, $manager): void {
            $currentB = $manager->current();
            $this->assertNotNull($currentB);
            $this->assertSame($companyB->id, $currentB->id);

            // Scope interne : C, puis retour à B.
            $manager->withinTenant($companyC, function () use ($companyC, $manager): void {
                $currentC = $manager->current();
                $this->assertNotNull($currentC);
                $this->assertSame($companyC->id, $currentC->id);
            });
            $currentAfter = $manager->current();
            $this->assertNotNull($currentAfter);
            $this->assertSame($companyB->id, $currentAfter->id);
        });

        // Après tous les scopes : retour au contexte initial A + search_path.
        $this->assertSame($companyA->id, $manager->current()?->id);
        $this->assertSame($pathA, $this->searchPath());
    }

    // ── Cache : clés scopées par tenant ───────────────────────────────────────

    public function test_cache_keys_are_tenant_scoped(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $cache = app(TenantCacheService::class);

        $cache->put($companyA->id, 'k', 'value-A');
        $cache->put($companyB->id, 'k', 'value-B');

        // Même clé logique, deux tenants : valeurs distinctes.
        $this->assertSame('value-A', $cache->get($companyA->id, 'k'));
        $this->assertSame('value-B', $cache->get($companyB->id, 'k'));

        // Le préfixe physique contient l'identité du tenant (pas de collision).
        $this->assertTrue(Cache::has("tenant:{$companyA->id}:k"));

        // L'invalidation chez A ne touche pas B.
        $cache->forget($companyA->id, 'k');
        $this->assertNull($cache->get($companyA->id, 'k'));
        $this->assertSame('value-B', $cache->get($companyB->id, 'k'));
    }

    // ── Exports / read models : contexte requis ───────────────────────────────

    public function test_tenant_scoped_query_without_context_fails_closed(): void
    {
        // Pattern exports/read models hors contexte : sur la surface tenant
        // (marqueur tenant_scope_required posé par TenantMiddleware), toute
        // requête sur un modèle BelongsToCompany sans compagnie courante
        // échoue (fail-closed) au lieu de fuir cross-tenant.
        $manager = app(TenantManager::class);
        $this->assertFalse($manager->hasTenant());

        app()->instance('tenant_scope_required', true);
        try {
            $this->expectException(TenantContextMissingException::class);
            Absence::query()->first();
        } finally {
            app()->forgetInstance('tenant_scope_required');
        }
    }
}
