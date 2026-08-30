<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Application\Services;

use App\Modules\EduManager\Domain\Models\EduAttendanceCorrection;
use App\Modules\EduManager\Domain\Models\EduAttendanceRecord;
use App\Modules\EduManager\Domain\Models\EduStudent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Issue #5821 (EDU-005) — service de présence scolaire.
 *
 * - `record()` : enregistre la présence d'un élève pour une classe et une
 *   date. Idempotent (UNIQUE company+class+student+date) : un second appel
 *   sur la même clé renvoie l'enregistrement existant sans rien écraser —
 *   modifier un statut passe obligatoirement par `correct()` (versionné).
 * - `correct()` : VERSIONNE la correction — écrit une ligne
 *   edu_attendance_corrections (previous → new + motif) AVANT de modifier
 *   le record, dans une transaction. Jamais d'écrasement silencieux.
 *
 * Toutes les vérifications sont bornées au tenant : un élève (ou une
 * classe) d'un autre tenant → ModelNotFoundException (404 à la surface
 * API). Les contrôles de classe (existence dans le tenant, appartenance
 * élève → classe) sont best-effort : EduClass et le lien d'inscription
 * sont livrés par EDU-003 (#5819) — tant qu'ils n'existent pas, seul le
 * contrôle élève (edu_students, EDU-002) s'applique.
 */
final class AttendanceService
{
    /**
     * Enregistre (ou récupère, idempotent) la présence d'un élève.
     *
     * @param  array<string, mixed>  $data  company_id, class_id, student_id,
     *                                      attendance_date (Y-m-d), status,
     *                                      reason_code?, note?, recorded_by?
     *
     * @throws InvalidArgumentException  statut inconnu / company_id manquant /
     *                                   reason_code trop long
     * @throws ModelNotFoundException    élève (ou classe) introuvable dans le tenant
     */
    public function record(array $data): EduAttendanceRecord
    {
        $companyId = (string) ($data['company_id'] ?? $this->currentCompanyId());
        if ($companyId === '') {
            throw new InvalidArgumentException('Le company_id est requis pour enregistrer une présence.');
        }

        $status = (string) ($data['status'] ?? '');
        if (! in_array($status, EduAttendanceRecord::STATUSES, true)) {
            throw new InvalidArgumentException(sprintf('Statut de présence invalide : %s.', $status));
        }

        $reasonCode = isset($data['reason_code']) && $data['reason_code'] !== null
            ? (string) $data['reason_code']
            : null;
        if ($reasonCode !== null && mb_strlen($reasonCode) > 30) {
            throw new InvalidArgumentException('Le reason_code est limité à 30 caractères.');
        }

        $studentId = (int) ($data['student_id'] ?? 0);
        $classId = (int) ($data['class_id'] ?? 0);
        $attendanceDate = (string) ($data['attendance_date'] ?? '');

        if ($studentId <= 0 || $attendanceDate === '') {
            throw new InvalidArgumentException('student_id et attendance_date sont requis.');
        }

        /** @var EduStudent|null $student */
        $student = EduStudent::query()
            ->whereKey($studentId)
            ->where('company_id', $companyId)
            ->first();

        if ($student === null) {
            // Élève absent du tenant : jamais de présence cross-tenant (404).
            throw (new ModelNotFoundException)->setModel(EduStudent::class, $studentId);
        }

        $this->assertClassInTenant($classId, $companyId);
        $this->assertStudentInClass($student, $classId);

        // Idempotence : la contrainte UNIQUE(company, class, student, date)
        // garantit un seul enregistrement ; un re-post retourne l'existant
        // (aucun écrasement silencieux — pour changer, correct()).
        return EduAttendanceRecord::query()->firstOrCreate(
            [
                'company_id' => $companyId,
                'class_id' => $classId,
                'student_id' => $student->id,
                'attendance_date' => $attendanceDate,
            ],
            [
                'status' => $status,
                'reason_code' => $reasonCode,
                'note' => isset($data['note']) && $data['note'] !== '' ? (string) $data['note'] : null,
                'recorded_by' => isset($data['recorded_by']) && $data['recorded_by'] !== null
                    ? (int) $data['recorded_by']
                    : null,
            ]
        );
    }

    /**
     * Corrige le statut d'un enregistrement de présence, EN VERSIONNANT.
     *
     * Écrit une ligne edu_attendance_corrections (previous_status →
     * new_status + motif) AVANT de mettre à jour le record, le tout dans une
     * transaction : l'historique des corrections est complet et rejouable.
     *
     * @throws InvalidArgumentException  statut de correction inconnu
     */
    public function correct(EduAttendanceRecord $record, string $newStatus, string $reason, int $actorId): EduAttendanceRecord
    {
        if (! in_array($newStatus, EduAttendanceRecord::STATUSES, true)) {
            throw new InvalidArgumentException(sprintf('Statut de présence invalide : %s.', $newStatus));
        }

        DB::transaction(function () use ($record, $newStatus, $reason, $actorId): void {
            // Versionnage AVANT mutation : la correction documente l'état
            // précédent tel qu'il existe en base au moment de l'écriture.
            EduAttendanceCorrection::query()->create([
                'company_id' => $record->company_id,
                'attendance_record_id' => $record->id,
                'previous_status' => $record->status,
                'new_status' => $newStatus,
                'reason' => $reason !== '' ? $reason : null,
                'corrected_by' => $actorId,
                'corrected_at' => now(),
            ]);

            $record->status = $newStatus;
            $record->save();
        });

        return $record->refresh();
    }

    /**
     * Best-effort : la classe doit exister chez le MÊME tenant. EduClass est
     * livré par EDU-003 (#5819) — tant qu'il n'existe pas, contrôle sauté.
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
            throw (new ModelNotFoundException)->setModel('App\Modules\EduManager\Domain\Models\EduClass', $classId);
        }
    }

    /**
     * Best-effort : l'élève doit être inscrit dans la classe. Le lien
     * d'inscription est livré par EDU-003 (#5819) — tant que la relation
     * `classes()` n'existe pas sur EduStudent, contrôle sauté.
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
     * Id du tenant courant si la surface API l'a lié (TenantMiddleware),
     * chaîne vide sinon (les appels hors surface passent company_id en clair).
     */
    private function currentCompanyId(): string
    {
        if (! app()->bound('current_company')) {
            return '';
        }

        return (string) currentCompany()->id;
    }
}
