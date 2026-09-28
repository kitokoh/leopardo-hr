<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Application\Actions;

use App\Modules\HealthManager\Domain\Models\HealthAppointment;
use App\Modules\HealthManager\Infrastructure\Services\HealthAppointmentService;

/**
 * Cas d'usage « transitionner un rendez-vous » (machine à états spec §4)
 * — HC-004 (#7788, BC-31).
 *
 * Extrait de `HealthAppointmentController::transition` (BOS-024b, #8213) :
 * transition hors machine à états → 422 HEALTH_INVALID_TRANSITION.
 */
final class TransitionHealthAppointmentStatusAction
{
    public function __construct(private readonly HealthAppointmentService $service) {}

    public function execute(HealthAppointment $appointment, string $target): HealthAppointment
    {
        // 422 HEALTH_INVALID_TRANSITION hors machine à états.
        $this->service->assertValidTransition($appointment->status, $target);

        $appointment->update(['status' => $target]);

        return $appointment->refresh();
    }
}
