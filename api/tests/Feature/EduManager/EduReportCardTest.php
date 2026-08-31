<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Application\Services\ReportCardService;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Domain\Models\EduReportCard;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduStudentGuardian;
use App\Modules\EduManager\Domain\Policies\EduReportCardPolicy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5824 (EDU-008) — bulletins de période : génération, validation,
 * publication atomique et accès guardian autorisé.
 *
 * Couvre : génération d'un brouillon (manager), snapshot REPRODUCTIBLE
 * (2e génération = même `data`, jamais de doublon — mise à jour du
 * brouillon), moyennes par matière arrondies à 2 décimales (quand
 * edu_grades/edu_assessments sont livrés par EDU-007 en parallèle ;
 * sinon fail-closed : bulletin généré mais vide), validation puis
 * publication atomique (machine à états draft → validated → published,
 * non-draft IMMUABLE), accès guardian STRICT (uniquement via
 * edu_student_guardians avec can_view_grades=true), refus cross-tenant
 * (classe d'un autre tenant à la génération, bulletin d'un autre tenant
 * invisible), et snapshot sans PII (aucune donnée nominative hors tenant).
 */
class EduReportCardTest extends TestCase
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
    }

    public function test_manager_can_generate_draft_report_card(): void
    {
        $student = $this->student($this->company, 'S-001');
        $classId = $this->classId($this->company);
        $yearId = $this->yearId($this->company);

        $card = $this->service()->generate(
            $student,
            $classId,
            $yearId,
            'S1',
            Carbon::parse('2026-09-01'),
            Carbon::parse('2027-01-31'),
        );

        $this->assertSame(EduReportCard::STATUS_DRAFT, $card->status);
        $this->assertSame($student->id, $card->student_id);
        $this->assertSame($classId, $card->class_id);
        $this->assertSame($yearId, $card->academic_year_id);
        $this->assertSame('S1', $card->period_label);
        $this->assertSame('2026-09-01', $card->period_start->toDateString());
        $this->assertSame('2027-01-31', $card->period_end->toDateString());
        // Snapshot toujours structuré — vide tant que les notes ne sont pas
        // livrées (EDU-007 parallèle), ou sans notes pour cet élève.
        $this->assertArrayHasKey('subjects', $card->data);
        $this->assertArrayHasKey('grade_count', $card->data);
        $this->assertNull($card->average_score);

        $this->assertDatabaseHas('edu_report_cards', [
            'company_id' => $this->company->id,
            'student_id' => $student->id,
            'class_id' => $classId,
            'academic_year_id' => $yearId,
            'period_label' => 'S1',
            'status' => EduReportCard::STATUS_DRAFT,
        ]);
    }

    public function test_generation_is_reproducible_and_idempotent(): void
    {
        $student = $this->student($this->company, 'S-001');
        $classId = $this->classId($this->company);
        $yearId = $this->yearId($this->company);
        $this->seedGradesIfAvailable($student, $classId);

        $first = $this->service()->generate(
            $student,
            $classId,
            $yearId,
            'S1',
            Carbon::parse('2026-09-01'),
            Carbon::parse('2027-01-31'),
        );
        $second = $this->service()->generate(
            $student,
            $classId,
            $yearId,
            'S1',
            Carbon::parse('2026-09-01'),
            Carbon::parse('2027-01-31'),
        );

        // Idempotence : la 2e génération met à jour le brouillon, jamais de
        // doublon (UNIQUE company+student+year+period).
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, EduReportCard::query()
            ->where('company_id', $this->company->id)
            ->where('student_id', $student->id)
            ->count());

        // Reproductibilité : même snapshot tant que les notes n'ont pas
        // changé (lecture rafraîchie des deux côtés — représentation
        // jsonb identique).
        $this->assertSame($first->refresh()->data, $second->data);
    }

    public function test_generation_computes_subject_averages_from_published_grades(): void
    {
        if (! $this->gradesAvailable()) {
            $this->markTestSkipped('edu_grades/edu_assessments non livrés (EDU-007, #5823) — chemin vide couvert par les autres tests.');
        }

        $student = $this->student($this->company, 'S-001');
        $classId = $this->classId($this->company);
        $yearId = $this->yearId($this->company);
        $this->seedGradesIfAvailable($student, $classId);

        $card = $this->service()->generate(
            $student,
            $classId,
            $yearId,
            'S1',
            Carbon::parse('2026-09-01'),
            Carbon::parse('2027-01-31'),
        );

        // Mathématiques : 14.0 + 16.0 → 15.0 ; Physique : 12.5 → 12.5 ;
        // moyenne globale : (15.0 + 12.5) / 2 = 13.75 (2 décimales).
        $bySubject = [];
        foreach ($card->data['subjects'] as $subject) {
            $bySubject[(string) $subject['subject_name']] = $subject;
        }

        $this->assertSame(15.0, (float) $bySubject['Mathématiques']['average']);
        $this->assertSame(2, $bySubject['Mathématiques']['grade_count']);
        $this->assertSame(12.5, (float) $bySubject['Physique']['average']);
        $this->assertSame(1, $bySubject['Physique']['grade_count']);
        $this->assertSame(3, $card->data['grade_count']);
        $this->assertSame('13.75', $card->average_score);

        // Une note encore en brouillon (modifiable) ne fige JAMAIS un
        // bulletin : la note draft de 18.0 en Mathématiques n'est pas
        // comptée — la moyenne Mathématiques reste 15.0 (2 notes publiées)
        // et le total reste 3. Si elle l'était, la moyenne serait de 16.0
        // et les assertions ci-dessus échoueraient.
    }

    public function test_generation_rejects_inverted_period(): void
    {
        $student = $this->student($this->company, 'S-001');

        $this->expectException(InvalidArgumentException::class);

        $this->service()->generate(
            $student,
            $this->classId($this->company),
            $this->yearId($this->company),
            'S1',
            Carbon::parse('2027-01-31'),
            Carbon::parse('2026-09-01'),
        );
    }

    public function test_validate_then_publish_is_atomic(): void
    {
        $card = $this->draftCard();

        $this->service()->validate($card, $this->manager->id);

        $this->assertSame(EduReportCard::STATUS_VALIDATED, $card->status);
        $this->assertSame($this->manager->id, $card->validated_by);
        $this->assertNotNull($card->validated_at);

        $this->assertDatabaseHas('edu_report_cards', [
            'id' => $card->id,
            'status' => EduReportCard::STATUS_VALIDATED,
            'validated_by' => $this->manager->id,
        ]);

        // Publication atomique : statut + horodatage écrits ensemble.
        $this->service()->publish($card, $this->manager->id);

        $this->assertSame(EduReportCard::STATUS_PUBLISHED, $card->status);
        $this->assertNotNull($card->published_at);

        $this->assertDatabaseHas('edu_report_cards', [
            'id' => $card->id,
            'status' => EduReportCard::STATUS_PUBLISHED,
        ]);
    }

    public function test_publish_requires_validation(): void
    {
        $card = $this->draftCard();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->publish($card, $this->manager->id);
    }

    public function test_validate_requires_draft(): void
    {
        $card = $this->draftCard();
        $this->service()->validate($card, $this->manager->id);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->validate($card, $this->manager->id);
    }

    public function test_republish_is_rejected(): void
    {
        $card = $this->draftCard();
        $this->service()->validate($card, $this->manager->id);
        $this->service()->publish($card, $this->manager->id);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->publish($card, $this->manager->id);
    }

    public function test_published_card_is_immutable(): void
    {
        $card = $this->draftCard();
        $this->service()->validate($card, $this->manager->id);
        $this->service()->publish($card, $this->manager->id);

        $student = $card->student()->firstOrFail();

        // Régénérer un bulletin publié : REFUS (immuable après publication).
        $this->expectException(InvalidArgumentException::class);

        $this->service()->generate(
            $student,
            $card->class_id,
            $card->academic_year_id,
            $card->period_label,
            $card->period_start,
            $card->period_end,
        );
    }

    public function test_guardian_with_grade_right_can_view_report_card(): void
    {
        $card = $this->draftCard();
        $student = $card->student()->firstOrFail();

        /** @var Employee $guardianActor */
        $guardianActor = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);
        $this->linkGuardian($guardianActor, $student, canViewGrades: true);

        $policy = app(EduReportCardPolicy::class);

        // Gardien autorisé : accès au bulletin de SON élève uniquement.
        $this->assertTrue($policy->view($guardianActor, $card));
        // Un gardien ne liste JAMAIS les bulletins (viewAny = manager seul).
        $this->assertFalse($policy->viewAny($guardianActor));
    }

    public function test_guardian_without_grade_right_cannot_view_report_card(): void
    {
        $card = $this->draftCard();
        $student = $card->student()->firstOrFail();

        /** @var Employee $guardianActor */
        $guardianActor = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);
        $this->linkGuardian($guardianActor, $student, canViewGrades: false);

        // Lié à l'élève mais SANS can_view_grades : accès strict refusé.
        $this->assertFalse(app(EduReportCardPolicy::class)->view($guardianActor, $card));
    }

    public function test_guardian_never_sees_other_tenant_report_card(): void
    {
        $card = $this->draftCard();

        /** @var Employee $guardianActor */
        $guardianActor = Employee::factory()->create([
            'company_id' => $this->otherCompany->id,
            'role' => 'employee',
        ]);
        $this->guardian($this->otherCompany, $guardianActor);

        // Gardien d'un AUTRE tenant : le bulletin de ce tenant est invisible
        // (même s'il n'existe aucun lien, la garde tenant précède tout).
        $this->assertFalse(app(EduReportCardPolicy::class)->view($guardianActor, $card));
    }

    public function test_cross_tenant_report_card_is_invisible_to_other_manager(): void
    {
        $card = $this->draftCard();

        $otherStudent = $this->student($this->otherCompany, 'S-X1');
        $otherCard = $this->service()->generate(
            $otherStudent,
            $this->classId($this->otherCompany),
            $this->yearId($this->otherCompany),
            'S1',
            Carbon::parse('2026-09-01'),
            Carbon::parse('2027-01-31'),
        );

        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->create([
            'company_id' => $this->otherCompany->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        $policy = app(EduReportCardPolicy::class);

        // Un gestionnaire ne voit JAMAIS les bulletins d'un autre tenant.
        $this->assertFalse($policy->view($otherManager, $card));
        $this->assertFalse($policy->view($this->manager, $otherCard));
        $this->assertFalse($policy->validate($otherManager, $card));
        $this->assertFalse($policy->publish($otherManager, $card));
    }

    public function test_cross_tenant_class_is_rejected_during_generation(): void
    {
        $student = $this->student($this->company, 'S-001');

        // Classe d'un AUTRE tenant pour un élève de ce tenant : refusé
        // avant toute écriture (contrôle classe best-effort #5819, actif
        // sur cette branche).
        $this->expectException(ModelNotFoundException::class);

        $this->service()->generate(
            $student,
            $this->classId($this->otherCompany),
            $this->yearId($this->company),
            'S1',
            Carbon::parse('2026-09-01'),
            Carbon::parse('2027-01-31'),
        );
    }

    public function test_plain_employee_is_not_authorized(): void
    {
        $card = $this->draftCard();

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);

        $policy = app(EduReportCardPolicy::class);

        $this->assertFalse($policy->viewAny($employee));
        $this->assertFalse($policy->view($employee, $card));
        $this->assertFalse($policy->validate($employee, $card));
        $this->assertFalse($policy->publish($employee, $card));
    }

    public function test_validate_and_publish_are_manager_scoped(): void
    {
        $card = $this->draftCard();

        $policy = app(EduReportCardPolicy::class);

        $this->assertTrue($policy->viewAny($this->manager));
        $this->assertTrue($policy->view($this->manager, $card));
        $this->assertTrue($policy->validate($this->manager, $card));
        $this->assertTrue($policy->publish($this->manager, $card));
    }

    public function test_snapshot_contains_no_pii(): void
    {
        $student = $this->student($this->company, 'S-001');
        $classId = $this->classId($this->company);
        $yearId = $this->yearId($this->company);
        $this->seedGradesIfAvailable($student, $classId);

        $card = $this->service()->generate(
            $student,
            $classId,
            $yearId,
            'S1',
            Carbon::parse('2026-09-01'),
            Carbon::parse('2027-01-31'),
        );

        // Export PDF / réutilisation du snapshot : AUCUNE donnée nominative
        // dans `data` (moyennes par matière uniquement) — rien qui puisse
        // fuiter hors tenant.
        /** @var string $raw */
        $raw = DB::table('edu_report_cards')->where('id', $card->id)->value('data');
        $this->assertIsString($raw);
        $this->assertStringNotContainsString($student->display_name, $raw);
        $this->assertStringNotContainsString($student->student_number, $raw);

        $snapshot = json_decode($raw, true);
        $this->assertIsArray($snapshot);
        $this->assertArrayHasKey('subjects', $snapshot);
        foreach ($snapshot['subjects'] as $subject) {
            $this->assertArrayNotHasKey('student_id', $subject);
            $this->assertArrayNotHasKey('student_name', $subject);
            $this->assertArrayNotHasKey('display_name', $subject);
        }
    }

    private function service(): ReportCardService
    {
        return app(ReportCardService::class);
    }

    /**
     * Brouillon S1 du tenant principal (2026-09-01 → 2027-01-31).
     */
    private function draftCard(): EduReportCard
    {
        $student = $this->student($this->company, 'S-001');

        return $this->service()->generate(
            $student,
            $this->classId($this->company),
            $this->yearId($this->company),
            'S1',
            Carbon::parse('2026-09-01'),
            Carbon::parse('2027-01-31'),
        );
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
     * ID de l'année scolaire du tenant — robuste à l'état du lot EDU-003
     * (#5819) : tant que `edu_academic_years` n'existe pas, un id arbitraire
     * suffit (pas de FK) ; sinon on insère la chaîne minimale.
     */
    private function yearId(Company $company): int
    {
        if (! schemaTableExists('edu_academic_years')) {
            return 1;
        }

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

        return (int) $yearId;
    }

    /**
     * ID d'une classe du tenant — robuste à l'état du lot EDU-003 (#5819) :
     * tant que `edu_classes` n'existe pas, la FK composite n'est pas active
     * et un id arbitraire suffit ; sinon on insère la chaîne minimale
     * année scolaire → classe pour satisfaire la contrainte en base.
     */
    private function classId(Company $company): int
    {
        if (! schemaTableExists('edu_classes')) {
            return 1;
        }

        $yearId = $this->yearId($company);

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
     * Les tables edu_grades/edu_assessments (EDU-007, #5823) sont-elles
     * livrées avec les colonnes attendues ?
     */
    private function gradesAvailable(): bool
    {
        return schemaTableExists('edu_grades')
            && schemaTableExists('edu_assessments')
            && schemaHasColumn('edu_grades', 'score')
            && schemaHasColumn('edu_grades', 'status')
            && schemaHasColumn('edu_grades', 'assessment_id')
            && schemaHasColumn('edu_assessments', 'subject_id')
            && schemaHasColumn('edu_assessments', 'academic_year_id')
            && schemaHasColumn('edu_assessments', 'assessment_date');
    }

    /**
     * Note le scénario minimal pour le calcul des moyennes (si livré) :
     * Mathématiques 14.0 + 16.0 → 15.0 ; Physique 12.5 → 12.5 ; globale
     * 13.75. Une note DRAFT supplémentaire (18.0 en Mathématiques) doit
     * être exclue du bulletin (seules les notes publiées comptent).
     */
    private function seedGradesIfAvailable(EduStudent $student, int $classId): void
    {
        if (! $this->gradesAvailable()) {
            return;
        }

        $companyId = (string) $student->company_id;
        $yearId = $this->yearId($this->company);

        $mathId = $this->subjectId($companyId, 'MATH', 'Mathématiques');
        $physicsId = $this->subjectId($companyId, 'PHY', 'Physique');

        $mathExam = $this->assessmentId($companyId, $classId, $mathId, $yearId, '2026-10-05', 'Composition S1');
        $mathTest = $this->assessmentId($companyId, $classId, $mathId, $yearId, '2026-11-12', 'Test S1');
        $physicsExam = $this->assessmentId($companyId, $classId, $physicsId, $yearId, '2026-10-19', 'Composition S1');

        // Notes PUBLIÉES (une seule note par élève et par évaluation).
        $this->gradeId($companyId, $mathExam, $student->id, '14.0', 'published');
        $this->gradeId($companyId, $mathTest, $student->id, '16.0', 'published');
        $this->gradeId($companyId, $physicsExam, $student->id, '12.5', 'published');

        // Note encore en brouillon (évaluation non publiée) : modifiable,
        // donc EXCLUE du bulletin (seules les notes publiées comptent).
        $draftMath = $this->assessmentId($companyId, $classId, $mathId, $yearId, '2026-12-03', 'Interro S1', 'draft');
        $this->gradeId($companyId, $draftMath, $student->id, '18.0', 'draft');
    }

    private function subjectId(string $companyId, string $code, string $name): int
    {
        DB::table('edu_subjects')->insertOrIgnore([
            'company_id' => $companyId,
            'code' => $code,
            'name' => $name,
        ]);

        /** @var int|string|null $subjectId */
        $subjectId = DB::table('edu_subjects')
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->value('id');

        return (int) $subjectId;
    }

    private function assessmentId(
        string $companyId,
        int $classId,
        int $subjectId,
        int $academicYearId,
        string $date,
        string $title,
        string $status = 'published',
    ): int {
        return (int) DB::table('edu_assessments')->insertGetId([
            'company_id' => $companyId,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'academic_year_id' => $academicYearId,
            'title' => $title,
            'assessment_type' => 'exam',
            'max_score' => '20.00',
            'assessment_date' => $date,
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
        ]);
    }

    private function gradeId(string $companyId, int $assessmentId, int $studentId, string $score, string $status = 'published'): void
    {
        DB::table('edu_grades')->insert([
            'company_id' => $companyId,
            'assessment_id' => $assessmentId,
            'student_id' => $studentId,
            'score' => $score,
            'status' => $status,
        ]);
    }

    private function guardian(Company $company, Employee $employee): EduGuardian
    {
        /** @var EduGuardian $guardian */
        $guardian = EduGuardian::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
        ]);

        return $guardian;
    }

    private function linkGuardian(Employee $guardianActor, EduStudent $student, bool $canViewGrades = false): EduStudentGuardian
    {
        $guardian = $this->guardian($this->company, $guardianActor);

        /** @var EduStudentGuardian $link */
        $link = EduStudentGuardian::query()->create([
            'company_id' => $this->company->id,
            'student_id' => $student->id,
            'guardian_id' => $guardian->id,
            'can_view_grades' => $canViewGrades,
        ]);

        return $link;
    }
}
