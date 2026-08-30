<?php

declare(strict_types=1);

namespace Tests\Unit\HR;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Infrastructure\Services\SectorTemplateService;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #5967 — régression : `absence_types_code_unique` était posé sur
 * `code` seul alors que la table vit dans le schéma partagé. Le premier
 * tenant à seeder ses codes standards (CA, MAL, MAT, PAT, CSS) "gagnait"
 * l'unicité globale ; `SectorTemplateService::seedAbsenceTypes()` voyait
 * tous ses `insertOrIgnore()` suivants silencieusement ignorés pour
 * n'importe quel autre tenant.
 */
class SectorTemplateSeedAbsenceTypesTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /** @test */
    public function two_tenants_can_seed_the_same_standard_absence_type_codes(): void
    {
        $companyA = Company::factory()->create(['sector' => 'standard']);
        $companyB = Company::factory()->create(['sector' => 'standard']);

        $service = new SectorTemplateService;

        // First tenant seeds successfully (this already worked before the fix).
        $service->applyTemplate($companyA);

        $countA = DB::table('absence_types')->where('company_id', $companyA->id)->count();
        $this->assertSame(5, $countA, 'Company A should have its 5 standard absence types.');

        // Second tenant seeds its own copy of the SAME standard codes (CA, MAL, MAT, PAT, CSS).
        // Before the fix (global unique index on `code` alone), this insertOrIgnore
        // silently no-oped for every row because company A already "owns" each code.
        $service->applyTemplate($companyB);

        $countB = DB::table('absence_types')->where('company_id', $companyB->id)->count();
        $this->assertSame(5, $countB, 'Company B must get its own 5 standard absence types — not silently skipped.');

        // Both companies have an absence type with code 'CA', each with its own row.
        $caRows = DB::table('absence_types')->where('code', 'CA')->get();
        $this->assertCount(2, $caRows, 'Two distinct companies must each have their own CA row.');
        $this->assertEqualsCanonicalizing(
            [$companyA->id, $companyB->id],
            $caRows->pluck('company_id')->all(),
        );
    }

    /** @test */
    public function duplicate_code_within_the_same_company_is_rejected(): void
    {
        $company = Company::factory()->create(['sector' => 'standard']);

        $service = new SectorTemplateService;
        $service->applyTemplate($company);

        // Re-inserting the same (company_id, code) pair must violate the
        // composite unique index — intra-tenant duplicates stay forbidden.
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('absence_types')->insert([
            'company_id' => $company->id,
            'name' => 'Congé Annuel (doublon)',
            'code' => 'CA',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
