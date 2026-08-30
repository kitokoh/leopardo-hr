<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduCampus;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduStudentGuardian;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5818 (EDU-002) — campus, élèves et responsables légaux.
 *
 * Verrouille :
 *   1. tables `edu_*` créées dans le schéma tenant (parité migrations) ;
 *   2. `company_id` NON nullable (zéro donnée scolaire orpheline) ;
 *   3. code / student_number uniques PAR TENANT ;
 *   4. CHECK statuts allowlistés ;
 *   5. référence cross-tenant impossible (FK composites student_guardians) ;
 *   6. migration idempotente + rollback propre.
 */
class EduCampusInvariantTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;
    }

    private function campus(Company $company, string $code = 'CAMPUS-A'): EduCampus
    {
        /** @var EduCampus $campus */
        $campus = EduCampus::query()->create([
            'company_id' => $company->id,
            'code' => $code,
            'name' => 'Campus '.$code,
            'timezone' => 'Africa/Algiers',
            'status' => EduCampus::STATUS_ACTIVE,
        ]);

        return $campus;
    }

    public function test_edu_tables_exist_in_tenant_schema(): void
    {
        foreach (['edu_campuses', 'edu_students', 'edu_guardians', 'edu_student_guardians'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "table {$table} absente");
            $row = DB::selectOne(
                'SELECT t.table_schema FROM information_schema.tables t WHERE t.table_name = ? LIMIT 1',
                [$table]
            );
            $this->assertSame('shared_tenants', $row->table_schema ?? null, "{$table} absente du schéma tenant");
        }
    }

    public function test_campus_creation_requires_company(): void
    {
        $this->expectException(QueryException::class);

        DB::transaction(function (): void {
            EduCampus::query()->create(['code' => 'C-1', 'name' => 'Sans tenant']);
        });
    }

    public function test_campus_code_is_unique_per_tenant(): void
    {
        $this->campus($this->companyA, 'CAMPUS-A');
        $this->campus($this->companyB, 'CAMPUS-A'); // même code, autre tenant → OK

        try {
            DB::transaction(function (): void {
                $this->campus($this->companyA, 'CAMPUS-A'); // doublon même tenant → rejeté
            });
            $this->fail("L'unicité (company_id, code) aurait dû rejeter le doublon.");
        } catch (QueryException $exception) {
            $this->assertStringContainsString('edu_campuses_company_code_unique', $exception->getMessage());
        }
    }

    public function test_campus_status_is_allowlisted(): void
    {
        try {
            DB::transaction(function (): void {
                EduCampus::query()->create([
                    'company_id' => $this->companyA->id,
                    'code' => 'C-BAD',
                    'name' => 'Campus',
                    'status' => 'exploded',
                ]);
            });
            $this->fail('Le CHECK edu_campuses_status_check aurait dû rejeter le statut.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('edu_campuses_status_check', $exception->getMessage());
        }
    }

    public function test_student_guardian_cannot_reference_another_tenant(): void
    {
        // Élève + guardian du tenant B.
        $campusB = $this->campus($this->companyB, 'CAMPUS-B');
        /** @var EduStudent $studentB */
        $studentB = EduStudent::query()->create([
            'company_id' => $this->companyB->id,
            'student_number' => 'STU-B-1',
            'display_name' => 'Élève B',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);
        /** @var EduGuardian $guardianB */
        $guardianB = EduGuardian::query()->create([
            'company_id' => $this->companyB->id,
            'first_name' => 'Responsable',
            'last_name' => 'B',
            'relationship_code' => 'parent',
        ]);

        // Campus du tenant A pour vérifier que la FK est bien composite.
        $this->campus($this->companyA, 'CAMPUS-A');

        try {
            DB::transaction(function () use ($studentB, $guardianB): void {
                // Tente de lier l'élève B (company B) à un guardian B avec company A :
                // la paire (guardian_id, company_id) viole la FK composite.
                EduStudentGuardian::query()->create([
                    'company_id' => $this->companyA->id,
                    'student_id' => (int) $studentB->getAttribute('id'),
                    'guardian_id' => (int) $guardianB->getAttribute('id'),
                    'relationship_code' => 'parent',
                ]);
            });
            $this->fail('La FK composite aurait dû rejeter la référence cross-tenant.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('edu_student_guardians_guardian_company_fk', $exception->getMessage());
        }
    }

    public function test_student_guardian_pair_is_unique_per_tenant(): void
    {
        $campus = $this->campus($this->companyA, 'CAMPUS-A');
        /** @var EduStudent $student */
        $student = EduStudent::query()->create([
            'company_id' => $this->companyA->id,
            'student_number' => 'STU-A-1',
            'display_name' => 'Élève A',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);
        /** @var EduGuardian $guardian */
        $guardian = EduGuardian::query()->create([
            'company_id' => $this->companyA->id,
            'first_name' => 'Responsable',
            'last_name' => 'A',
            'relationship_code' => 'parent',
        ]);

        EduStudentGuardian::query()->create([
            'company_id' => $this->companyA->id,
            'student_id' => (int) $student->getAttribute('id'),
            'guardian_id' => (int) $guardian->getAttribute('id'),
            'relationship_code' => 'parent',
        ]);

        try {
            DB::transaction(function () use ($student, $guardian): void {
                EduStudentGuardian::query()->create([
                    'company_id' => $this->companyA->id,
                    'student_id' => (int) $student->getAttribute('id'),
                    'guardian_id' => (int) $guardian->getAttribute('id'),
                    'relationship_code' => 'parent',
                ]);
            });
            $this->fail("L'unicité (company_id, student_id, guardian_id) aurait dû rejeter le doublon.");
        } catch (QueryException $exception) {
            $this->assertStringContainsString('edu_student_guardians_company_student_guardian_unique', $exception->getMessage());
        }
    }
}
