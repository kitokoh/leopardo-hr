<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduTeacher;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Référentiel scolaire — EDU-003/009/010 (issues #5819, #5825, #5826).
 *
 * Couvre : CRUD années/classes/matières/enseignants réservé à la direction
 * (403 employé), lecture ouverte au tenant, validation stricte
 * (end_date >= start_date), isolation tenant 404, 401 sans auth.
 */
class EduReferentialApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        return $company;
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $manager;
    }

    private function employee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        return $employee;
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/edu/academic-years')->assertStatus(401);
        $this->postJson('/api/v1/edu/classes', [])->assertStatus(401);
    }

    public function test_manager_creates_academic_year_and_class(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        /** @var array<string, mixed> $year */
        $year = $this->postJson('/api/v1/edu/academic-years', [
            'code' => '2026-2027',
            'name' => 'Année 2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-15',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.code', '2026-2027')
            ->json('data');

        $this->postJson('/api/v1/edu/classes', [
            'academic_year_id' => $year['id'],
            'code' => 'CM1-A',
            'name' => 'CM1 Section A',
            'grade_level' => 'CM1',
            'capacity' => 30,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'CM1-A');

        $this->assertSame(1, EduAcademicYear::query()->where('company_id', $company->id)->count());
        $this->assertSame(1, EduClass::query()->where('company_id', $company->id)->count());
    }

    public function test_invalid_date_range_rejected(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        $this->postJson('/api/v1/edu/academic-years', [
            'code' => 'BAD',
            'name' => 'Période invalide',
            'start_date' => '2027-09-01',
            'end_date' => '2026-09-01',
        ])->assertStatus(422);
    }

    public function test_employee_cannot_create_referential(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->employee($company));

        $this->postJson('/api/v1/edu/academic-years', [
            'code' => 'X', 'name' => 'X', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        ])->assertStatus(403);

        $this->postJson('/api/v1/edu/classes', ['academic_year_id' => 1, 'code' => 'X', 'name' => 'X'])->assertStatus(403);
        $this->postJson('/api/v1/edu/subjects', ['code' => 'MATH', 'name' => 'Mathématiques'])->assertStatus(403);
        $this->postJson('/api/v1/edu/teachers', ['employee_id' => 1])->assertStatus(403);
    }

    public function test_employee_can_read_referential(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->manager($company));

        $this->postJson('/api/v1/edu/subjects', ['code' => 'MATH', 'name' => 'Mathématiques'])->assertStatus(201);

        Sanctum::actingAs($this->employee($company));

        $this->getJson('/api/v1/edu/subjects')->assertStatus(200)->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/edu/academic-years')->assertStatus(200);
        $this->getJson('/api/v1/edu/classes')->assertStatus(200);
    }

    public function test_teacher_linked_to_tenant_employee(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        Sanctum::actingAs($manager);

        $teacherEmployee = $this->employee($company);

        $this->postJson('/api/v1/edu/teachers', ['employee_id' => $teacherEmployee->id])
            ->assertStatus(201)
            ->assertJsonPath('data.employee_id', $teacherEmployee->id);

        $this->assertSame(1, EduTeacher::query()->where('company_id', $company->id)->count());
    }

    public function test_cross_tenant_referential_is_404(): void
    {
        $companyA = $this->company();
        Sanctum::actingAs($this->manager($companyA));

        /** @var EduAcademicYear $year */
        $year = EduAcademicYear::query()->create([
            'company_id' => $companyA->id,
            'code' => 'A-2026',
            'name' => 'Année A',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $companyB = $this->company();
        Sanctum::actingAs($this->manager($companyB));

        $this->putJson('/api/v1/edu/academic-years/'.$year->id, [
            'code' => 'X', 'name' => 'X', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        ])->assertStatus(404);
    }
}
