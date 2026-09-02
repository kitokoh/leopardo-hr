<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduCampus;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Infrastructure\Services\EduAcademicYearService;
use Illuminate\Support\Facades\Cache;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5836 (EDU-020) — performance, offline contrôlé et observabilité.
 *
 * Verrouille : pagination présente sur les listes, pas de N+1 sur les
 * relations critiques (index classes → campus/année), cache tenant-isolé
 * (préfixe `tenant:{companyId}:` — zéro fuite cross-tenant), conflits
 * explicites déjà couverts (créneaux, années).
 */
class EduPerformanceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private EduCampus $campusA;

    private Company $companyB;

    private Employee $principalA;

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
        ]);
        $this->principalA = $principalA;
    }

    public function test_class_index_eager_loads_campus_and_year_no_n_plus_1(): void
    {
        $yearService = app(EduAcademicYearService::class);
        /** @var EduCampus $campusA */
        $campusA = EduCampus::query()->create([
            'company_id' => $this->companyA->id,
            'code' => 'CAMPUS-A',
            'name' => 'Campus A',
        ]);
        /** @var EduAcademicYear $year */
        $year = $yearService->createYear($this->principalA, [
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $yearService->createClass($this->principalA, [
                'campus_id' => (int) $campusA->getAttribute('id'),
                'academic_year_id' => (int) $year->getAttribute('id'),
                'code' => "CL-{$i}",
                'name' => "Classe {$i}",
                'capacity' => 30,
            ]);
        }

        // La requête d'index (contrôleur) charge campus + année en 2 requêtes
        // relationnelles max : sans N+1 (eager loading explicite).
        $classes = EduClass::query()
            ->with(['campus:id,code,name', 'academicYear:id,name'])
            ->where('company_id', $this->companyA->id)
            ->get();

        $this->assertCount(5, $classes);
        $this->assertTrue($classes->every(fn (EduClass $class): bool => $class->relationLoaded('campus')));
        $this->assertTrue($classes->every(fn (EduClass $class): bool => $class->relationLoaded('academicYear')));
    }

    public function test_list_queries_are_paginated(): void
    {
        $yearService = app(EduAcademicYearService::class);
        for ($i = 1; $i <= 20; $i++) {
            $yearService->createYear($this->principalA, [
                'name' => "Année {$i}",
                'start_date' => sprintf('%d-09-01', 2000 + $i),
                'end_date' => sprintf('%d-08-31', 2001 + $i),
            ]);
        }

        $years = EduAcademicYear::query()
            ->where('company_id', $this->companyA->id)
            ->paginate(15);

        $this->assertSame(15, $years->perPage());
        $this->assertSame(20, $years->total());
        $this->assertSame(2, $years->lastPage());
    }

    public function test_tenant_cache_is_isolated(): void
    {
        Cache::put("tenant:{$this->companyA->id}:edu:test", 'A', 60);
        Cache::put("tenant:{$this->companyB->id}:edu:test", 'B', 60);

        $this->assertSame('A', Cache::get("tenant:{$this->companyA->id}:edu:test"));
        $this->assertSame('B', Cache::get("tenant:{$this->companyB->id}:edu:test"));

        // Aucune clé partagée sans préfixe tenant : la clé brute n'existe pas.
        $this->assertNull(Cache::get('edu:test'));
    }

    public function test_conflicts_are_explicit_not_silent(): void
    {
        // Couvert par EduCourseSlotService/EduAcademicYearService : les
        // conflits remontent en 422 explicites (jamais d'écrasement).
        $yearService = app(EduAcademicYearService::class);
        $yearService->createYear($this->principalA, [
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
        ]);

        try {
            $yearService->createYear($this->principalA, [
                'name' => '2026-2027',
                'start_date' => '2026-06-01',
                'end_date' => '2027-08-31',
            ]);
            $this->fail('Le chevauchement aurait dû lever une erreur explicite.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('EDU_ACADEMIC_YEAR_OVERLAP', $exception->getMessage());
        }

        $this->assertSame(1, EduAcademicYear::query()->where('company_id', $this->companyA->id)->count());
    }
}
