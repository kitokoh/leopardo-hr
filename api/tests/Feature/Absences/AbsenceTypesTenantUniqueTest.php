<?php

declare(strict_types=1);

namespace Tests\Feature\Absences;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\HR\Infrastructure\Services\SectorTemplateService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5967 (BC-05 WORKFORCE) — absence_types.code : unicité par tenant.
 *
 * Régression multi-tenant : deux tenants peuvent créer le même code standard
 * (CA, MAL, …) — échec attendu sur main (index global), vert avec le fix.
 * Unicité intra-tenant conservée (erreur claire sur doublon).
 */
class AbsenceTypesTenantUniqueTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_two_tenants_can_seed_same_standard_code(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['sector' => 'services']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['sector' => 'services']);

        // Premier tenant : seed complet.
        app(TenantManager::class)->withinTenant($companyA, function () use ($companyA): void {
            app(SectorTemplateService::class)->applyTemplate($companyA);
        });

        $this->assertSame(1, DB::table('absence_types')
            ->where('company_id', $companyA->id)
            ->where('code', 'CA')
            ->count());

        // Deuxième tenant : le même code standard doit EXISTER (pas de
        // violation d'unicité globale avalée par insertOrIgnore).
        app(TenantManager::class)->withinTenant($companyB, function () use ($companyB): void {
            app(SectorTemplateService::class)->applyTemplate($companyB);
        });

        $this->assertSame(1, DB::table('absence_types')
            ->where('company_id', $companyB->id)
            ->where('code', 'CA')
            ->count());

        // Les deux lignes coexistent (régression : sur main, la seconde
        // insertion était silencieusement ignorée).
        $this->assertSame(2, DB::table('absence_types')
            ->where('code', 'CA')
            ->count());
    }

    public function test_duplicate_code_within_same_tenant_is_rejected(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['sector' => 'services']);

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            app(SectorTemplateService::class)->applyTemplate($company);
        });

        // Insertion directe du même code dans le même tenant → violation
        // d'unicité claire (composite company_id + code).
        $this->expectException(QueryException::class);

        DB::table('absence_types')->insert([
            'company_id' => $company->id,
            'name' => 'Doublon',
            'code' => 'CA',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);
    }
}
