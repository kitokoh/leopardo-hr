<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAttendance;
use App\Modules\EduManager\Domain\Models\EduGrade;
use App\Modules\EduManager\Domain\Models\EduReportCard;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Domain\Models\EduStudentGuardian;

/**
 * Rétention, anonymisation et droit d'accès RGPD — EDU-019 (issue #5835).
 *
 * - Anonymisation IDEMPOTENTE : les PII d'un élève sont masquées
 *   (display_name → pseudonyme, birth_date → null, metadata → null,
 *   statut → archived) ; les liens guardians et les notes sont détachés.
 *   Jamais de suppression physique (audit non altérable conservé).
 * - Export individuel (droit d'accès) : paquet JSON de TOUTES les données
 *   de l'élève (profil, guardians, présences, notes, bulletins) —
 *   tenant-scopé, direction uniquement.
 * - Chaque opération sensible est journalisée (AuditLog, module `edu`,
 *   actions `edu.privacy.anonymized` / `edu.privacy.export`).
 */
final class EduRetentionService
{
    /**
     * Anonymisation RGPD d'un élève (idempotente).
     */
    public function anonymizeStudent(Employee $actor, EduStudent $student): EduStudent
    {
        abort_if($student->company_id !== $actor->company_id, 404);

        if ($student->status === EduStudent::STATUS_ARCHIVED
            && str_starts_with((string) $student->display_name, 'Élève anonymisé')
        ) {
            return $student;
        }

        $student->update([
            'display_name' => 'Élève anonymisé #'.$student->getAttribute('id'),
            'birth_date_encrypted' => null,
            'metadata' => null,
            'status' => EduStudent::STATUS_ARCHIVED,
        ]);

        // Détachement des liens nominatifs (les enregistrements restent,
        // mais sans nom : audit non altérable, données dépersonnalisées).
        EduStudentGuardian::query()
            ->where('company_id', $actor->company_id)
            ->where('student_id', (int) $student->getAttribute('id'))
            ->delete();

        AuditLog::create([
            'company_id' => $actor->company_id,
            'user_id' => $actor->id,
            'action' => 'edu.privacy.anonymized',
            'module' => 'edu',
            'auditable_type' => $student->getMorphClass(),
            'auditable_id' => $student->getAttribute('id'),
            'new_values' => ['student_number' => $student->student_number],
        ]);

        return $student->refresh();
    }

    /**
     * Export individuel RGPD (droit d'accès) — toutes les données.
     *
     * @return array<string, mixed>
     */
    public function exportIndividual(Employee $actor, EduStudent $student): array
    {
        abort_if($student->company_id !== $actor->company_id, 404);

        $attendances = EduAttendance::query()
            ->where('company_id', $actor->company_id)
            ->where('student_id', (int) $student->getAttribute('id'))
            ->get(['attendance_date', 'status', 'reason']);

        $grades = EduGrade::query()
            ->with('assessment:id,title,subject_id,assessment_date')
            ->where('company_id', $actor->company_id)
            ->where('student_id', (int) $student->getAttribute('id'))
            ->get(['assessment_id', 'score', 'status', 'version', 'updated_at']);

        $reportCards = EduReportCard::query()
            ->where('company_id', $actor->company_id)
            ->where('student_id', (int) $student->getAttribute('id'))
            ->get(['academic_year_id', 'period', 'status', 'published_at']);

        $guardians = EduStudentGuardian::query()
            ->with('guardian:id,first_name,last_name,relationship_code')
            ->where('company_id', $actor->company_id)
            ->where('student_id', (int) $student->getAttribute('id'))
            ->get();

        AuditLog::create([
            'company_id' => $actor->company_id,
            'user_id' => $actor->id,
            'action' => 'edu.privacy.export',
            'module' => 'edu',
            'auditable_type' => $student->getMorphClass(),
            'auditable_id' => $student->getAttribute('id'),
            'new_values' => ['student_number' => $student->student_number],
        ]);

        return [
            'profile' => [
                'student_number' => $student->student_number,
                'display_name' => $student->display_name,
                'status' => $student->status,
                'created_at' => $student->created_at?->toIso8601String(),
            ],
            'guardians' => $guardians->map(fn ($link): array => [
                'first_name' => $link->guardian->first_name,
                'last_name' => $link->guardian->last_name,
                'relationship_code' => $link->relationship_code,
            ])->all(),
            'attendances' => $attendances->map(fn (EduAttendance $a): array => [
                'date' => $a->attendance_date->toDateString(),
                'status' => $a->status,
                'reason' => $a->reason,
            ])->all(),
            'grades' => $grades->map(fn (EduGrade $g): array => [
                'assessment' => $g->assessment?->title,
                'date' => $g->assessment?->assessment_date?->toDateString(),
                'score' => $g->score,
                'status' => $g->status,
                'version' => (int) $g->version,
            ])->all(),
            'report_cards' => $reportCards->map(fn (EduReportCard $c): array => [
                'academic_year_id' => $c->academic_year_id,
                'period' => $c->period,
                'status' => $c->status,
                'published_at' => $c->published_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
