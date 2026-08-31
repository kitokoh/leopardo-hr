<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Services;

use App\Modules\EduManager\Domain\Models\EduReportCard;
use App\Modules\EduManager\Domain\Models\EduStudent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Issue #5824 (EDU-008) — service des bulletins de période.
 *
 * - `generate()` : calcule les moyennes par matière (edu_grades ⋈
 *   edu_assessments) sur la période, écrit le snapshot `data` et
 *   `average_score`, statut draft. IDEMPOTENT : un brouillon existant pour
 *   (student, academic_year, period_label) est MIS À JOUR, jamais dupliqué ;
 *   un bulletin non-draft est IMMUABLE (exception). Le snapshot est
 *   REPRODUCTIBLE : pure fonction des notes (aucun horodatage dans `data`)
 *   — régénérer donne le même résultat tant que les notes n'ont pas changé.
 * - `validate()` : draft → validated (validated_by, validated_at).
 * - `publish()` : validated → published (published_at), dans une
 *   transaction (publication atomique).
 *
 * Seules les notes PUBLIÉES entrent dans le bulletin (EduGrade.status =
 * 'published', posé de façon atomique par GradeService#publishAssessment,
 * EDU-007 #5823) : un brouillon de note encore modifiable ne fige jamais un
 * bulletin. Toutes les moyennes sont arrondies à 2 décimales. Tout est
 * borné au tenant de l'élève : une classe ou une année d'un autre tenant →
 * ModelNotFoundException (404 à la surface API).
 *
 * Dépendances SOFT sur EduAssessment/EduGrade (livrés par EDU-007 en
 * parallèle, #5823) : références de classe en chaîne + class_exists + repli
 * query-builder si les tables existent sans les modèles ; sinon FAIL-CLOSED
 * (bulletin généré mais VIDE — data = snapshot vide, average_score null).
 */
final class ReportCardService
{
    /** @var class-string<Model> */
    private const ASSESSMENT_MODEL = 'App\Modules\EduManager\Domain\Models\EduAssessment';

    /** @var class-string<Model> */
    private const GRADE_MODEL = 'App\Modules\EduManager\Domain\Models\EduGrade';

    /**
     * Génère (ou régénère) le bulletin draft d'un élève pour une période.
     *
     * @throws InvalidArgumentException  libellé de période vide / période
     *                                   inversée (start >= end) / bulletin
     *                                   existant non-draft (immuable)
     * @throws ModelNotFoundException    année scolaire ou classe absente du
     *                                   tenant de l'élève
     */
    public function generate(
        EduStudent $student,
        int $classId,
        int $academicYearId,
        string $periodLabel,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): EduReportCard {
        $companyId = (string) $student->company_id;

        if ($periodLabel === '') {
            throw new InvalidArgumentException('Le libellé de période est requis.');
        }

        if ($periodStart->greaterThanOrEqualTo($periodEnd)) {
            throw new InvalidArgumentException('La période du bulletin est inversée (start >= end).');
        }

        $this->assertAcademicYearInTenant($academicYearId, $companyId);
        $this->assertClassInTenant($classId, $companyId);

        [$subjects, $gradeCount] = $this->subjectAverages(
            $student,
            $classId,
            $academicYearId,
            $companyId,
            $periodStart,
            $periodEnd,
        );

        // Moyenne globale = moyenne des moyennes par matière (arrondie à 2
        // décimales) ; null tant qu'aucune note publiée n'est disponible.
        $averageScore = $subjects === []
            ? null
            : round(array_sum(array_column($subjects, 'average')) / count($subjects), 2);

        // Snapshot reproductible : aucune donnée volatile (horodatage,
        // auteur…) — pure fonction des notes publiées.
        $data = [
            'subjects' => $subjects,
            'grade_count' => $gradeCount,
        ];

        /** @var EduReportCard|null $existing */
        $existing = EduReportCard::query()
            ->where('company_id', $companyId)
            ->where('student_id', $student->id)
            ->where('academic_year_id', $academicYearId)
            ->where('period_label', $periodLabel)
            ->first();

        if ($existing !== null) {
            if ($existing->status !== EduReportCard::STATUS_DRAFT) {
                throw new InvalidArgumentException(sprintf(
                    'Le bulletin %s (%s) est %s : seuls les brouillons sont régénérables.',
                    $periodLabel,
                    $existing->academic_year_id,
                    $existing->status,
                ));
            }

            // Idempotence : mise à jour du brouillon, jamais de doublon.
            $existing->forceFill([
                'class_id' => $classId,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'data' => $data,
                'average_score' => $averageScore,
            ])->save();

            return $existing->refresh();
        }

        /** @var EduReportCard $card */
        $card = EduReportCard::query()->create([
            'company_id' => $companyId,
            'student_id' => $student->id,
            'class_id' => $classId,
            'academic_year_id' => $academicYearId,
            'period_label' => $periodLabel,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'data' => $data,
            'average_score' => $averageScore,
            'status' => EduReportCard::STATUS_DRAFT,
        ]);

        return $card;
    }

    /**
     * Valide un brouillon : draft → validated (traçabilité acteur/date).
     *
     * @throws InvalidArgumentException  le bulletin n'est pas un brouillon
     */
    public function validate(EduReportCard $card, int $actorId): void
    {
        if ($card->status !== EduReportCard::STATUS_DRAFT) {
            throw new InvalidArgumentException(sprintf(
                'Seul un brouillon peut être validé (statut actuel : %s).',
                $card->status,
            ));
        }

        $card->forceFill([
            'status' => EduReportCard::STATUS_VALIDATED,
            'validated_by' => $actorId,
            'validated_at' => now(),
        ])->save();
    }

    /**
     * Publie un bulletin validé : validated → published (published_at).
     *
     * Publication ATOMIQUE (transaction) : statut et horodatage sont écrits
     * ensemble ; un bulletin publié est immuable (toute régénération est
     * refusée par `generate()`).
     *
     * @throws InvalidArgumentException  le bulletin n'est pas validé
     */
    public function publish(EduReportCard $card, int $actorId): void
    {
        if ($card->status !== EduReportCard::STATUS_VALIDATED) {
            throw new InvalidArgumentException(sprintf(
                'Seul un bulletin validé peut être publié (statut actuel : %s).',
                $card->status,
            ));
        }

        // L'acteur est déjà tracé par `validated_by` (pas de colonne
        // published_by) ; l'id est conservé pour un audit ultérieur.
        DB::transaction(function () use ($card): void {
            $card->forceFill([
                'status' => EduReportCard::STATUS_PUBLISHED,
                'published_at' => now(),
            ])->save();
        });

        $card->refresh();
    }

    /**
     * Moyennes par matière depuis les notes PUBLIÉES (edu_grades ⋈
     * edu_assessments) sur la période (borne inclusive) et l'année scolaire.
     * Les moyennes sont arrondies à 2 décimales ; le tri par subject_id rend
     * le snapshot déterministe.
     *
     * @return array{0: list<array{
     *     subject_id: int,
     *     subject_name: string|null,
     *     average: float,
     *     grade_count: int,
     * }>, 1: int}
     */
    private function subjectAverages(
        EduStudent $student,
        int $classId,
        int $academicYearId,
        string $companyId,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): array {
        $rows = $this->gradeRows($student, $classId, $academicYearId, $companyId, $periodStart, $periodEnd);

        $gradeCount = count($rows);
        if ($gradeCount === 0) {
            return [[], 0];
        }

        /** @var array<int, list<float>> $scoresBySubject */
        $scoresBySubject = [];
        foreach ($rows as $row) {
            $scoresBySubject[$row['subject_id']][] = $row['score'];
        }

        // Tri déterministe (reproductibilité du snapshot).
        ksort($scoresBySubject);

        $subjects = [];
        foreach ($scoresBySubject as $subjectId => $scores) {
            $subjects[] = [
                'subject_id' => $subjectId,
                'subject_name' => $this->subjectName($subjectId, $companyId),
                'average' => round(array_sum($scores) / count($scores), 2),
                'grade_count' => count($scores),
            ];
        }

        return [$subjects, $gradeCount];
    }

    /**
     * Lignes (subject_id, score) des notes PUBLIÉES de l'élève sur la
     * période et l'année scolaire, dans la classe. Voie Eloquent si
     * EduGrade/EduAssessment sont livrés (EDU-007), repli query-builder si
     * seules les tables existent, fail-closed sinon.
     *
     * @return list<array{subject_id: int, score: float}>
     */
    private function gradeRows(
        EduStudent $student,
        int $classId,
        int $academicYearId,
        string $companyId,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): array {
        $start = $periodStart->toDateString();
        $end = $periodEnd->toDateString();

        if (class_exists(self::GRADE_MODEL) && class_exists(self::ASSESSMENT_MODEL)) {
            $rows = self::GRADE_MODEL::query()
                ->join('edu_assessments', function ($join): void {
                    $join->on('edu_grades.assessment_id', '=', 'edu_assessments.id')
                        ->on('edu_grades.company_id', '=', 'edu_assessments.company_id');
                })
                ->where('edu_grades.company_id', $companyId)
                ->where('edu_grades.student_id', $student->id)
                ->where('edu_grades.status', 'published')
                ->where('edu_assessments.class_id', $classId)
                ->where('edu_assessments.academic_year_id', $academicYearId)
                ->whereBetween('edu_assessments.assessment_date', [$start, $end])
                ->orderBy('edu_assessments.assessment_date')
                ->orderBy('edu_assessments.id')
                ->orderBy('edu_grades.id')
                ->get(['edu_assessments.subject_id', 'edu_grades.score']);

            return array_values($rows->map(function (Model $row): array {
                return $this->normalizeGradeRow(
                    (int) $row->getAttribute('subject_id'),
                    $row->getAttribute('score'),
                );
            })->all());
        }

        if (schemaTableExists('edu_grades') && schemaTableExists('edu_assessments')) {
            $rows = DB::table('edu_grades')
                ->join('edu_assessments', function ($join): void {
                    $join->on('edu_grades.assessment_id', '=', 'edu_assessments.id')
                        ->on('edu_grades.company_id', '=', 'edu_assessments.company_id');
                })
                ->where('edu_grades.company_id', $companyId)
                ->where('edu_grades.student_id', $student->id)
                ->where('edu_grades.status', 'published')
                ->where('edu_assessments.class_id', $classId)
                ->where('edu_assessments.academic_year_id', $academicYearId)
                ->whereBetween('edu_assessments.assessment_date', [$start, $end])
                ->orderBy('edu_assessments.assessment_date')
                ->orderBy('edu_assessments.id')
                ->orderBy('edu_grades.id')
                ->get(['edu_assessments.subject_id', 'edu_grades.score']);

            return array_values(array_map(function (object $row): array {
                return $this->normalizeGradeRow(
                    (int) $row->subject_id,
                    $row->score,
                );
            }, $rows->all()));
        }

        // Fail-closed : notes non livrées (EDU-007 parallèle) → bulletin vide
        // mais généré (statut draft, snapshot vide, average_score null).
        return [];
    }

    /**
     * @return array{subject_id: int, score: float}
     */
    private function normalizeGradeRow(int $subjectId, mixed $score): array
    {
        return [
            'subject_id' => $subjectId,
            'score' => (float) $score,
        ];
    }

    /**
     * Best-effort : nom de la matière (affichage du snapshot). EduSubject est
     * livré par EDU-003 (#5819) — s'il manque, subject_name reste null.
     */
    private function subjectName(int $subjectId, string $companyId): ?string
    {
        /** @var class-string<Model> $subjectModel */
        $subjectModel = 'App\Modules\EduManager\Domain\Models\EduSubject';

        if (! class_exists($subjectModel)) {
            return null;
        }

        $name = $subjectModel::query()
            ->whereKey($subjectId)
            ->where('company_id', $companyId)
            ->value('name');

        return $name !== null ? (string) $name : null;
    }

    /**
     * Best-effort : l'année scolaire doit exister chez le MÊME tenant. Tant
     * que EduAcademicYear n'existe pas, contrôle sauté.
     */
    private function assertAcademicYearInTenant(int $academicYearId, string $companyId): void
    {
        if ($academicYearId <= 0) {
            return;
        }

        /** @var class-string<Model> $yearModel */
        $yearModel = 'App\Modules\EduManager\Domain\Models\EduAcademicYear';

        if (! class_exists($yearModel)) {
            return;
        }

        $exists = $yearModel::query()
            ->whereKey($academicYearId)
            ->where('company_id', $companyId)
            ->exists();

        if (! $exists) {
            throw (new ModelNotFoundException)->setModel($yearModel, $academicYearId);
        }
    }

    /**
     * Best-effort : la classe doit exister chez le MÊME tenant (jamais la
     * classe d'un autre tenant pour l'élève). Tant que EduClass n'existe
     * pas, contrôle sauté.
     */
    private function assertClassInTenant(int $classId, string $companyId): void
    {
        if ($classId <= 0) {
            return;
        }

        /** @var class-string<Model> $classModel */
        $classModel = 'App\Modules\EduManager\Domain\Models\EduClass';

        if (! class_exists($classModel)) {
            return;
        }

        $exists = $classModel::query()
            ->whereKey($classId)
            ->where('company_id', $companyId)
            ->exists();

        if (! $exists) {
            throw (new ModelNotFoundException)->setModel($classModel, $classId);
        }
    }
}
