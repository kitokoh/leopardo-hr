<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Application\Actions;

use App\Modules\HealthManager\Domain\Models\HealthAppointment;
use App\Modules\HealthManager\Infrastructure\Services\HealthAppointmentService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;

/**
 * Cas d'usage « planifier un rendez-vous » — HC-004 (#7788, BC-31).
 *
 * Extrait de `HealthAppointmentController::store` (BOS-024b, #8213) :
 * chevauchement praticien → 409 HEALTH_APPOINTMENT_CONFLICT (invariant
 * porté par le service), création en transaction au statut `scheduled`.
 */
final class ScheduleHealthAppointmentAction
{
    public function __construct(
        private readonly HealthAppointmentService $service,
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  Payload validé (StoreHealthAppointmentRequest).
     */
    public function execute(string $companyId, array $payload): HealthAppointment
    {
        $startsAt = Carbon::parse((string) $payload['starts_at']);
        $endsAt = Carbon::parse((string) $payload['ends_at']);

        return $this->db->transaction(function () use ($payload, $companyId, $startsAt, $endsAt): HealthAppointment {
            // 409 HEALTH_APPOINTMENT_CONFLICT si chevauchement praticien.
            $this->service->assertNoConflict($companyId, (int) $payload['practitioner_id'], $startsAt, $endsAt);

            /** @var HealthAppointment $appointment */
            $appointment = HealthAppointment::query()->create(array_merge($payload, [
                'company_id' => $companyId,
                'status' => HealthAppointment::STATUS_SCHEDULED,
            ]));

            return $appointment;
        });
    }
}
