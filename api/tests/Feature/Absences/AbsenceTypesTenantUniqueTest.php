<?php

declare(strict_types=1);

namespace Tests\Feature\Absences;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\HR\Infrastructure\Services\SectorTemplateService;
use App\Modules\Planning\Domain\Models\AbsenceType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5967 (R1, découverte DEP-BC02/#5878) — absence_types : unicitié du code
 * PAR TENANT (index composite `(company_id, code)`) au lieu de l'index
 * unique global sur `code` seul.
 *
 * Régressions verrouillées :
 *  - deux tenants peuvent créer le même code standard (CA, MAL, …) — les
 *    deux lignes existent réellement (sur main, le 2e insert est
 *    silencieusement ignoré par `insertOrIgnore`) ;
 *  - aucun doublon intra-tenant : re-créer le même code dans le même tenant
 *    lève une erreur d'unicité claire (23505 / 1062) ;
 *  - re-seed du même tenant → idempotent (toujours 1 ligne par code).
 */
class AbsenceTypesTenantUniqueTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $tenantA;

    private Company $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $tenantA */
        $tenantA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'sector' => 'standard']);
        /** @var Company $tenantB */
        $tenantB = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'sector' => 'btp']);
        $this->tenantA = $tenantA;
        $this->tenantB = $tenantB;
    }

    private function tenants(): TenantManager
    {
        return app(TenantManager::class);
    }

    public function test_two_tenants_can_have_the_same_standard_code(): void
    {
        $this->tenants()->withinTenant($this->tenantA, fn () => app(SectorTemplateService::class)->applyTemplate($this->tenantA));
        $this->tenants()->withinTenant($this->tenantB, fn () => app(SectorTemplateService::class)->applyTemplate($this->tenantB));

        // Les codes standards (CA, MAL, MAT, PAT, CSS) existent pour les DEUX tenants.
        foreach (['CA', 'MAL', 'MAT', 'PAT', 'CSS'] as $code) {
            self::assertSame(
                1,
                DB::table('absence_types')->where('company_id', $this->tenantA->id)->where('code', $code)->count(),
                "Code standard {$code} absent chez le tenant A.",
            );
            self::assertSame(
                1,
                DB::table('absence_types')->where('company_id', $this->tenantB->id)->where('code', $code)->count(),
                "Code standard {$code} absent chez le tenant B (bug R1 : insert ignoré).",
            );
        }

        // Le tenant BTP a en plus INT + CHOM.
        self::assertSame(1, DB::table('absence_types')->where('company_id', $this->tenantB->id)->where('code', 'INT')->count());
        self::assertSame(1, DB::table('absence_types')->where('company_id', $this->tenantB->id)->where('code', 'CHOM')->count());

        // Total : 5 types chez A, 7 chez B — aucun doublon, aucune perte.
        self::assertSame(5, DB::table('absence_types')->where('company_id', $this->tenantA->id)->count());
        self::assertSame(7, DB::table('absence_types')->where('company_id', $this->tenantB->id)->count());
    }

    public function test_duplicate_code_within_same_tenant_is_rejected(): void
    {
        $this->tenants()->withinTenant($this->tenantA, function (): void {
            AbsenceType::query()->create([
                'company_id' => $this->tenantA->id,
                'name' => 'Congé Annuel',
                'code' => 'CA',
                'is_paid' => true,
                'deducts_leave' => true,
                'requires_proof' => false,
            ]);
        });

        $this->expectException(QueryException::class);

        $this->tenants()->withinTenant($this->tenantA, function (): void {
            AbsenceType::query()->create([
                'company_id' => $this->tenantA->id,
                'name' => 'Congé Annuel (doublon)',
                'code' => 'CA',
                'is_paid' => true,
                'deducts_leave' => true,
                'requires_proof' => false,
            ]);
        });
    }

    public function test_reseeding_same_tenant_is_idempotent(): void
    {
        // Double application du template sur le même tenant → toujours 1 ligne.
        $this->tenants()->withinTenant($this->tenantA, function (): void {
            app(SectorTemplateService::class)->applyTemplate($this->tenantA);
            app(SectorTemplateService::class)->applyTemplate($this->tenantA);
        });

        self::assertSame(1, DB::table('absence_types')->where('company_id', $this->tenantA->id)->where('code', 'CA')->count());
        self::assertSame(5, DB::table('absence_types')->where('company_id', $this->tenantA->id)->count());
    }

    public function test_same_code_different_tenants_is_not_a_duplicate(): void
    {
        // CA chez A, puis CA chez B → aucune exception (uniciité par tenant).
        $this->tenants()->withinTenant($this->tenantA, function (): void {
            AbsenceType::query()->create([
                'company_id' => $this->tenantA->id,
                'name' => 'Congé Annuel',
                'code' => 'CA',
                'is_paid' => true,
                'deducts_leave' => true,
                'requires_proof' => false,
            ]);
        });

        $this->tenants()->withinTenant($this->tenantB, function (): void {
            AbsenceType::query()->create([
                'company_id' => $this->tenantB->id,
                'name' => 'Congé Annuel',
                'code' => 'CA',
                'is_paid' => true,
                'deducts_leave' => true,
                'requires_proof' => false,
            ]);
        });

        self::assertSame(1, DB::table('absence_types')->where('company_id', $this->tenantA->id)->where('code', 'CA')->count());
        self::assertSame(1, DB::table('absence_types')->where('company_id', $this->tenantB->id)->where('code', 'CA')->count());
    }
}
