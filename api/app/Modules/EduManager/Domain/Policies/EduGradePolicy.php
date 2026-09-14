<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Access\EduAccess;
use App\Modules\EduManager\Domain\Models\EduAssessment;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Domain\Models\EduTeacher;

/**
 * Issue #5823 (EDU-007) — Policy des notes (grades).
 *
 * - viewAny : gestionnaire du tenant OU enseignant (profil EduTeacher) ;
 * - view : gestionnaire (tenant) OU enseignant de la CLASSE de l'évaluation
 *   de la note ;
 * - create : gestionnaire OU enseignant (saisie de notes, tant que
 *   l'évaluation n'est pas publiée — le verrou métier est porté par
 *   GradeService) ;
 * - update : gestionnaire OU enseignant, borné au tenant, UNIQUEMENT tant
 *   que la note n'est pas publiée — une note publiée est immuable
 *   (update refusé) ;
 * - correct : correction AUDITABLE d'une note publiée
 *   (GradeService::correctGrade versionne AVANT de modifier) — réservée
 *   aux gestionnaires du tenant.
 *
 * Un employé sans rôle gestionnaire ni profil enseignant n'a aucun accès.
 */
class EduGradePolicy
{
    public const MANAGER_ROLES = ['principal', 'rh', 'manager'];

    public function viewAny(Employee $actor): bool
    {
        return $this->isManager($actor) || $this->isTeacher($actor);
    }

    public function view(Employee $actor, EduGrade $grade): bool
    {
        if ($grade->company_id !== $actor->company_id) {
            return false;
        }

        if ($this->isManager($actor)) {
            return true;
        }

        return $this->isTeacher($actor) && $this->teachesAssessment($actor, $grade);
    }

    public function create(Employee $actor): bool
    {
        return $this->isManager($actor) || $this->isTeacher($actor);
    }

    public function update(Employee $actor, EduGrade $grade): bool
    {
        if ($grade->company_id !== $actor->company_id) {
            return false;
        }

        // Note publiée = IMMUABLE hors correction auditable (correct()).
        if ($grade->status === EduGrade::STATUS_PUBLISHED) {
            return false;
        }

        if ($this->isManager($actor)) {
            return true;
        }

        return $this->isTeacher($actor) && $this->teachesAssessment($actor, $grade);
    }

    /**
     * Correction d'une note publiée : opération contrôlée avec
     * justification, audit et version (spec §6.3) — gestionnaire du tenant.
     */
    public function correct(Employee $actor, EduGrade $grade): bool
    {
        if ($grade->company_id !== $actor->company_id) {
            return false;
        }

        if ($this->isManager($actor)) {
            return true;
        }

        // Correction d'une note publiée : le TITULAIRE de la classe (il
        // conduit la pédagogie et assume la correction), jamais l'enseignant
        // qui n'y assure qu'une séance (verrouillé par
        // `EduRbacMatrixTest::test_grade_policy_manager_draft_published_and_
        // cross_tenant`).
        return EduAccess::isClassReferent($actor, $this->assessmentClassId($grade));
    }

    private function isManager(Employee $actor): bool
    {
        return $actor->hasManagerRole(...self::MANAGER_ROLES);
    }

    /**
     * Enseignant : lien EduTeacher → employee_id dans SON tenant.
     */
    private function isTeacher(Employee $actor): bool
    {
        return EduAccess::isTeacher($actor);
    }

    /**
     * Best-effort : l'enseignant ne voit/modifie QUE les notes des classes
     * qui lui sont affectées (lien edu_timetable_slots, EDU-006 #5822).
     * Sans lien exploitable, refus (fail-closed).
     */
    private function teachesAssessment(Employee $actor, EduGrade $grade): bool
    {
        return EduAccess::teachesClass($actor, $this->assessmentClassId($grade));
    }

    /**
     * Classe de l'évaluation de la note — requête explicitement bornée au
     * tenant de la note (indépendante du scope global courant).
     */
    private function assessmentClassId(EduGrade $grade): int
    {
        /** @var EduAssessment|null $assessment */
        $assessment = EduAssessment::query()
            ->whereKey($grade->assessment_id)
            ->where('company_id', $grade->company_id)
            ->first(['id', 'class_id']);

        return $assessment === null ? 0 : (int) $assessment->class_id;
    }
}
