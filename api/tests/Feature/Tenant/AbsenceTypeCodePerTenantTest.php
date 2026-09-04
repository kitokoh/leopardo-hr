<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Planning\Domain\Models\AbsenceType;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5967 (R1, découverte DEP-BC02 #5878) — les codes de types d'absence sont
 * standard par tenant (CA, MAL, MAT, PAT, CSS, INT, CHOM) et insérés via
 * `insertOrIgnore()`. L'ancien index UNIQUE global sur `code` faisait
 * silencieusement échouer (ignorer) les inserts de TOUS les tenants après le
 * premier → onboarding congés cassé.
 *
 * Le fix remplace l'unicité globale par une unicité composite
 * `(company_id, code)` : deux tenants peuvent désormais partager le même code
 * standard.
 */
class AbsenceTypeCodePerTenantTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_two_tenants_can_share_the_same_standard_absence_type_code(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $manager = app(TenantManager::class);

        // Seed du même code standard « CA » (Congé Annuel) chez les deux tenants,
        // via le même mécanisme insertOrIgnore que SectorTemplateService.
        $manager->withinTenant($companyA, function () use ($companyA): void {
            AbsenceType::query()->insertOrIgnore([
                'company_id' => $companyA->id,
                'name' => 'Congé Annuel',
                'code' => 'CA',
                'is_paid' => true,
                'deducts_leave' => true,
                'requires_proof' => false,
            ]);
        });

        $manager->withinTenant($companyB, function () use ($companyB): void {
            AbsenceType::query()->insertOrIgnore([
                'company_id' => $companyB->id,
                'name' => 'Congé Annuel',
                'code' => 'CA',
                'is_paid' => true,
                'deducts_leave' => true,
                'requires_proof' => false,
            ]);
        });

        // Le code standard « CA » existe pour les DEUX tenants (et une seule
        // fois chacun) — preuve que l'unicité est bien par (company_id, code).
        $manager->withinTenant($companyA, function () use ($companyA): void {
            $this->assertSame(1, AbsenceType::query()->where('company_id', $companyA->id)->where('code', 'CA')->count());
        });

        $manager->withinTenant($companyB, function () use ($companyB): void {
            $this->assertSame(1, AbsenceType::query()->where('company_id', $companyB->id)->where('code', 'CA')->count());
        });

        $total = DB::table('absence_types')
            ->where('code', 'CA')
            ->count();
        $this->assertSame(2, $total);
    }
}
