<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Application\Actions;

use App\Modules\HealthManager\Domain\Models\HealthAppointment;
use App\Modules\HealthManager\Infrastructure\Services\HealthAppointmentService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;

/**
 * Cas d'usage « modifier un rendez-vous » — HC-004 (#7788, BC-31).
 *
 * Extrait de `HealthAppointmentController::update` (BOS-024b, #8213) :
 * cohérence temporelle re-vérifiée sur l'état FUSIONNÉ (422), contrôle
 * de chevauchement en ignorant le rendez-vous lui-même (409), mise à
 * jour en transaction.
 */
final class UpdateHealthAppointmentAction
{
    public function __construct(
        private readonly HealthAppointmentService $service,
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  Payload validé (UpdateHealthAppointmentRequest).
     */
    public function execute(HealthAppointment $appointment, array $payload): HealthAppointment
    {
        $startsAt = isset($payload['starts_at'])
            ? Carbon::parse((string) $payload['starts_at'])
            : $appointment->starts_at;
        $endsAt = isset($payload['ends_at'])
            ? Carbon::parse((string) $payload['ends_at'])
            : $appointment->ends_at;

        // Cohérence temporelle re-vérifiée sur l'état FUSIONNÉ (un seul des
        // deux champs peut bouger) → 422.
        abort_if($endsAt->lessThanOrEqualTo($startsAt), 422, 'HEALTH_INVALID_TIME_RANGE: ends_at must be after starts_at.');

        $practitionerId = isset($payload['practitioner_id'])
            ? (int) $payload['practitioner_id']
            : $appointment->practitioner_id;

        $this->db->transaction(function () use ($appointment, $payload, $practitionerId, $startsAt, $endsAt): void {
            // 409 si le créneau (éventuellement déplacé) chevauche un autre
            // rendez-vous actif du praticien — le sien est ignoré.
            $this->service->assertNoConflict(
                (string) $appointment->company_id,
                $practitionerId,
                $startsAt,
                $endsAt,
                $appointment->id,
            );

            $appointment->update($payload);
        });

        return $appointment->refresh();
    }
}
