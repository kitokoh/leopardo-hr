<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Exceptions\TenantContextMissingException;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Application\Services\GradeService;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Domain\Models\EduGradeVersion;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Policies\EduAssessmentPolicy;
use App\Modules\EduManager\Domain\Policies\EduGradePolicy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5823 (EDU-007) — évaluations et notes VERSIONNÉES.
 *
 * Couvre : création d'évaluation + note (manager), note modifiable tant
 * qu'elle n'est pas publiée (idempotent, pas de doublon), publication
 * ATOMIQUE (toutes les notes draft → published + évaluation verrouillée),
 * correction d'une note publiée VERSIONNÉE (une ligne edu_grade_versions
 * par correction AVANT la mutation, la note reflète la dernière valeur),
 * refus cross-tenant (élève OU évaluation d'un autre tenant), PII
 * minimisée (commentaire/justification > 255 rejetés), et policies bornées
 * au tenant (manager) / enseignants de la classe.
 */
class EduGradeTest extends TestCase
{
    use RefreshTenantDatabase;
    use WithFaker;

    private Company $company;

    private Company $otherCompany;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->otherCompany = $other;

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->manager = $manager;

        // Contexte tenant courant : requis par le scope BelongsToCompany et
        // par currentCompany() dans GradeService (pattern EduAdmissionTest).
        app()->instance('current_company', $company);
        Sanctum::actingAs($manager);
    }

    public function test_manager_can_create_assessment_and_record_grade(): void
    {
        $assessment = $this->assessment($this->company);
        $student = $this->student($this->company, 'S-001');

        $grade = $this->service()->recordGrade(
            $assessment,
            (int) $student->id,
            15.5,
            'Travail sérieux',
            (int) $this->manager->id,
        );

        $this->assertSame($assessment->id, $grade->assessment_id);
        $this->assertSame($student->id, $grade->student_id);
        $this->assertEqualsWithDelta(15.5, (float) $grade->score, 0.001);
        $this->assertSame('Travail sérieux', $grade->comment);
        $this->assertSame(EduGrade::STATUS_DRAFT, $grade->status);
        $this->assertSame($this->manager->id, $grade->graded_by);
        $this->assertSame($this->company->id, $grade->company_id);

        $this->assertDatabaseHas('edu_assessments', [
            'id' => $assessment->id,
            'company_id' => $this->company->id,
            'title' => 'Devoir de mathématiques',
            'assessment_type' => EduAssessment::TYPE_TEST,
            'status' => EduAssessment::STATUS_DRAFT,
        ]);

        $this->assertDatabaseHas('edu_grades', [
            'id' => $grade->id,
            'company_id' => $this->company->id,
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'score' => 15.5,
            'status' => EduGrade::STATUS_DRAFT,
        ]);
    }

    public function test_draft_grade_is_modifiable_before_publication(): void
    {
        $assessment = $this->assessment($this->company);
        $student = $this->student($this->company, 'S-001');
        $service = $this->service();

        $first = $service->recordGrade($assessment, (int) $student->id, 15.5, 'Brouillon 1', (int) $this->manager->id);

        // Re-saisie avant publication : même note mise à jour en place
        // (modifiable), aucun doublon, aucun versionnage.
        $second = $service->recordGrade($assessment, (int) $student->id, 14.0, 'Brouillon 2', (int) $this->manager->id);

        $this->assertSame($first->id, $second->id);
        $this->assertEqualsWithDelta(14.0, (float) $second->score, 0.001);
        $this->assertSame('Brouillon 2', $second->comment);
        $this->assertSame(EduGrade::STATUS_DRAFT, $second->status);

        $this->assertSame(
            1,
            EduGrade::query()
                ->where('company_id', $this->company->id)
                ->where('assessment_id', $assessment->id)
                ->count()
        );
    }

    public function test_publish_assessment_publishes_all_draft_grades_atomically(): void
    {
        $assessment = $this->assessment($this->company);
        $studentA = $this->student($this->company, 'S-001');
        $studentB = $this->student($this->company, 'S-002');
        $service = $this->service();

        $service->recordGrade($assessment, (int) $studentA->id, 12.0, null, (int) $this->manager->id);
        $service->recordGrade($assessment, (int) $studentB->id, 18.5, 'Excellent', (int) $this->manager->id);

        $service->publishAssessment($assessment, (int) $this->manager->id);

        // Toutes les notes draft → published, évaluation verrouillée.
        $this->assertSame(
            2,
            EduGrade::query()
                ->where('company_id', $this->company->id)
                ->where('assessment_id', $assessment->id)
                ->where('status', EduGrade::STATUS_PUBLISHED)
                ->count()
        );

        /** @var EduGrade $gradeA */
        $gradeA = EduGrade::query()
            ->where('assessment_id', $assessment->id)
            ->where('student_id', $studentA->id)
            ->firstOrFail();
        $this->assertSame(EduGrade::STATUS_PUBLISHED, $gradeA->status);
        $this->assertNotNull($gradeA->graded_at);

        $assessment->refresh();
        $this->assertSame(EduAssessment::STATUS_PUBLISHED, $assessment->status);
        $this->assertNotNull($assessment->published_at);

        // Idempotence : re-publication = no-op propre (toujours 2 notes).
        $service->publishAssessment($assessment, (int) $this->manager->id);
        $this->assertSame(
            2,
            EduGrade::query()
                ->where('company_id', $this->company->id)
                ->where('assessment_id', $assessment->id)
                ->count()
        );
    }

    public function test_record_grade_is_refused_on_published_assessment(): void
    {
        $assessment = $this->assessment($this->company);
        $studentA = $this->student($this->company, 'S-001');
        $service = $this->service();

        $service->recordGrade($assessment, (int) $studentA->id, 15.5, null, (int) $this->manager->id);
        $service->publishAssessment($assessment, (int) $this->manager->id);

        // Évaluation publiée : plus aucune saisie (immuable, pas d'écrasement).
        $newStudent = $this->student($this->company, 'S-003');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('publiée');

        $service->recordGrade($assessment, (int) $newStudent->id, 10.0, null, (int) $this->manager->id);
    }

    public function test_published_grade_correction_is_versioned(): void
    {
        $assessment = $this->assessment($this->company);
        $student = $this->student($this->company, 'S-001');
        $service = $this->service();

        $grade = $service->recordGrade($assessment, (int) $student->id, 15.5, null, (int) $this->manager->id);
        $service->publishAssessment($assessment, (int) $this->manager->id);
        $grade->refresh();

        // Correction 1 : 15.5 → 16 (version écrite AVANT la mutation).
        $grade = $service->correctGrade($grade, 16.0, 'Erreur de saisie', (int) $this->manager->id);
        $this->assertEqualsWithDelta(16.0, (float) $grade->score, 0.001);
        $this->assertSame(EduGrade::STATUS_PUBLISHED, $grade->status);

        // Correction 2 : 16 → 17 (nouvelle version).
        $grade = $service->correctGrade($grade, 17.0, 'Relecture du barème', (int) $this->manager->id);
        $this->assertEqualsWithDelta(17.0, (float) $grade->score, 0.001);

        // 2 corrections = 2 lignes en base (versionnage, jamais d'écrasement).
        $versions = EduGradeVersion::query()
            ->where('company_id', $this->company->id)
            ->where('grade_id', $grade->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $versions);
        $this->assertEqualsWithDelta(15.5, (float) $versions[0]->previous_score, 0.001);
        $this->assertEqualsWithDelta(16.0, (float) $versions[0]->new_score, 0.001);
        $this->assertSame(EduGrade::STATUS_PUBLISHED, $versions[0]->previous_status);
        $this->assertSame(EduGrade::STATUS_PUBLISHED, $versions[0]->new_status);
        $this->assertSame('Erreur de saisie', $versions[0]->reason);
        $this->assertSame($this->manager->id, $versions[0]->changed_by);
        $this->assertNotNull($versions[0]->changed_at);
        $this->assertEqualsWithDelta(16.0, (float) $versions[1]->previous_score, 0.001);
        $this->assertEqualsWithDelta(17.0, (float) $versions[1]->new_score, 0.001);
        $this->assertSame('Relecture du barème', $versions[1]->reason);

        // La note en base reflète la DERNIÈRE valeur.
        $this->assertDatabaseHas('edu_grades', [
            'id' => $grade->id,
            'score' => 17.0,
        ]);
    }

    public function test_cross_tenant_student_is_rejected(): void
    {
        // Élève d'un AUTRE tenant : jamais notable chez ce tenant.
        $otherStudent = $this->student($this->otherCompany, 'S-X1');
        $assessment = $this->assessment($this->company);

        $this->expectException(ModelNotFoundException::class);

        $this->service()->recordGrade(
            $assessment,
            (int) $otherStudent->id,
            10.0,
            null,
            (int) $this->manager->id,
        );
    }

    public function test_cross_tenant_assessment_is_rejected(): void
    {
        // Évaluation d'un AUTRE tenant : refus avant toute écriture.
        $otherAssessment = $this->assessment($this->otherCompany);
        $student = $this->student($this->company, 'S-001');

        $this->expectException(TenantContextMissingException::class);

        $this->service()->recordGrade(
            $otherAssessment,
            (int) $student->id,
            10.0,
            null,
            (int) $this->manager->id,
        );
    }

    public function test_score_outside_scale_is_rejected(): void
    {
        $assessment = $this->assessment($this->company, ['max_score' => 20]);
        $student = $this->student($this->company, 'S-001');
        $service = $this->service();

        try {
            $service->recordGrade($assessment, (int) $student->id, -1.0, null, (int) $this->manager->id);
            $this->fail('Une note négative aurait dû être rejetée.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('négative', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('dépasse le barème');

        // Barème 20 : 21 est hors échelle (validation serveur, spec §6.3).
        $service->recordGrade($assessment, (int) $student->id, 21.0, null, (int) $this->manager->id);
    }

    public function test_pii_is_minimized_comment_and_reason_are_bounded(): void
    {
        $assessment = $this->assessment($this->company);
        $student = $this->student($this->company, 'S-001');
        $service = $this->service();

        // Commentaire libre > 255 : rejeté (PII minimisée, spec §6.3).
        try {
            $service->recordGrade(
                $assessment,
                (int) $student->id,
                10.0,
                str_repeat('x', 256),
                (int) $this->manager->id,
            );
            $this->fail('Un commentaire > 255 aurait dû être rejeté.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('255', $e->getMessage());
        }

        // Le commentaire borné (<= 255) passe.
        $grade = $service->recordGrade(
            $assessment,
            (int) $student->id,
            10.0,
            str_repeat('y', 255),
            (int) $this->manager->id,
        );
        $this->assertSame(255, mb_strlen((string) $grade->comment));
    }

    public function test_correction_requires_a_justification(): void
    {
        $assessment = $this->assessment($this->company);
        $student = $this->student($this->company, 'S-001');
        $service = $this->service();

        $grade = $service->recordGrade($assessment, (int) $student->id, 15.5, null, (int) $this->manager->id);
        $service->publishAssessment($assessment, (int) $this->manager->id);
        $grade->refresh();

        // Justification vide : refusée (audit rejouable).
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('justification');

        $service->correctGrade($grade, 16.0, '   ', (int) $this->manager->id);
    }

    public function test_grade_policy_is_tenant_bound_and_publish_locked(): void
    {
        // Deux évaluations distinctes : la publication de l'une ne doit pas
        // impacter le brouillon de l'autre (titre distinct — UNIQUE par
        // classe/matière/titre).
        $draftAssessment = $this->assessment($this->company, ['title' => 'Interrogation orale']);
        $publishedAssessment = $this->assessment($this->company, ['title' => 'Devoir de mathématiques']);
        $otherAssessment = $this->assessment($this->otherCompany);
        $student = $this->student($this->company, 'S-001');
        $otherStudent = $this->student($this->otherCompany, 'S-X1');
        $service = $this->service();

        $draft = $service->recordGrade($draftAssessment, (int) $student->id, 15.5, null, (int) $this->manager->id);

        $published = $service->recordGrade($publishedAssessment, (int) $student->id, 15.5, null, (int) $this->manager->id);
        $service->publishAssessment($publishedAssessment, (int) $this->manager->id);
        $published->refresh();

        /** @var EduGrade $otherGrade */
        $otherGrade = EduGrade::query()->create([
            'company_id' => $this->otherCompany->id,
            'assessment_id' => $otherAssessment->id,
            'student_id' => $otherStudent->id,
            'score' => 12.0,
            'status' => EduGrade::STATUS_DRAFT,
        ]);

        $policy = app(EduGradePolicy::class);

        // Gestionnaire : tout sur SON tenant, brouillon y compris.
        $this->assertTrue($policy->viewAny($this->manager));
        $this->assertTrue($policy->view($this->manager, $draft));
        $this->assertTrue($policy->create($this->manager));
        $this->assertTrue($policy->update($this->manager, $draft));

        // Note publiée : update REFUSÉ (immuable), correct autorisé (auditable).
        $this->assertFalse($policy->update($this->manager, $published));
        $this->assertTrue($policy->correct($this->manager, $published));

        // Un gestionnaire ne voit JAMAIS les notes d'un autre tenant.
        $this->assertFalse($policy->view($this->manager, $otherGrade));
        $this->assertFalse($policy->update($this->manager, $otherGrade));
        $this->assertFalse($policy->correct($this->manager, $otherGrade));

        // Employé simple : aucun accès (notes sensibles).
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);
        $this->assertFalse($policy->viewAny($employee));
        $this->assertFalse($policy->create($employee));
        $this->assertFalse($policy->view($employee, $draft));
    }

    public function test_assessment_policy_allows_teacher_of_the_class(): void
    {
        $classId = $this->classId($this->company);
        $subjectId = $this->subjectId($this->company);

        /** @var Employee $teacherEmployee */
        $teacherEmployee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);
        $teacherId = $this->teacherRow($this->company, (int) $teacherEmployee->id);
        // L'enseignant a une séance dans la classe → il l'enseigne.
        $this->slotRow($this->company, $classId, $subjectId, $teacherId);

        $assessment = $this->assessment($this->company, ['class_id' => $classId, 'subject_id' => $subjectId]);
        $otherAssessment = $this->assessment($this->otherCompany);

        $policy = app(EduAssessmentPolicy::class);

        $this->assertTrue($policy->viewAny($teacherEmployee));
        // L'enseignant voit l'évaluation de SA classe...
        $this->assertTrue($policy->view($teacherEmployee, $assessment));
        // ...jamais celle d'un autre tenant...
        $this->assertFalse($policy->view($teacherEmployee, $otherAssessment));
        // ...et ne crée ni ne publie (actes d'administration).
        $this->assertFalse($policy->create($teacherEmployee));
        $this->assertFalse($policy->publish($teacherEmployee, $assessment));

        // Le gestionnaire, lui, fait tout sur son tenant.
        $this->assertTrue($policy->viewAny($this->manager));
        $this->assertTrue($policy->view($this->manager, $assessment));
        $this->assertTrue($policy->create($this->manager));
        $this->assertTrue($policy->publish($this->manager, $assessment));
        $this->assertFalse($policy->view($this->manager, $otherAssessment));
    }

    private function service(): GradeService
    {
        return app(GradeService::class);
    }

    /**
     * Évaluation du tenant — la classe, la matière et l'année scolaire
     * minimales sont créées au besoin (robuste à l'état du lot).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function assessment(Company $company, array $overrides = []): EduAssessment
    {
        $classId = $overrides['class_id'] ?? $this->classId($company);
        $subjectId = $overrides['subject_id'] ?? $this->subjectId($company);
        $yearId = $this->academicYearId($company);

        /** @var EduAssessment $assessment */
        $assessment = EduAssessment::query()->create([
            'company_id' => $company->id,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'academic_year_id' => $yearId,
            'title' => (string) ($overrides['title'] ?? 'Devoir de mathématiques'),
            'assessment_type' => EduAssessment::TYPE_TEST,
            'max_score' => $overrides['max_score'] ?? 20,
            'coefficient' => 1,
            'assessment_date' => '2026-09-15',
            'status' => EduAssessment::STATUS_DRAFT,
            'created_by' => $this->manager->id,
        ]);

        return $assessment;
    }

    /**
     * ID de l'année scolaire 2025-2026 du tenant (créée par classId()).
     */
    private function academicYearId(Company $company): int
    {
        /** @var int|string|null $yearId */
        $yearId = DB::table('edu_academic_years')
            ->where('company_id', $company->id)
            ->where('name', '2025-2026')
            ->value('id');

        return (int) $yearId;
    }

    /**
     * ID d'une classe du tenant — chaîne minimale année scolaire → classe.
     */
    private function classId(Company $company): int
    {
        DB::table('edu_academic_years')->insertOrIgnore([
            'company_id' => $company->id,
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
        ]);

        /** @var int|string|null $yearId */
        $yearId = DB::table('edu_academic_years')
            ->where('company_id', $company->id)
            ->where('name', '2025-2026')
            ->value('id');

        DB::table('edu_classes')->insertOrIgnore([
            'company_id' => $company->id,
            'academic_year_id' => $yearId,
            'name' => '6AP',
        ]);

        /** @var int|string|null $classId */
        $classId = DB::table('edu_classes')
            ->where('company_id', $company->id)
            ->where('name', '6AP')
            ->value('id');

        return (int) $classId;
    }

    /**
     * ID d'une matière du tenant (code unique par tenant).
     */
    private function subjectId(Company $company): int
    {
        DB::table('edu_subjects')->insertOrIgnore([
            'company_id' => $company->id,
            'code' => 'MATH',
            'name' => 'Mathématiques',
        ]);

        /** @var int|string|null $subjectId */
        $subjectId = DB::table('edu_subjects')
            ->where('company_id', $company->id)
            ->where('code', 'MATH')
            ->value('id');

        return (int) $subjectId;
    }

    private function student(Company $company, string $number): EduStudent
    {
        /** @var EduStudent $student */
        $student = EduStudent::query()->create([
            'company_id' => $company->id,
            'student_number' => $number,
            'display_name' => $this->faker->name(),
        ]);

        return $student;
    }

    /**
     * Profil enseignant lié à un employé (lien EduTeacher.employee_id).
     */
    private function teacherRow(Company $company, int $employeeId): int
    {
        return (int) DB::table('edu_teachers')->insertGetId([
            'company_id' => $company->id,
            'employee_id' => $employeeId,
            'display_name' => 'Mme Enseignante',
            'status' => 'active',
        ]);
    }

    /**
     * Séance de l'enseignant dans la classe (lien enseignant → classe,
     * EDU-006 #5822) — insert brut, pas de détection de conflit ici.
     */
    private function slotRow(Company $company, int $classId, int $subjectId, int $teacherId): void
    {
        DB::table('edu_timetable_slots')->insert([
            'company_id' => $company->id,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,
            'day_of_week' => 1,
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
        ]);
    }
}
