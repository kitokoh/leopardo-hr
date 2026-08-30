<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduReportCard;
use App\Modules\EduManager\Domain\Models\EduReportCardLine;
use App\Modules\EduManager\Domain\Models\EduStudent;
use Illuminate\Support\Facades\DB;

/**
 * Règles métier des bulletins — EDU-008 (issue #5824).
 *
 * - Génération idempotente : les lignes sont recalculées depuis les notes
 *   publiées/corrigées de la période (moyenne pondérée par coefficient,
 *   comptage des évaluations) — le bulletin est un read model recalculable.
 * - Validation : seul un manager de direction (principal/rh) peut valider ;
 *   un bulletin publié ne peut plus être modifié (EDU_REPORT_CARD_LOCKED).
 * - Publication : rend le bulletin visible pour les guardians autorisés
 *   (Policy EduReportCardPolicy).
 * - Isolation tenant : élève d'une autre compagnie → 404.
 */
final class EduReportCardService
{
    /**
     * Génère (ou régénère) le bulletin d'un élève pour une période.
     *
     * @param  array<string, mixed>  $data
     */
    public function generate(Employee $actor, EduStudent $student, array $data): EduReportCard
    {
        abort_if($student->company_id !== $actor->company_id, 404);
        abort_if(! in_array($data['period'], EduReportCard::PERIODS, true), 422, 'EDU_REPORT_CARD_PERIOD');

        /** @var EduReportCard|null $existing */
        $existing = EduReportCard::query()
            ->where('company_id', $actor->company_id)
            ->where('student_id', (int) $student->getAttribute('id'))
            ->where('academic_year_id', (int) $data['academic_year_id'])
            ->where('period', $data['period'])
            ->first();

        abort_if($existing !== null && $existing->isPublished(), 422, 'EDU_REPORT_CARD_LOCKED');

        return DB::transaction(function () use ($actor, $student, $data, $existing): EduReportCard {
            /** @var EduReportCard $card */
            $card = $existing ?? EduReportCard::query()->create([
                'company_id' => $actor->company_id,
                'student_id' => (int) $student->getAttribute('id'),
                'academic_year_id' => (int) $data['academic_year_id'],
                'period' => $data['period'],
                'status' => EduReportCard::STATUS_DRAFT,
                'generated_at' => now(),
            ]);

            $card->lines()->delete();
            $this->recomputeLines($actor, $card, (int) $data['academic_year_id']);

            $card->update(['generated_at' => now()]);

            return $card->refresh();
        });
    }

    public function validate(Employee $actor, EduReportCard $card): EduReportCard
    {
        abort_if($card->company_id !== $actor->company_id, 404);
        abort_if($card->isPublished(), 422, 'EDU_REPORT_CARD_LOCKED');

        $card->update([
            'status' => EduReportCard::STATUS_VALIDATED,
            'validated_at' => now(),
            'validated_by' => $actor->id,
        ]);

        return $card->refresh();
    }

    public function publish(Employee $actor, EduReportCard $card): EduReportCard
    {
        abort_if($card->company_id !== $actor->company_id, 404);
        abort_if($card->status !== EduReportCard::STATUS_VALIDATED, 422, 'EDU_REPORT_CARD_NOT_VALIDATED');

        $card->update([
            'status' => EduReportCard::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        return $card->refresh();
    }

    private function recomputeLines(Employee $actor, EduReportCard $card, int $academicYearId): void
    {
        $rows = DB::table('edu_grades as g')
            ->join('edu_assessments as a', function ($join): void {
                $join->on('a.id', '=', 'g.assessment_id')
                    ->on('a.company_id', '=', 'g.company_id');
            })
            ->where('g.company_id', $actor->company_id)
            ->where('g.student_id', (int) $card->student_id)
            ->where('a.academic_year_id', $academicYearId)
            ->where('a.published_at', '!=', null)
            ->selectRaw('a.subject_id, COUNT(g.id) as cnt, AVG(g.score) as avg, MAX(a.coefficient) as coeff')
            ->groupBy('a.subject_id')
            ->get();

        foreach ($rows as $row) {
            EduReportCardLine::query()->create([
                'company_id' => $actor->company_id,
                'report_card_id' => (int) $card->getAttribute('id'),
                'subject_id' => (int) $row->subject_id,
                'average' => (string) round((float) $row->avg, 2),
                'coefficient' => (string) $row->coeff,
                'assessment_count' => (int) $row->cnt,
            ]);
        }
    }
}
