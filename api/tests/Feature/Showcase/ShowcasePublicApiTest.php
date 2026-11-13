<?php

declare(strict_types=1);

namespace Tests\Feature\Showcase;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Infrastructure\Services\ShowcasePublicCache;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-27 SHOWCASE (#6867 — P0) — API publique de la vitrine.
 *
 * Accès public sans auth, 404 propre (brouillon / slug inconnu / société
 * suspendue), isolation cross-tenant par slug, ZÉRO donnée interne dans le
 * payload (test de non-fuite verrouillant la shape du DTO public) et cache
 * Redis invalidé à la mutation d'une vitrine publiée.
 */
class ShowcasePublicApiTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function company(string $slug, array $overrides = []): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(array_merge([
            'country' => 'DZ',
            'currency' => 'DZD',
            'slug' => $slug,
        ], $overrides));

        return $company;
    }

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function publishedShowcase(Company $company): CompanyShowcase
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company): CompanyShowcase {
            /** @var CompanyShowcase $showcase */
            $showcase = CompanyShowcase::query()->create([
                'company_id' => $company->id,
                'slug' => $company->slug,
                'status' => CompanyShowcaseStatus::Published,
                'theme' => 'default',
                'settings' => [
                    'colors' => ['primary' => '#0F172A'],
                    'brand_name' => 'Acme Industries',
                    'logo_id' => 42, // interne — ne doit JAMAIS sortir
                ],
                'published_at' => now(),
            ]);

            CompanyShowcaseSection::query()->create([
                'company_id' => $company->id,
                'showcase_id' => $showcase->id,
                'type' => 'hero',
                'content' => ['heading' => 'Acme Industries'],
                'sort_order' => 10,
            ]);
            CompanyShowcaseSection::query()->create([
                'company_id' => $company->id,
                'showcase_id' => $showcase->id,
                'type' => 'footer',
                'content' => ['text' => '© 2026 Acme'],
                'sort_order' => 20,
            ]);

            return $showcase;
        });
    }

    public function test_public_showcase_is_readable_without_auth(): void
    {
        $company = $this->company('acme-industries');
        $this->publishedShowcase($company);

        $this->getJson('/api/v1/public/vitrine/acme-industries')
            ->assertOk()
            ->assertJsonPath('data.slug', 'acme-industries')
            ->assertJsonPath('data.company_name', $company->name)
            ->assertJsonPath('data.theme', 'default')
            ->assertJsonPath('data.settings.brand_name', 'Acme Industries')
            // Allowlist `settings` : scalaires uniquement (logo_id, ids et
            // structures internes exclus). La surcharge de couleur du tenant
            // sort par le canal public dédié `theme_config.variables`
            // (ShowcaseThemeRegistry::resolvedVariables, V-THEMES #6868).
            ->assertJsonPath('data.theme_config.variables.primary', '#0F172A')
            ->assertJsonMissingPath('data.settings.logo_id')
            ->assertJsonMissingPath('data.settings.colors')
            ->assertJsonCount(2, 'data.sections')
            ->assertJsonPath('data.sections.0.type', 'hero')
            ->assertJsonPath('data.sections.1.type', 'footer');
    }

    public function test_public_payload_leaks_no_internal_field(): void
    {
        $company = $this->company('acme-no-leak');
        $this->publishedShowcase($company);

        $payload = $this->getJson('/api/v1/public/vitrine/acme-no-leak')->assertOk()->json('data');

        // Champs internes interdits au niveau racine du DTO.
        foreach (['id', 'company_id', 'status', 'custom_domain', 'created_at', 'updated_at', 'members', 'employees'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $payload, "Le DTO public ne doit pas exposer « {$forbidden} ».");
        }

        // Aucune donnée RH/tenant interne dans les sections non plus.
        foreach ($payload['sections'] as $section) {
            foreach (['id', 'showcase_id', 'company_id', 'sort_order', 'created_at', 'updated_at'] as $forbidden) {
                $this->assertArrayNotHasKey($forbidden, $section, "Une section publique ne doit pas exposer « {$forbidden} ».");
            }
            $this->assertSame(['type', 'schema_version', 'content'], array_keys($section));
        }
    }

    public function test_draft_showcase_is_never_public(): void
    {
        $company = $this->company('acme-draft');
        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            CompanyShowcase::query()->create([
                'company_id' => $company->id,
                'slug' => $company->slug,
                'status' => CompanyShowcaseStatus::Draft,
                'theme' => 'default',
            ]);
        });

        $this->getJson('/api/v1/public/vitrine/acme-draft')->assertStatus(404);
    }

    public function test_unknown_slug_and_suspended_company_return_404(): void
    {
        $this->getJson('/api/v1/public/vitrine/does-not-exist')->assertStatus(404);

        $suspended = $this->company('acme-suspended', ['status' => 'suspended']);
        $this->publishedShowcase($suspended);

        $this->getJson('/api/v1/public/vitrine/acme-suspended')->assertStatus(404);
    }

    public function test_public_route_is_isolated_per_company_slug(): void
    {
        $companyA = $this->company('tenant-a');
        $companyB = $this->company('tenant-b');
        $this->publishedShowcase($companyA);
        $this->publishedShowcase($companyB);

        // Le slug B ne peut pas exposer le contenu de A et réciproquement.
        $this->getJson('/api/v1/public/vitrine/tenant-a')
            ->assertOk()
            ->assertJsonPath('data.slug', 'tenant-a');
        $this->getJson('/api/v1/public/vitrine/tenant-b')
            ->assertOk()
            ->assertJsonPath('data.slug', 'tenant-b');
    }

    public function test_cache_is_used_and_invalidated_on_published_showcase_mutation(): void
    {
        $company = $this->company('acme-cache');
        // Le feature flag doit être posé AVANT la création de la vitrine et de
        // l'acteur : `withinTenant()` fige l'instance `current_company` lue
        // ensuite par le middleware `module.showcase` (fixture uniquement —
        // en production chaque requête recharge la société depuis la base).
        $company->setFeature('company_showcase', true);
        $company->save();
        $this->publishedShowcase($company);
        $this->principal($company);

        $key = ShowcasePublicCache::key('acme-cache');

        $this->getJson('/api/v1/public/vitrine/acme-cache?lang=fr')->assertOk();
        $this->assertTrue(Cache::has($key), 'Le DTO public doit être mis en cache après la première lecture.');

        // Mutation d'une section d'une vitrine publiée → invalidation immédiate.
        $sectionId = app(TenantManager::class)->withinTenant($company, function (): int {
            return CompanyShowcaseSection::query()->where('type', 'hero')->firstOrFail()->id;
        });

        $this->patchJson("/api/v1/showcase/sections/{$sectionId}", [
            'content' => ['heading' => 'Acme Industries — mise à jour'],
        ])->assertOk();

        $this->assertFalse(Cache::has($key), 'Le cache public doit être invalidé après mutation d\'une vitrine publiée.');

        // Relecture → nouvelle mise en cache avec le contenu à jour.
        $this->getJson('/api/v1/public/vitrine/acme-cache?lang=fr')
            ->assertOk()
            ->assertJsonPath('data.sections.0.content.heading', 'Acme Industries — mise à jour');
    }
}
