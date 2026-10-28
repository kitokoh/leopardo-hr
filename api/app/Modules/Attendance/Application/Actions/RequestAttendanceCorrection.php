<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Application\Actions;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceCorrectionRequest;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Attendance\Infrastructure\Services\AttendancePeriodClosureService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Cas d'usage : demander une correction de pointage (#7962).
 *
 * La logique métier — fenêtre temporelle, cohérence entrée/sortie, verrou de
 * période clôturée, stockage du justificatif, journal d'audit — vivait dans le
 * `AttendanceController` (818 lignes). Elle est extraite ici pour que le
 * contrôleur ne fasse plus que du HTTP : valider la forme, appeler l'Action,
 * formater la réponse.
 */
class RequestAttendanceCorrection
{
    public function __construct(
        private readonly AttendancePeriodClosureService $periodClosures,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Charge déjà validée par la Request de l'interface.
     *
     * @throws ValidationException si la correction demandée est incohérente.
     */
    public function execute(Employee $actor, array $data, ?UploadedFile $proof = null): AttendanceCorrectionRequest
    {
        // Issue #5267 : une période clôturée verrouille les corrections.
        $this->periodClosures->assertPeriodOpen((string) $actor->company_id, Carbon::parse($data['date']));

        $timezone = currentCompany()->timezone;
        $requestedCheckIn = Carbon::parse($data['requested_check_in'])->setTimezone($timezone);
        $requestedCheckOut = isset($data['requested_check_out'])
            ? Carbon::parse($data['requested_check_out'])->setTimezone($timezone)
            : null;

        $this->assertCoherent($requestedCheckIn, $requestedCheckOut);

        $log = null;
        if (! empty($data['attendance_log_id'])) {
            $log = AttendanceLog::query()
                ->where('id', $data['attendance_log_id'])
                ->where('employee_id', $actor->id)
                ->firstOrFail();
        }

        // Issue #5267 : stockage du justificatif sous un chemin scopé entreprise
        // (même pattern que les absences, PA2-MOB-006).
        $proofPath = $proof instanceof UploadedFile
            ? $proof->store('attendance-corrections/proofs/'.$actor->company_id, 'local')
            : null;

        $correction = AttendanceCorrectionRequest::query()->create([
            // #7711 : company_id délégué au trait BelongsToCompany (tenant actif).
            'employee_id' => $actor->id,
            'attendance_log_id' => $log?->id,
            'date' => $data['date'],
            'requested_check_in' => $requestedCheckIn,
            'requested_check_out' => $requestedCheckOut,
            'reason' => $data['reason'],
            'proof_path' => $proofPath,
            'status' => 'pending',
        ]);

        AuditLog::create([
            'company_id' => $actor->company_id,
            'user_id' => $actor->id,
            'action' => 'attendance_correction_requested',
            'auditable_type' => $correction->getMorphClass(),
            'auditable_id' => $correction->id,
            'old_values' => [],
            'new_values' => [
                'date' => $correction->date->format('Y-m-d'),
                'requested_check_in' => $requestedCheckIn->toIso8601String(),
                'requested_check_out' => $requestedCheckOut?->toIso8601String(),
                'has_proof' => $proofPath !== null,
            ],
        ]);

        return $correction;
    }

    /**
     * Une correction ne peut pas porter sur le futur, ni se terminer avant de
     * commencer. Les deux règles sont regroupées dans les mêmes clés d'erreur
     * que celles exposées historiquement par l'API.
     */
    private function assertCoherent(Carbon $checkIn, ?Carbon $checkOut): void
    {
        $errors = [];
        if ($checkIn->isFuture()) {
            $errors['requested_check_in'] = [__('attendance.correction_future_check_in')];
        }
        if ($checkOut !== null && $checkOut->isFuture()) {
            $errors['requested_check_out'] = [__('attendance.correction_future_check_out')];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if ($checkOut !== null && $checkOut->lessThanOrEqualTo($checkIn)) {
            throw ValidationException::withMessages([
                'requested_check_out' => [__('errors.ATTENDANCE_CHECK_OUT_AFTER_CHECK_IN')],
            ]);
        }
    }
}
