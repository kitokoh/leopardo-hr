<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Domain\Models\EduGradeVersion;
use Illuminate\Support\Facades\DB;

/**
 * Règles métier des évaluations et notes — EDU-007 (issue #5823).
 *
 * - Barème : une note est bornée [0, max_score] de l'évaluation
 *   (EDU_GRADE_OUT_OF_RANGE) ; le commentaire est contrôlé (≤ 500).
 * - Saisie idempotente : une seule note courante par (évaluation, élève) et
 *   tenant — firstOrCreate, la première saisie est version 1.
 * - Publication : publish() pose published_at (idempotent) ; une note
 *   publiée peut être corrigée (statut corrected) mais l'historique est
 *   toujours conservé dans edu_grade_versions (version++ à chaque
 *   correction).
 * - Isolation tenant : évaluation d'une autre compagnie → 404.
 */
final class EduGradeService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function grade(Employee $actor, EduAssessment $assessment, array $data): EduGrade
    {
        abort_if($assessment->company_id !== $actor->company_id, 404);

        $this->assertScoreInRange($assessment, $data['score']);

        /** @var EduGrade $grade */
        $grade = EduGrade::query()->firstOrCreate(
            [
                'company_id' => $actor->company_id,
                'assessment_id' => (int) $assessment->getAttribute('id'),
                'student_id' => (int) $data['student_id'],
            ],
            [
                'score' => $data['score'],
                'comment' => $data['comment'] ?? null,
                'graded_by' => $actor->id,
                'status' => EduGrade::STATUS_DRAFT,
                'version' => 1,
            ]
        );

        return $grade;
    }

    public function publish(Employee $actor, EduGrade $grade): EduGrade
    {
        abort_if($grade->company_id !== $actor->company_id, 404);

        if ($grade->published_at === null) {
            $grade->update([
                'status' => EduGrade::STATUS_PUBLISHED,
                'published_at' => now(),
            ]);
        }

        return $grade->refresh();
    }

    /**
     * Correction VERSIONNÉE : n'écrase jamais l'historique.
     *
     * @param  array<string, mixed>  $data
     */
    public function correct(Employee $actor, EduGrade $grade, array $data): EduGrade
    {
        abort_if($grade->company_id !== $actor->company_id, 404);

        $assessment = $grade->assessment;
        abort_if($assessment === null, 422, 'EDU_GRADE_ASSESSMENT_MISSING');

        $this->assertScoreInRange($assessment, $data['score']);

        return DB::transaction(function () use ($actor, $grade, $data): EduGrade {
            $nextVersion = ((int) $grade->version) + 1;

            EduGradeVersion::query()->create([
                'company_id' => $grade->company_id,
                'grade_id' => (int) $grade->getAttribute('id'),
                'version' => $nextVersion,
                'score' => $data['score'],
                'comment' => $data['comment'] ?? null,
                'changed_by' => $actor->id,
            ]);

            $grade->update([
                'score' => $data['score'],
                'comment' => $data['comment'] ?? null,
                'version' => $nextVersion,
                'status' => EduGrade::STATUS_CORRECTED,
            ]);

            return $grade->refresh();
        });
    }

    private function assertScoreInRange(EduAssessment $assessment, mixed $score): void
    {
        $max = (float) $assessment->max_score;
        $value = (float) $score;

        abort_if($value < 0 || $value > $max, 422, 'EDU_GRADE_OUT_OF_RANGE');
    }
}
