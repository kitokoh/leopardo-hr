<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduAcademicYear;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduFeeCharge;
use App\Modules\EduManager\Domain\Models\EduFeeType;
use App\Modules\EduManager\Domain\Models\EduStudent;
use Illuminate\Support\Facades\DB;
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

    /**
     * Compte les requêtes émises par UN appel au dashboard (#7985).
     *
     * Mesure par le journal de requêtes de la connexion
     * (`flushQueryLog()` + `enableQueryLog()`) et non `DB::listen()` : un
     * écouteur n'est jamais retiré et fausserait la mesure suivante
     * (leçon #7339, `PlatformCompanyHealthApiTest`).
     */
    private function dashboardQueryCount(): int
    {
        return count($this->dashboardQueries());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dashboardQueries(): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson($this->baseUrl().'/dashboard')->assertOk();

        /** @var list<array<string, mixed>> $log */
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        return $log;
    }

    /**
     * Amorce des dossiers d'admission de la société A, statuts alternés
     * (`pending`/`accepted`) pour prouver le `group by status` SQL (#7985).
     */
    private function seedAdmissions(int $count): void
    {
        $existing = EduAdmission::query()->where('company_id', $this->companyA->id)->count();

        for ($i = 0; $i < $count; $i++) {
            $number = 'ADM-'.str_pad((string) ($existing + $i + 1), 4, '0', STR_PAD_LEFT);

            EduAdmission::query()->create([
                'company_id' => $this->companyA->id,
                'admission_number' => $number,
                'applicant_name' => 'Candidat '.$number,
                'status' => ($existing + $i) % 2 === 0
                    ? EduAdmission::STATUS_PENDING
                    : EduAdmission::STATUS_ACCEPTED,
                'consent_marketing' => false,
                'submitted_at' => now(),
            ]);
        }
    }

    /**
     * Amorce `$count` charges de 1 250,50 en attente de règlement (société A).
     */
    private function seedFeeCharges(int $count): void
    {
        /** @var EduAcademicYear $year */
        $year = EduAcademicYear::query()->where('company_id', $this->companyA->id)->firstOrFail();

        /** @var EduStudent $student */
        $student = EduStudent::query()->where('company_id', $this->companyA->id)->firstOrFail();

        /** @var EduFeeType $feeType */
        $feeType = EduFeeType::query()->firstOrCreate(
            ['company_id' => $this->companyA->id, 'code' => 'TUITION'],
            ['label' => 'Frais de scolarité', 'amount' => 1250.50, 'currency' => 'DZD'],
        );

        for ($i = 0; $i < $count; $i++) {
            EduFeeCharge::query()->create([
                'company_id' => $this->companyA->id,
                'student_id' => $student->getAttribute('id'),
                'fee_type_id' => $feeType->getAttribute('id'),
                'academic_year_id' => $year->getAttribute('id'),
                'amount' => 1250.50,
                'currency' => 'DZD',
                'status' => $i % 2 === 0 ? EduFeeCharge::STATUS_PENDING : EduFeeCharge::STATUS_PARTIAL,
            ]);
        }
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

    /**
     * #7985 — les agrégats du dashboard (frais en attente, admissions par
     * statut) sont calculés par la BASE, pas en PHP.
     *
     * Deux verrous complémentaires :
     *  1. le nombre de requêtes est BORNÉ — identique que l'on ait N ou 2N
     *     lignes (aucun chargement de lignes, donc aucun coût par ligne) ;
     *  2. les requêtes émises sur `edu_fee_charges` / `edu_admissions` sont des
     *     AGRÉGATS SQL (`count(*)`, `sum(amount)`, `group by status`) — un
     *     `select *` remettrait le calcul en mémoire et rougirait ce test.
     */
    public function test_dashboard_aggregates_are_sql_level_and_bounded(): void
    {
        Sanctum::actingAs($this->principalA);

        // Périmètre N : 4 dossiers (2 statuts) + 4 charges en attente.
        $this->seedAdmissions(4);
        $this->seedFeeCharges(4);

        $firstCount = null;
        $this->getJson($this->baseUrl().'/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.pending_fees_count', 4)
            ->assertJsonPath('data.summary.pending_fees_total', 5002)
            ->assertJsonPath('data.navigation.7.count', 4)
            ->assertJsonPath('data.summary.admissions_by_status.pending', 2)
            ->assertJsonPath('data.summary.admissions_by_status.accepted', 2);

        $firstCount = $this->dashboardQueryCount();

        // Périmètre 2N : +4 dossiers et +4 charges (les agrégats doublent).
        $this->seedAdmissions(4);
        $this->seedFeeCharges(4);

        $this->getJson($this->baseUrl().'/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.pending_fees_count', 8)
            ->assertJsonPath('data.summary.pending_fees_total', 10004)
            ->assertJsonPath('data.navigation.7.count', 8)
            ->assertJsonPath('data.summary.admissions_by_status.pending', 4)
            ->assertJsonPath('data.summary.admissions_by_status.accepted', 4);

        $secondSql = $this->dashboardQueries();

        $this->assertSame(
            $firstCount,
            count($secondSql),
            'Le dashboard doit rester à nombre de requêtes CONSTANT : N et 2N lignes exigent le même nombre de requêtes.',
        );

        $sql = array_map(static fn (array $query): string => (string) $query['query'], $secondSql);

        $feeQueries = array_values(array_filter(
            $sql,
            static fn (string $query): bool => str_contains($query, 'from "edu_fee_charges"'),
        ));
        $this->assertCount(1, $feeQueries, 'Les frais en attente doivent tenir en UNE requête agrégée.');
        $this->assertStringContainsString('count(*)', $feeQueries[0]);
        $this->assertStringContainsString('sum(amount)', $feeQueries[0]);

        $admissionQueries = array_values(array_filter(
            $sql,
            static fn (string $query): bool => str_contains($query, 'from "edu_admissions"'),
        ));
        $this->assertCount(1, $admissionQueries, 'Les admissions par statut doivent tenir en UNE requête groupée.');
        $this->assertStringContainsString('group by "status"', $admissionQueries[0]);
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
