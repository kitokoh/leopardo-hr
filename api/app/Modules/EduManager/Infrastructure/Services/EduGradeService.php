<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduEvaluation;
use App\Modules\EduManager\Domain\Models\EduGradeEntry;
use Illuminate\Support\Facades\DB;

/**
 * Évaluations & notes VERSIONNÉES — EDU-007 (issue #5823).
 *
 * - Une note non publiée (draft) est modifiable en place selon le rôle.
 * - Une note PUBLIÉE est IMMUABLE : la correction insère une NOUVELLE
 *   VERSION (append-only) avec motif — audit complet, rejeu impossible.
 * - Publication d'évaluation idempotente (déjà publiée → état inchangé).
 * - Zéro fuite tenant : tout est scopé company_id.
 */
final class EduGradeService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function enter(Employee $actor, EduEvaluation $evaluation, array $data): EduGradeEntry
    {
        if ($evaluation->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        if ($evaluation->status === EduEvaluation::STATUS_PUBLISHED) {
            abort(422, 'EVALUATION_PUBLISHED_READONLY');
        }

        $studentId = isset($data['student_id']) && is_numeric($data['student_id']) ? (int) $data['student_id'] : 0;
        $this->assertStudentInTenant((string) $actor->company_id, $studentId);

        $score = is_numeric($data['score'] ?? null) ? (float) $data['score'] : 0.0;
        $maxScore = (float) $evaluation->max_score;

        if ($score < 0 || $score > $maxScore) {
            abort(422, 'SCORE_OUT_OF_RANGE');
        }

        /** @var EduGradeEntry|null $existing */
        $existing = EduGradeEntry::query()
            ->where('company_id', $evaluation->company_id)
            ->where('evaluation_id', $evaluation->id)
            ->where('student_id', $studentId)
            ->where('version', 1)
            ->first();

        if ($existing instanceof EduGradeEntry) {
            $existing->forceFill([
                'score' => $score,
                'comment' => $data['comment'] ?? $existing->comment,
                'entered_by' => $actor->id,
            ])->save();

            return $existing->refresh();
        }

        return EduGradeEntry::query()->create([
            'company_id' => $evaluation->company_id,
            'evaluation_id' => $evaluation->id,
            'student_id' => $studentId,
            'score' => $score,
            'status' => EduGradeEntry::STATUS_DRAFT,
            'comment' => $data['comment'] ?? null,
            'version' => 1,
            'entered_by' => $actor->id,
        ]);
    }

    /**
     * Correction d'une note publiée → nouvelle version (immuabilité).
     *
     * @param  array<string, mixed>  $data
     */
    public function correctPublished(Employee $actor, EduGradeEntry $entry, array $data): EduGradeEntry
    {
        if ($entry->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $evaluation = EduEvaluation::query()->find($entry->evaluation_id);

        if (! $evaluation instanceof EduEvaluation) {
            abort(422, 'EVALUATION_NOT_FOUND');
        }

        $score = is_numeric($data['score'] ?? null) ? (float) $data['score'] : (float) $entry->score;
        $reason = is_string($data['correction_reason'] ?? null) ? $data['correction_reason'] : '';

        if (trim($reason) === '') {
            abort(422, 'CORRECTION_REASON_REQUIRED');
        }

        if ($score < 0 || $score > (float) $evaluation->max_score) {
            abort(422, 'SCORE_OUT_OF_RANGE');
        }

        $rawMax = EduGradeEntry::query()
            ->where('company_id', $entry->company_id)
            ->where('evaluation_id', $entry->evaluation_id)
            ->where('student_id', $entry->student_id)
            ->max('version');
        $maxVersion = is_numeric($rawMax) ? (int) $rawMax : 0;

        return EduGradeEntry::query()->create([
            'company_id' => $entry->company_id,
            'evaluation_id' => $entry->evaluation_id,
            'student_id' => $entry->student_id,
            'score' => $score,
            'status' => EduGradeEntry::STATUS_PUBLISHED,
            'comment' => $data['comment'] ?? $entry->comment,
            'version' => $maxVersion + 1,
            'entered_by' => $actor->id,
            'correction_reason' => $reason,
            'corrected_by' => $actor->id,
            'corrected_at' => now(),
        ]);
    }

    public function publish(Employee $actor, EduEvaluation $evaluation): EduEvaluation
    {
        if ($evaluation->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        if ($evaluation->status === EduEvaluation::STATUS_PUBLISHED) {
            return $evaluation->refresh(); // idempotent
        }

        $evaluation->forceFill([
            'status' => EduEvaluation::STATUS_PUBLISHED,
            'published_by' => $actor->id,
            'published_at' => now(),
        ])->save();

        // Publie les notes draft en cours (immuabilité ensuite).
        EduGradeEntry::query()
            ->where('company_id', $evaluation->company_id)
            ->where('evaluation_id', $evaluation->id)
            ->where('status', EduGradeEntry::STATUS_DRAFT)
            ->update(['status' => EduGradeEntry::STATUS_PUBLISHED]);

        return $evaluation->refresh();
    }

    private function assertStudentInTenant(string $companyId, int $studentId): void
    {
        if (! DB::getSchemaBuilder()->hasTable('edu_students')) {
            return;
        }

        $exists = DB::table('edu_students')
            ->where('company_id', $companyId)
            ->where('id', $studentId)
            ->exists();

        abort_if(! $exists, 422, 'STUDENT_OUTSIDE_TENANT');
    }
}
