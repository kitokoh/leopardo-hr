<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduStudent;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issues #5827/#5828 (EDU-011/EDU-012) — tableau de bord administration et
 * espace enseignant.
 *
 * Verrouille : navigation rôle-aware (direction = dashboard complet,
 * enseignant = SES classes uniquement, lambda = 403), compteurs, isolation
 * cross-tenant.
 */
class EduDashboardTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $teacherA;

    private Employee $lambdaA;

    private EduClass $ownClass;

    private EduClass $otherClass;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['edumanager' => true],
        ]);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['edumanager' => true],
        ]);
        $this->companyB = $companyB;

        /** @var Employee $principalA */
        $principalA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principalA = $principalA;

        /** @var Employee $teacherA */
        $teacherA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->teacherA = $teacherA;

        /** @var Employee $lambdaA */
        $lambdaA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->lambdaA = $lambdaA;

        /** @var EduAcademicYear $yearA */
        $yearA = EduAcademicYear::query()->create([
            'company_id' => $companyA->id,
            'name' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => EduAcademicYear::STATUS_ACTIVE,
        ]);
        $yearId = (int) $yearA->getAttribute('id');

        /** @var EduClass $ownClass */
        $ownClass = EduClass::query()->create([
            'company_id' => $companyA->id,
            'academic_year_id' => $yearId,
            'code' => 'CP-A',
            'name' => 'CP A',
            'teacher_id' => (int) $teacherA->getAttribute('id'),
            'status' => EduClass::STATUS_ACTIVE,
        ]);
        $this->ownClass = $ownClass;

        /** @var EduClass $otherClass */
        $otherClass = EduClass::query()->create([
            'company_id' => $companyA->id,
            'academic_year_id' => $yearId,
            'code' => 'CE1-B',
            'name' => 'CE1 B',
            'status' => EduClass::STATUS_ACTIVE,
        ]);
        $this->otherClass = $otherClass;

        EduStudent::query()->create([
            'company_id' => $companyA->id,
            'student_number' => 'STU-0001',
            'display_name' => 'Lina Benali',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);
    }

    private function baseUrl(): string
    {
        return '/api/v1/edu-manager';
    }

    public function test_dashboard_is_admin_only_with_counts(): void
    {
        // Lambda : 403.
        Sanctum::actingAs($this->lambdaA);
        $this->getJson($this->baseUrl().'/dashboard')->assertStatus(403);

        // Direction : dashboard complet.
        Sanctum::actingAs($this->principalA);
        $this->getJson($this->baseUrl().'/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.navigation.0.key', 'campuses')
            ->assertJsonPath('data.navigation.2.count', 2) // classes
            ->assertJsonPath('data.navigation.3.count', 1); // élèves
    }

    public function test_teacher_workspace_is_scoped_to_own_classes(): void
    {
        // Lambda : 403.
        Sanctum::actingAs($this->lambdaA);
        $this->getJson($this->baseUrl().'/teacher/workspace')->assertStatus(403);

        // Enseignant : uniquement SES classes (référente), jamais l'autre.
        Sanctum::actingAs($this->teacherA);
        $response = $this->getJson($this->baseUrl().'/teacher/workspace')
            ->assertOk()
            ->assertJsonPath('data.role', 'teacher');

        $classIds = collect($response->json('data.classes'))->pluck('id')->all();
        $this->assertContains((int) $this->ownClass->getAttribute('id'), $classIds);
        $this->assertNotContains((int) $this->otherClass->getAttribute('id'), $classIds);
    }

    public function test_dashboard_is_tenant_isolated(): void
    {
        /** @var Employee $principalB */
        $principalB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($principalB);

        $this->getJson($this->baseUrl().'/dashboard')
            ->assertOk()
            ->assertJsonPath('data.navigation.2.count', 0);
    }
}
