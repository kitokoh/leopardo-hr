<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Infrastructure\Services;

use App\Modules\HealthManager\Domain\Exceptions\HealthAppointmentConflictException;
use App\Modules\HealthManager\Domain\Exceptions\HealthInvalidTransitionException;
use App\Modules\HealthManager\Domain\Models\HealthAppointment;
use Illuminate\Support\Carbon;

/**
 * Invariants métier des rendez-vous — HC-004 (#7788), spec §4.
 *
 * - Chevauchement praticien interdit : deux rendez-vous ACTIFS (statut hors
 *   cancelled/no_show) du même praticien ne se recouvrent jamais
 *   (starts_at < other.ends_at ET ends_at > other.starts_at) →
 *   409 HEALTH_APPOINTMENT_CONFLICT. Les créneaux adjacents
 *   (ends_at == other.starts_at) sont autorisés.
 * - Machine à états : scheduled→confirmed|cancelled ;
 *   confirmed→checked_in|cancelled|no_show ; checked_in→completed ;
 *   completed/cancelled/no_show terminaux → 422 HEALTH_INVALID_TRANSITION.
 */
class HealthAppointmentService
{
    /**
     * Statuts qui ne bloquent PAS le créneau du praticien.
     *
     * @var list<string>
     */
    private const NON_BLOCKING_STATUSES = [
        HealthAppointment::STATUS_CANCELLED,
        HealthAppointment::STATUS_NO_SHOW,
    ];

    /**
     * Transitions autorisées (spec §4).
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        HealthAppointment::STATUS_SCHEDULED => [
            HealthAppointment::STATUS_CONFIRMED,
            HealthAppointment::STATUS_CANCELLED,
        ],
        HealthAppointment::STATUS_CONFIRMED => [
            HealthAppointment::STATUS_CHECKED_IN,
            HealthAppointment::STATUS_CANCELLED,
            HealthAppointment::STATUS_NO_SHOW,
        ],
        HealthAppointment::STATUS_CHECKED_IN => [
            HealthAppointment::STATUS_COMPLETED,
        ],
        HealthAppointment::STATUS_COMPLETED => [],
        HealthAppointment::STATUS_CANCELLED => [],
        HealthAppointment::STATUS_NO_SHOW => [],
    ];

    /**
     * Refuse tout chevauchement d'agenda du praticien (409).
     */
    public function assertNoConflict(
        string $companyId,
        int $practitionerId,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $ignoreAppointmentId = null,
    ): void {
        $conflict = HealthAppointment::query()
            ->where('company_id', $companyId)
            ->where('practitioner_id', $practitionerId)
            ->whereNotIn('status', self::NON_BLOCKING_STATUSES)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when(
                $ignoreAppointmentId !== null,
                fn ($query) => $query->where('id', '!=', $ignoreAppointmentId)
            )
            ->exists();

        if ($conflict) {
            throw new HealthAppointmentConflictException;
        }
    }

    /**
     * Refuse toute transition hors machine à états (422).
     */
    public function assertValidTransition(string $from, string $to): void
    {
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new HealthInvalidTransitionException($from, $to);
        }
    }
}
