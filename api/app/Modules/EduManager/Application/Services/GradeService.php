<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Services;

use App\Core\Tenant\Domain\Exceptions\TenantContextMissingException;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Domain\Models\EduGradeVersion;
use App\Modules\EduManager\Domain\Models\EduStudent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Issue #5823 (EDU-007) — notes et évaluations VERSIONNÉES.
 *
 * - `recordGrade()` : saisit (ou met à jour tant que brouillon) la note
 *   d'un élève pour une évaluation. Refusée dès que l'évaluation est
 *   publiée ; une note déjà publiée n'est JAMAIS écrasée (correction via
 *   `correctGrade()` uniquement).
 * - `publishAssessment()` : publie TOUTES les notes brouillon d'une
 *   évaluation de façon ATOMIQUE (transaction) et verrouille l'évaluation.
 * - `correctGrade()` : corrige une note avec justification. Si la note est
 *   publiée, une ligne `edu_grade_versions` (previous → new + motif +
 *   acteur) est écrite AVANT la modification, dans la même transaction :
 *   jamais d'écrasement silencieux, audit complet et rejouable (spec §6.3).
 *
 * PII minimisée (spec §6.3) : `comment` et `reason` sont des zones BORNÉES
 * (255 caractères) — un texte libre non borné susceptible de porter des
 * données sensibles est rejeté côté serveur.
 *
 * Toutes les vérifications sont bornées au tenant : une évaluation (ou un
 * élève) d'un autre tenant → refus avant toute écriture
 * (TenantContextMissingException / ModelNotFoundException).
 */
final class GradeService
{
    /**
     * Enregistre la note d'un élève pour une évaluation (brouillon).
     *
     * - L'évaluation doit appartenir au tenant courant et ne pas être
     *   publiée (notes immuables après publication — utiliser
     *   `correctGrade()`).
     * - L'élève doit exister chez le MÊME tenant que l'évaluation et être
     *   inscrit dans sa classe (contrôle best-effort, pattern
     *   AttendanceService) ; le score est borné [0, max_score] et le
     *   commentaire limité à 255 caractères (PII minimisée).
     * - IDEMPOTENT : une note brouillon existante pour (évaluation, élève)
     *   est mise à jour en place (modifiable tant que non publiée) ; une
     *   note publiée est refusée (immuable).
     *
     * @throws InvalidArgumentException      évaluation publiée / score hors
     *                                       barème / commentaire > 255
     * @throws ModelNotFoundException        élève introuvable dans le tenant
     * @throws TenantContextMissingException évaluation d'un autre tenant
     */
    public function recordGrade(
        EduAssessment $assessment,
        int $studentId,
        float $score,
        ?string $comment,
        int $actorId,
    ): EduGrade {
        $this->assertSameTenant($assessment);

        if ($assessment->status === EduAssessment::STATUS_PUBLISHED) {
            throw new InvalidArgumentException(
                "L'évaluation est publiée : ses notes sont immuables (correction via correctGrade uniquement)."
            );
        }

        $score = $this->normalizeScore($score);
        $student = $this->studentInTenant($studentId, $assessment->company_id);
        $this->assertStudentInClass($student, (int) $assessment->class_id);
        $this->assertScoreValid($score, (float) $assessment->max_score);
        $this->assertCommentValid($comment);

        /** @var EduGrade|null $existing */
        $existing = EduGrade::query()
            ->where('company_id', $assessment->company_id)
            ->where('assessment_id', $assessment->id)
            ->where('student_id', $studentId)
            ->first();

        if ($existing !== null) {
            // Note publiée : immuable — jamais d'écrasement silencieux.
            if ($existing->status === EduGrade::STATUS_PUBLISHED) {
                throw new InvalidArgumentException(
                    'La note est publiée : correction via correctGrade uniquement.'
                );
            }

            // Brouillon : modifiable directement (aucun versionnage requis).
            $existing->update([
                'score' => $score,
                'comment' => $comment !== null && $comment !== '' ? $comment : null,
                'graded_by' => $actorId,
            ]);

            return $existing->refresh();
        }

        /** @var EduGrade $grade */
        $grade = EduGrade::query()->create([
            'company_id' => $assessment->company_id,
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'score' => $score,
            'comment' => $comment !== null && $comment !== '' ? $comment : null,
            'status' => EduGrade::STATUS_DRAFT,
            'graded_by' => $actorId,
        ]);

        return $grade;
    }

    /**
     * Publie une évaluation : toutes les notes brouillon passent en
     * `published` (graded_at horodaté) et l'évaluation est verrouillée
     * (status published + published_at), le tout de façon ATOMIQUE.
     *
     * Idempotente : une évaluation déjà publiée est un no-op (aucune
     * réécriture, aucune erreur). Les notes déjà publiées sont laissées
     * intactes. `$actorId` est réservé au traçage d'audit futur (le journal
     * EduGradeVersion enregistre l'acteur des corrections ; la publication
     * elle-même est horodatée via `published_at`).
     */
    public function publishAssessment(EduAssessment $assessment, int $actorId): void
    {
        $this->assertSameTenant($assessment);

        if ($assessment->status === EduAssessment::STATUS_PUBLISHED) {
            return;
        }

        DB::transaction(function () use ($assessment): void {
            EduGrade::query()
                ->where('company_id', $assessment->company_id)
                ->where('assessment_id', $assessment->id)
                ->where('status', EduGrade::STATUS_DRAFT)
                ->update([
                    'status' => EduGrade::STATUS_PUBLISHED,
                    'graded_at' => now(),
                ]);

            $assessment->update([
                'status' => EduAssessment::STATUS_PUBLISHED,
                'published_at' => now(),
            ]);
        });
    }

