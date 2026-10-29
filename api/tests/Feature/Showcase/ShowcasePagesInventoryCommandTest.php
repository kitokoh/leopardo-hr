<?php

declare(strict_types=1);

namespace Tests\Feature\Showcase;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8727 — inventaire Showcase → Pages.
 *
 * Le lot livré est **lecture seule** : ces tests verrouillent (1) la justesse
 * des compteurs et des correspondances Site, (2) le fait que les cas ambigus
 * sont remontés comme bloquants au lieu d'être tranchés silencieusement, et
 * (3) l'absence totale d'écriture — c'est la garantie qui autorise à lancer la
 * commande sur une base vivante.
 */
class ShowcasePagesInventoryCommandTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(string $slug): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'slug' => $slug,
        ]);

        return $company;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function showcase(Company $company, array $overrides = []): int
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $overrides): int {
            return (int) DB::table('company_showcases')->insertGetId(array_merge([
                'company_id' => $company->id,
                'slug' => $company->slug,
                'status' => 'draft',
                'theme' => 'default',
                'created_at' => now(),
                'updated_at' => now(),
            ], $overrides));
        });
    }

    private function site(Company $company, string $name = 'Siège'): int
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $name): int {
            return (int) DB::table('sites')->insertGetId([
                'company_id' => $company->id,
                'name' => $name,
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function report(string $path): array
    {
        $this->assertFileExists($path);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) File::get($path), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    public function test_inventory_counts_sections_media_and_reports_a_ready_tenant(): void
    {
        $company = $this->company('inventory-ready');
        $showcaseId = $this->showcase($company, ['status' => 'published']);
        $this->site($company);

        app(TenantManager::class)->withinTenant($company, function () use ($company, $showcaseId): void {
            DB::table('company_showcase_sections')->insert([
                ['company_id' => $company->id, 'showcase_id' => $showcaseId, 'type' => 'hero', 'sort_order' => 0, 'schema_version' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['company_id' => $company->id, 'showcase_id' => $showcaseId, 'type' => 'gallery', 'sort_order' => 1, 'schema_version' => 1, 'created_at' => now(), 'updated_at' => now()],
            ]);

            DB::table('showcase_media')->insert([
                'company_id' => $company->id,
                'showcase_id' => $showcaseId,
                'kind' => 'logo',
                'original_name' => 'logo.png',
                'file_name' => 'logo.png',
                'mime_type' => 'image/png',
                'extension' => 'png',
                'size' => 2048,
                'disk' => 's3',
                'path' => 'showcase/logo.png',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $path = storage_path('app/testing-inventory-ready.json');

        $this->assertSame(0, Artisan::call('showcase:pages-inventory', ['--output' => $path, '--tenant' => 'inventory-ready']));

        $report = $this->report($path);
        $tenant = $report['tenants'][0];

        $this->assertSame('ready', $tenant['migration_state']);
        $this->assertSame([], $tenant['blocking']);
        $this->assertSame(2, $tenant['sections']['count']);
        $this->assertSame(['hero', 'gallery'], $tenant['sections']['types']);
        $this->assertSame(1, $tenant['media']['count']);
        $this->assertSame(2048, $tenant['media']['bytes']);
        $this->assertSame(1, $tenant['sites']['count']);
        $this->assertSame('published', $tenant['showcase']['status']);
        $this->assertSame(1, $report['totals']['published_showcases']);

        File::delete($path);
    }

    public function test_tenant_without_any_showcase_is_not_blocking(): void
    {
        $this->company('inventory-empty');
        $path = storage_path('app/testing-inventory-empty.json');

        $this->assertSame(0, Artisan::call('showcase:pages-inventory', ['--output' => $path, '--tenant' => 'inventory-empty']));

        $tenant = $this->report($path)['tenants'][0];
        $this->assertSame('no_showcase', $tenant['migration_state']);
        $this->assertSame([], $tenant['blocking']);
        $this->assertNull($tenant['showcase']);

        File::delete($path);
    }

    /**
     * Critère d'acceptation #8727 : « aucun choix silencieux si un tenant a zéro
     * ou plusieurs Sites possibles ». Le second cas est structurellement
     * insoluble (pages.site_id est UNIQUE), il doit donc BLOQUER.
     */
    public function test_tenant_with_several_sites_is_flagged_as_blocking_not_resolved(): void
    {
        $company = $this->company('inventory-multi-site');
        $this->showcase($company);
        $this->site($company, 'Siège');
        $this->site($company, 'Annexe');

        $path = storage_path('app/testing-inventory-multi.json');

        $this->assertSame(0, Artisan::call('showcase:pages-inventory', ['--output' => $path, '--tenant' => 'inventory-multi-site']));

        $report = $this->report($path);
        $tenant = $report['tenants'][0];

        $this->assertSame('ambiguous_sites', $tenant['migration_state']);
        $this->assertCount(2, $tenant['sites']['ids']);
        $this->assertNotSame([], $tenant['blocking']);
        $this->assertStringContainsString('décision humaine', $tenant['blocking'][0]);

        // Le rapport agrégé doit exposer le blocage explicitement.
        $this->assertCount(1, $report['summary']['blocking_tenants']);
        $this->assertSame('inventory-multi-site', $report['summary']['blocking_tenants'][0]['company_slug']);

        File::delete($path);
    }

    public function test_showcase_without_any_site_is_blocking(): void
    {
        $company = $this->company('inventory-no-site');
        $this->showcase($company);

        $path = storage_path('app/testing-inventory-nosite.json');

        $this->assertSame(0, Artisan::call('showcase:pages-inventory', ['--output' => $path, '--tenant' => 'inventory-no-site']));

        $tenant = $this->report($path)['tenants'][0];
        $this->assertSame('showcase_without_site', $tenant['migration_state']);
        $this->assertSame(0, $tenant['sites']['count']);
        $this->assertNotSame([], $tenant['blocking']);

        File::delete($path);
    }

    /**
     * La garantie qui autorise l'exécution sur une base vivante : un run
     * d'inventaire ne modifie AUCUNE ligne.
     */
    public function test_inventory_is_strictly_read_only(): void
    {
        $company = $this->company('inventory-readonly');
        $this->showcase($company, ['status' => 'published']);
        $this->site($company);

        $snapshot = fn (): array => app(TenantManager::class)->withinTenant($company, static fn (): array => [
            'showcase' => DB::table('company_showcases')->count(),
            'sites' => DB::table('sites')->count(),
            'pages' => DB::table('pages')->count(),
        ]);

        $before = $snapshot();
        $path = storage_path('app/testing-inventory-readonly.json');

        $this->assertSame(0, Artisan::call('showcase:pages-inventory', ['--output' => $path, '--tenant' => 'inventory-readonly']));

        $this->assertSame($before, $snapshot());
        $this->assertSame(0, $before['pages'], 'aucune Page ne doit être créée par un inventaire');

        File::delete($path);
    }

    public function test_unknown_tenant_slug_fails_loudly(): void
    {
        $this->assertSame(1, Artisan::call('showcase:pages-inventory', ['--tenant' => 'does-not-exist']));
    }
}
