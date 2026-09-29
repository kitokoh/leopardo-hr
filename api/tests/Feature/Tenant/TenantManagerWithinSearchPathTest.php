<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-019 (#8204) — contrat de `TenantManager::withinSearchPath()` : bascule
 * brute de `search_path` avec restauration garantie (try/finally), sans
 * toucher au contexte `current_company`.
 *
 * C'est l'API unique qui remplace les `SET search_path` manuels des surfaces
 * publiques (kiosk) et des vues portefeuille (plateforme) — complète le
 * verrouillage de `TenantManagerStandardizationTest` (#5736).
 */
class TenantManagerWithinSearchPathTest extends TestCase
{
    use RefreshTenantDatabase;

    private function manager(): TenantManager
    {
        return app(TenantManager::class);
    }

    /**
     * search_path normalisé (PostgreSQL rend `"shared_tenants", public` avec
     * guillemets/espaces selon la provenance) — on compare la substance.
     */
    private function searchPath(): string
    {
        $path = DB::scalar('SHOW search_path');

        return is_string($path) ? str_replace(['"', ' '], '', $path) : '';
    }

    public function test_within_search_path_executes_and_restores_after_success(): void
    {
        $before = $this->searchPath();
        $inside = null;

        $result = $this->manager()->withinSearchPath('public', function () use (&$inside): string {
            $inside = $this->searchPath();

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertSame('public', $inside);
        $this->assertSame($before, $this->searchPath());
    }

    public function test_within_search_path_restores_after_exception(): void
    {
        $before = $this->searchPath();

        $caught = null;
        try {
            $this->manager()->withinSearchPath('public', function (): void {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException $e) {
            $caught = $e->getMessage();
        }

        $this->assertSame('boom', $caught);

        // Fuite impossible : la requête suivante retrouve le chemin d'origine.
        $this->assertSame($before, $this->searchPath());
    }

    public function test_nested_within_search_path_restores_intermediate_path(): void
    {
        $before = $this->searchPath();
        $seen = [];

        $this->manager()->withinSearchPath('public', function () use (&$seen): void {
            $seen[] = $this->searchPath();

            $this->manager()->withinSearchPath('shared_tenants,public', function () use (&$seen): void {
                $seen[] = $this->searchPath();
            });

            // Après le scope imbriqué, on revient au chemin intermédiaire.
            $seen[] = $this->searchPath();
        });

        $this->assertSame(['public', 'shared_tenants,public', 'public'], $seen);
        $this->assertSame($before, $this->searchPath());
    }

    public function test_within_search_path_preserves_company_context(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        $manager = $this->manager();

        // Sans contexte : le scope brut n'en crée pas.
        $manager->withinSearchPath('public', function () use ($manager): void {
            $this->assertFalse($manager->hasTenant());
        });

        // Avec contexte : le scope brut n'y touche pas.
        $manager->withinTenant($company, function () use ($manager, $company): void {
            $manager->withinSearchPath('public', function () use ($manager, $company): void {
                $this->assertSame($company->id, $manager->current()?->id);
            });
            $this->assertSame($company->id, $manager->current()?->id);
        });

        $this->assertFalse($manager->hasTenant());
    }

    public function test_within_search_path_rejects_unsafe_path_before_any_sql(): void
    {
        $before = $this->searchPath();
        $executed = false;
        $rejected = false;

        try {
            $this->manager()->withinSearchPath('public; DROP TABLE companies', function () use (&$executed): void {
                $executed = true;
            });
        } catch (\InvalidArgumentException) {
            $rejected = true;
        }

        $this->assertTrue($rejected, 'Un search_path non sûr doit être refusé (fail-closed).');
        $this->assertFalse($executed, 'La closure ne doit jamais s\'exécuter avec un chemin non sûr.');
        $this->assertSame($before, $this->searchPath());
    }
}
