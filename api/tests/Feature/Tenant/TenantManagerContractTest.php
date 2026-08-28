<?php

declare(strict_types=1);

use App\Contracts\Queue\TenantScopedJob;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Infrastructure\Services\TenantCacheService;
use App\Core\Tenant\TenantManager;
use App\Jobs\Middleware\EnsureTenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;

/**
 * MAT-004 (#5862) — Contrat global de TenantManager.
 *
 * Prouve, sur les cinq surfaces du contrat (HTTP, jobs, events, cache,
 * exports), que :
 *   1. toute requête tenant sans contexte échoue (fail-closed) ;
 *   2. le contexte (current_company + search_path) est restauré en `finally`
 *      après chaque utilisation de `withinTenant`, y compris sur exception ;
 *   3. aucune fuite de contexte entre tenants (clés de cache isolées, lectures
 *      scopées, listeners et jobs bornés au tenant courant).
 */

uses(CreatesMvpSchema::class);

beforeEach(function (): void {
    $this->setUpMvpSchema();
    $this->manager = app(TenantManager::class);
});

afterEach(function (): void {
    app()->forgetInstance('current_company');
    $this->tearDownMvpSchema();
});

function contractCompany(string $slug, string $email): Company
{
    return Company::factory()->create([
        'name' => ucfirst($slug),
        'slug' => $slug,
        'sector' => 'restaurant',
        'country' => 'DZ',
        'city' => 'Alger',
        'email' => $email,
        'status' => 'active',
    ]);
}

// ─── 1. withinTenant : restauration systématique (finally) ───────────────────

it('restaure la company précédente après withinTenant', function (): void {
    $companyA = contractCompany('tenant-a', 'a@contract.test');
    $companyB = contractCompany('tenant-b', 'b@contract.test');

    $this->manager->setTenant($companyA);

    $this->manager->withinTenant($companyB, function () use ($companyB): void {
        expect($this->manager->current()?->is($companyB))->toBeTrue();
    });

    expect($this->manager->current()?->is($companyA))->toBeTrue();
    expect($this->manager->hasTenant())->toBeTrue();
});

it('restaure le search_path PostgreSQL après withinTenant', function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL requis pour le search_path.');
    }

    $companyA = contractCompany('tenant-sp-a', 'spa@contract.test');
    $companyB = contractCompany('tenant-sp-b', 'spb@contract.test');

    $this->manager->setTenant($companyA);
    $pathBefore = DB::selectOne('SHOW search_path')->search_path;

    $this->manager->withinTenant($companyB, function () use ($companyB): void {
        expect(DB::selectOne('SHOW search_path')->search_path)
            ->toContain($companyB->getSafeSearchPath());
    });

    expect(DB::selectOne('SHOW search_path')->search_path)->toBe($pathBefore);
});