    /**
     * Corrige une note (score) avec justification.
     *
     * Si la note est PUBLIÉE : une ligne `edu_grade_versions` est écrite
     * AVANT la modification (previous_score → new_score + justification +
     * acteur + horodatage), dans la même transaction — l'historique des
     * corrections est complet et rejouable. Si la note est encore un
     * brouillon, la valeur est modifiée directement (aucun versionnage
     * requis). Le score reste borné [0, max_score] et la justification
     * limitée à 255 caractères (PII minimisée).
     *
     * @throws InvalidArgumentException      justification manquante ou
     *                                       > 255 / score hors barème
     * @throws TenantContextMissingException note d'un autre tenant
     */
    public function correctGrade(EduGrade $grade, float $newScore, string $reason, int $actorId): EduGrade
    {
        $this->assertSameTenant($grade);
        $this->assertReasonValid($reason);

        $newScore = $this->normalizeScore($newScore);
        $this->assertScoreValid($newScore, $this->maxScoreOf($grade));

        DB::transaction(function () use ($grade, $newScore, $reason, $actorId): void {
            if ($grade->status === EduGrade::STATUS_PUBLISHED) {
                // Versionnage AVANT mutation : la version documente l'état
                // précédent tel qu'il existe en base au moment de l'écriture.
                EduGradeVersion::query()->create([
                    'company_id' => $grade->company_id,
                    'grade_id' => $grade->id,
                    'previous_score' => $grade->score,
                    'new_score' => $newScore,
                    'previous_status' => $grade->status,
                    'new_status' => EduGrade::STATUS_PUBLISHED,
                    'reason' => $reason,
                    'changed_by' => $actorId,
                    'changed_at' => now(),
                ]);
            }

            $grade->score = $newScore;
            $grade->save();
        });

        return $grade->refresh();
    }

    /**
     * Élève du MÊME tenant que l'évaluation — jamais de note cross-tenant.
     *
     * @throws ModelNotFoundException élève absent du tenant (404)
     */
    private function studentInTenant(int $studentId, string $companyId): EduStudent
    {
        /** @var EduStudent|null $student */
        $student = EduStudent::query()
            ->whereKey($studentId)
            ->where('company_id', $companyId)
            ->first();

        if ($student === null) {
            throw (new ModelNotFoundException)->setModel(EduStudent::class, $studentId);
        }

        return $student;
    }

    /**
     * Best-effort : l'élève doit être inscrit dans la classe de
     * l'évaluation. Le lien d'inscription n'est pas encore livré par le
     * module — tant que la relation `classes()` n'existe pas sur
     * EduStudent, contrôle sauté (pattern AttendanceService).
     */
    private function assertStudentInClass(EduStudent $student, int $classId): void
    {
        if ($classId <= 0 || ! method_exists($student, 'classes')) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Builder $classesQuery */
        $classesQuery = $student->{'classes'}();

        if (! $classesQuery->whereKey($classId)->exists()) {
            throw (new ModelNotFoundException)->setModel('App\Modules\EduManager\Domain\Models\EduClass', $classId);
        }
    }

    /**
     * Score borné [0, max_score] — valeurs et échelles validées côté
     * serveur (spec §6.3), en complément des CHECK en base.
     */
    private function assertScoreValid(float $score, float $maxScore): void
    {
        if ($score < 0) {
            throw new InvalidArgumentException('La note ne peut pas être négative.');
        }

        if ($score > $maxScore) {
            throw new InvalidArgumentException(
                sprintf('La note (%s) dépasse le barème de l\'évaluation (%s).', $score, $maxScore)
            );
        }
    }

    /**
     * PII minimisée : commentaire borné à 255 caractères — jamais de zone
     * libre non bornée susceptible de porter des données sensibles.
     */
    private function assertCommentValid(?string $comment): void
    {
        if ($comment !== null && mb_strlen($comment) > 255) {
            throw new InvalidArgumentException('Le commentaire est limité à 255 caractères (PII minimisée).');
        }
    }

    /**
     * Justification obligatoire et bornée (audit rejouable, PII minimisée).
     */
    private function assertReasonValid(string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Une justification est requise pour corriger une note (audit).');
        }

        if (mb_strlen($reason) > 255) {
            throw new InvalidArgumentException('La justification est limitée à 255 caractères (PII minimisée).');
        }
    }

    /**
     * Barème de l'évaluation de la note — requête explicitement bornée au
     * tenant de la note (indépendante du scope global courant).
     */
    private function maxScoreOf(EduGrade $grade): float
    {
        /** @var EduAssessment|null $assessment */
        $assessment = EduAssessment::query()
            ->whereKey($grade->assessment_id)
            ->where('company_id', $grade->company_id)
            ->first(['id', 'max_score']);

        if ($assessment === null) {
            throw (new ModelNotFoundException)->setModel(EduAssessment::class, (int) $grade->assessment_id);
        }

        return (float) $assessment->max_score;
    }

    /**
     * Normalise la précision à 2 décimales AVANT validation/écriture :
     * cohérent avec numeric(6,2) (qui arrondit silencieusement en base) et
     * évite les artefacts de virgule flottante côté comparaison.
     */
    private function normalizeScore(float $score): float
    {
        return (float) round($score, 2);
    }

    /**
     * Garde tenant : le modèle doit appartenir à la compagnie courante.
     *
     * @throws TenantContextMissingException modèle d'un autre tenant (403)
     */
    private function assertSameTenant(Model $model): void
    {
        if ($model->company_id !== currentCompany()->id) {
            throw new TenantContextMissingException;
        }
    }
}
