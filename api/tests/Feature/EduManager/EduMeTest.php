<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduCampus;
use App\Modules\EduManager\Domain\Models\EduClass;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * EDU-011 (#5827) — profil rôle-aware : la navigation côté client est
 * pilotée par l'API (rôle + périmètre), jamais dupliquée.
 */
class EduMeTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_admin_role(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['edumanager' => true],
        ]);
        /** @var Employee $principal */
        $principal = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($principal);

        $this->getJson('/api/v1/edu-manager/me')
            ->assertOk()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.permissions.manage_all', true);
    }

    public function test_teacher_role_exposes_class_scope(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['edumanager' => true],
        ]);
        /** @var Employee $teacher */
        $teacher = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
        Sanctum::actingAs($teacher);

        [$campusId, $yearId] = app(\App\Core\Tenant\TenantManager::class)->withinTenant($company, function () use ($company): array {
            $campus = EduCampus::query()->create([
                'company_id' => $company->id,
                'code' => 'CMP-1',
                'name' => 'Campus',
            ]);
            $year = EduAcademicYear::query()->create([
                'company_id' => $company->id,
                'name' => '2025-2026',
                'start_date' => '2025-09-01',
                'end_date' => '2026-07-31',
                'status' => 'active',
            ]);

            return [$campus->id, $year->id];
        });

        EduClass::query()->create([
            'company_id' => $company->id,
            'campus_id' => $campusId,
            'academic_year_id' => $yearId,
            'code' => 'CL-3A',
            'name' => '3A',
            'teacher_id' => $teacher->id,
        ]);

        $this->getJson('/api/v1/edu-manager/me')
            ->assertOk()
            ->assertJsonPath('data.role', 'teacher')
            ->assertJsonPath('data.permissions.manage_own_classes', true)
            ->assertJsonCount(1, 'data.teacher.class_ids');
    }

    public function test_plain_employee_has_employee_role(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['edumanager' => true],
        ]);
        /** @var Employee $lambda */
        $lambda = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
        Sanctum::actingAs($lambda);

        $this->getJson('/api/v1/edu-manager/me')
            ->assertOk()
            ->assertJsonPath('data.role', 'employee');
    }

    public function test_solution_inactive_returns_403(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => [],
        ]);
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/edu-manager/me')
            ->assertStatus(403);
    }
}