it('restaure le contexte dans finally quand la closure échoue', function (): void {
    $companyA = contractCompany('tenant-finally-a', 'fa@contract.test');
    $companyB = contractCompany('tenant-finally-b', 'fb@contract.test');

    $this->manager->setTenant($companyA);

    $thrown = false;
    try {
        $this->manager->withinTenant($companyB, function (): void {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        $thrown = true;
    }

    expect($thrown)->toBeTrue();
    expect($this->manager->current()?->is($companyA))->toBeTrue();
});

it('gère les withinTenant imbriqués sans fuite de contexte', function (): void {
    $companyA = contractCompany('tenant-nest-a', 'na@contract.test');
    $companyB = contractCompany('tenant-nest-b', 'nb@contract.test');
    $companyC = contractCompany('tenant-nest-c', 'nc@contract.test');

    $this->manager->setTenant($companyA);

    $this->manager->withinTenant($companyB, function () use ($companyB, $companyC): void {
        expect($this->manager->current()?->is($companyB))->toBeTrue();

        $this->manager->withinTenant($companyC, function () use ($companyC): void {
            expect($this->manager->current()?->is($companyC))->toBeTrue();
        });

        expect($this->manager->current()?->is($companyB))->toBeTrue();
    });

    expect($this->manager->current()?->is($companyA))->toBeTrue();
});

it('resetToPrevious restaure le contexte précédent', function (): void {
    $companyA = contractCompany('tenant-reset-a', 'ra@contract.test');
    $companyB = contractCompany('tenant-reset-b', 'rb@contract.test');

    $this->manager->setTenant($companyA);
    $this->manager->setTenant($companyB);

    $this->manager->resetToPrevious();

    expect($this->manager->current()?->is($companyA))->toBeTrue();
});

// ─── 2. HTTP : fail-closed et middleware tenant ──────────────────────────────

it('exige un contexte tenant sur les routes tenant (401 non authentifié)', function (): void {
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
});

it('échoue fermé quand un employé sans compagnie accède à une route tenant', function (): void {
    $orphan = Employee::factory()->create([
        'company_id' => null,
        'role' => 'ordinary',
        'status' => 'active',
    ]);

    Sanctum::actingAs($orphan);

    $this->getJson('/api/v1/notifications')
        ->assertStatus(403)
        ->assertJsonPath('error', 'TENANT_CONTEXT_MISSING');
});

it('isole les requêtes d un tenant par rapport à un autre (cross-tenant)', function (): void {
    $companyA = contractCompany('tenant-http-a', 'ha@contract.test');
    $companyB = contractCompany('tenant-http-b', 'hb@contract.test');

    $employeeA = Employee::factory()->create([
        'company_id' => $companyA->id,
        'role' => 'manager',
        'manager_role' => 'principal',
        'status' => 'active',
    ]);
    Employee::factory()->create([
        'company_id' => $companyB->id,
        'role' => 'employee',
        'status' => 'active',
    ]);

    Sanctum::actingAs($employeeA);

    // L'employé A ne voit que les employés de son tenant.
    $this->getJson('/api/v1/employees')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// ─── 3. Jobs : contexte établi puis restauré, y compris sur échec ────────────

it('établit puis restaure le contexte autour d un job TenantScopedJob', function (): void {
    $company = contractCompany('tenant-job-ok', 'jo@contract.test');
    $manager = $this->manager;

    $job = new TenantContractJob($company->id, $manager);
    $middleware = new EnsureTenantContext();

    $middleware->handle($job, static function (TenantContractJob $job): void {
        $job->run();
    });

    expect($job->seenCompanyId)->toBe($company->id);
    expect($manager->hasTenant())->toBeFalse();
});

it('restaure le contexte après un job qui échoue', function (): void {
    $company = contractCompany('tenant-job-ko', 'jk@contract.test');
    $manager = $this->manager;

    $job = new TenantContractJob($company->id, $manager);
    $middleware = new EnsureTenantContext();

    $thrown = false;
    try {
        $middleware->handle($job, static function (): void {
            throw new RuntimeException('job failed');
        });
    } catch (RuntimeException) {
        $thrown = true;
    }

    expect($thrown)->toBeTrue();
    expect($manager->hasTenant())->toBeFalse();
});

// ─── 4. Events : listeners bornés au tenant courant, contexte restauré ───────

it('expose le contexte tenant aux listeners puis le restaure', function (): void {
    $company = contractCompany('tenant-event', 'ev@contract.test');
    $seenCompanyId = null;

    Event::listen(TenantContractEvent::class, static function () use (&$seenCompanyId): void {
        $current = app()->bound('current_company') ? currentCompany() : null;
        $seenCompanyId = $current instanceof Company ? $current->id : null;
    });

    $this->manager->withinTenant($company, static function (): void {
        event(new TenantContractEvent());
    });

    expect($seenCompanyId)->toBe($company->id);
    expect($this->manager->hasTenant())->toBeFalse();
});

it('restaure le contexte même quand un listener échoue', function (): void {
    $companyA = contractCompany('tenant-ev-a', 'ea@contract.test');
    $companyB = contractCompany('tenant-ev-b', 'eb@contract.test');

    $this->manager->setTenant($companyA);

    Event::listen(TenantContractEvent::class, static function (): void {
        throw new RuntimeException('listener failed');
    });

    try {
        $this->manager->withinTenant($companyB, static function (): void {
            event(new TenantContractEvent());
        });
    } catch (RuntimeException) {
        // listener en échec — attendu
    }

    expect($this->manager->current()?->is($companyA))->toBeTrue();
});

// ─── 5. Cache : clés isolées par tenant, invalidation ciblée ────────────────

it('isole les clés de cache par tenant (TenantCacheService)', function (): void {
    $companyA = contractCompany('tenant-cache-a', 'ca@contract.test');
    $companyB = contractCompany('tenant-cache-b', 'cb@contract.test');
    $cache = app(TenantCacheService::class);

    $cache->put($companyA->id, 'shared:key', 'value-A');
    $cache->put($companyB->id, 'shared:key', 'value-B');

    expect($cache->get($companyA->id, 'shared:key'))->toBe('value-A');
    expect($cache->get($companyB->id, 'shared:key'))->toBe('value-B');
});

it('invalide uniquement le tenant ciblé', function (): void {
    $companyA = contractCompany('tenant-cache-a2', 'c2a@contract.test');
    $companyB = contractCompany('tenant-cache-b2', 'c2b@contract.test');
    $cache = app(TenantCacheService::class);

    $cache->put($companyA->id, 'shared:key', 'value-A');
    $cache->put($companyB->id, 'shared:key', 'value-B');

    $cache->forget($companyA->id, 'shared:key');

    expect($cache->get($companyA->id, 'shared:key'))->toBeNull();
    expect($cache->get($companyB->id, 'shared:key'))->toBe('value-B');
});

// ─── 6. Exports : opération tenant-scopée, données isolées, contexte restauré ─

it('une opération de type export ne lit que les données du tenant courant', function (): void {
    $companyA = contractCompany('tenant-exp-a', 'xa@contract.test');
    $companyB = contractCompany('tenant-exp-b', 'xb@contract.test');

    // Création hors contexte : company_id forcé explicitement (BelongsToCompany
    // ne surcharge que les company_id vides).
    Employee::factory()->count(2)->create(['company_id' => $companyA->id]);
    Employee::factory()->count(3)->create(['company_id' => $companyB->id]);

    $this->manager->setTenant($companyA);

    $exportedCount = null;
    $this->manager->withinTenant($companyB, function () use (&$exportedCount): void {
        // Simulation d'une lecture d'export : le scope global filtre sur le
        // tenant courant (search_path en mode schema, scope en mode shared).
        $exportedCount = Employee::query()->count();
    });

    expect($exportedCount)->toBe(3);
    expect($this->manager->current()?->is($companyA))->toBeTrue();
});

it('restaure le search_path après une opération de type export', function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL requis pour le search_path.');
    }

    $companyA = contractCompany('tenant-exp-sp-a', 'spa2@contract.test');
    $companyB = contractCompany('tenant-exp-sp-b', 'spb2@contract.test');

    $this->manager->setTenant($companyA);
    $pathBefore = DB::selectOne('SHOW search_path')->search_path;

    $this->manager->withinTenant($companyB, static function (): void {
        DB::select('SELECT 1');
    });

    expect(DB::selectOne('SHOW search_path')->search_path)->toBe($pathBefore);
});

// ─── Classes locales de contrat ──────────────────────────────────────────────

final class TenantContractJob implements TenantScopedJob
{
    public ?string $seenCompanyId = null;

    public function __construct(
        private readonly string $companyId,
        private readonly TenantManager $manager,
    ) {
    }

    public function tenantCompanyId(): ?string
    {
        return $this->companyId;
    }

    public function middleware(): array
    {
        return [new EnsureTenantContext()];
    }

    public function run(): void
    {
        $current = $this->manager->current();

        $this->seenCompanyId = $current instanceof Company ? $current->id : null;
    }
}

final class TenantContractEvent
{
}
