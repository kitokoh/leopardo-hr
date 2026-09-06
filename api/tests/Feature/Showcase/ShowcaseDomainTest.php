<?php

declare(strict_types=1);

namespace Tests\Feature\Showcase;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Showcase\Domain\Enums\ShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Policies\CompanyShowcasePolicy;
use App\Modules\Showcase\Domain\Support\ShowcaseFeatures;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-27 SHOWCASE (#6865) — socle domaine : migration tenant idempotente,
 * unicite `company_id` (une vitrine par tenant) + `slug` global, scope
 * tenant, feature flag `company_showcase` et RBAC deny-by-default (gestion
 * reservee principal/rh du tenant).
 */
class ShowcaseDomainTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $employeeA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;

        /** @var Employee $principalA */
        $principalA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        $this->principalA = $principalA;

        /** @var Employee $employeeA */
        $employeeA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        $this->employeeA = $employeeA;
    }

    private function showcase(Company $company, string $slug = 'acme-industries'): CompanyShowcase
    {
        /** @var CompanyShowcase $showcase */
        $showcase = CompanyShowcase::query()->create([
            'company_id' => $company->id,
            'slug' => $slug,
        ]);

        return $showcase;
    }

    public function test_company_showcases_table_exists_in_tenant_schema(): void
    {
        $this->assertTrue(Schema::hasTable('company_showcases'));

        $schema = DB::selectOne(
            'SELECT table_schema FROM information_schema.tables WHERE table_name = ? LIMIT 1',
            ['company_showcases']
        );
        $this->assertSame('shared_tenants', $schema->table_schema ?? null, 'company_showcases absente du schéma tenant');
    }

    public function test_showcase_is_created_with_company_id_and_defaults(): void
    {
        $showcase = $this->showcase($this->companyA);

        $this->assertSame($this->companyA->id, $showcase->company_id);
        $this->assertSame('acme-industries', $showcase->slug);
        $this->assertSame(ShowcaseStatus::Draft, $showcase->status);
        $this->assertSame('industry', $showcase->theme);
        $this->assertNull($showcase->settings);
        $this->assertNull($showcase->published_at);

        $nullCount = DB::table('company_showcases')->whereNull('company_id')->count();
        $this->assertSame(0, $nullCount);
    }

    public function test_a_company_can_have_only_one_showcase(): void
    {
        $this->showcase($this->companyA, 'acme-industries');
        $this->showcase($this->companyB, 'autre-tenant');

        // Une seconde vitrine pour le MÊME tenant → contrainte unique company_id.
        $this->expectException(QueryException::class);
        DB::transaction(function (): void {
            $this->showcase($this->companyA, 'acme-industries-2');
        });
    }

    public function test_slug_is_globally_unique(): void
    {
        $this->showcase($this->companyA, 'acme-industries');

        // Même slug chez un AUTRE tenant → refusé (URL publique stable globale).
        $this->expectException(QueryException::class);
        DB::transaction(function (): void {
            $this->showcase($this->companyB, 'acme-industries');
        });
    }

    public function test_tenant_scope_scopes_to_current_company(): void
    {
        $this->showcase($this->companyA);
        $this->showcase($this->companyB, 'autre-tenant');

        // Contexte tenant A → seule la vitrine de A est visible.
        app()->instance('current_company', $this->companyA);
        app()->instance('tenant_scope_required', true);

        $this->assertSame(1, CompanyShowcase::query()->count());

        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');
    }

    public function test_company_showcase_feature_flag_defaults_disabled_and_can_be_enabled(): void
    {
        $this->assertSame('company_showcase', ShowcaseFeatures::COMPANY_SHOWCASE);
        $this->assertFalse($this->companyA->hasFeature(ShowcaseFeatures::COMPANY_SHOWCASE));

        $this->companyA->setFeature(ShowcaseFeatures::COMPANY_SHOWCASE, true);
        $this->companyA->save();

        $fresh = Company::query()->findOrFail($this->companyA->id);
        $this->assertTrue($fresh->hasFeature(ShowcaseFeatures::COMPANY_SHOWCASE));
    }

    public function test_showcase_management_reserved_to_principal_of_same_tenant(): void
    {
        $showcase = $this->showcase($this->companyA);
        $policy = new CompanyShowcasePolicy;

        $this->assertTrue($policy->create($this->principalA));
        $this->assertTrue($policy->update($this->principalA, $showcase));
        $this->assertTrue($policy->view($this->principalA, $showcase));
        $this->assertTrue($policy->publish($this->principalA, $showcase));

        // Employé lambda : lecture autorisée, gestion refusée (deny-by-default).
        $this->assertTrue($policy->viewAny($this->employeeA));
        $this->assertFalse($policy->create($this->employeeA));
        $this->assertFalse($policy->update($this->employeeA, $showcase));
        $this->assertFalse($policy->delete($this->employeeA, $showcase));
        $this->assertFalse($policy->publish($this->employeeA, $showcase));
    }

    public function test_showcase_policy_denies_cross_tenant_management(): void
    {
        $showcaseA = $this->showcase($this->companyA);
        $showcaseB = $this->showcase($this->companyB, 'autre-tenant');
        $policy = new CompanyShowcasePolicy;

        // Le principal de A gère la vitrine de A…
        $this->assertTrue($policy->update($this->principalA, $showcaseA));
        $this->assertTrue($policy->publish($this->principalA, $showcaseA));

        // … mais pas celle de B (isolation tenant, 403/404 côté API plus tard).
        $this->assertFalse($policy->update($this->principalA, $showcaseB));
        $this->assertFalse($policy->delete($this->principalA, $showcaseB));

        // Lecture cross-tenant refusée également (scope company_id).
        $this->assertFalse($policy->view($this->principalA, $showcaseB));
    }
}
