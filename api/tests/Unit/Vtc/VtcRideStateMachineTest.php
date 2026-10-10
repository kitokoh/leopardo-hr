<?php

declare(strict_types=1);

namespace Tests\Unit\Vtc;

use App\Modules\Vtc\Domain\Enums\VtcRideStatus;
use App\Modules\Vtc\Domain\Exceptions\InvalidRideTransitionException;
use App\Modules\Vtc\Domain\Support\VtcRideStateMachine;
use PHPUnit\Framework\TestCase;

/**
 * VTC-03 (#8359, BC-34 VTC) — machine à états du cycle de course : golden
 * path complet, toutes les transitions invalides rejetées, états terminaux
 * définitifs (spec §5.2).
 */
class VtcRideStateMachineTest extends TestCase
{
    public function test_golden_path_is_allowed(): void
    {
        $machine = new VtcRideStateMachine;

        $path = [
            VtcRideStatus::Requested,
            VtcRideStatus::Dispatching,
            VtcRideStatus::Accepted,
            VtcRideStatus::Arrived,
            VtcRideStatus::InProgress,
            VtcRideStatus::Completed,
        ];

        for ($i = 0; $i < count($path) - 1; $i++) {
            self::assertTrue(
                $machine->canTransitionTo($path[$i], $path[$i + 1]),
                sprintf('Transition %s → %s refusée.', $path[$i]->value, $path[$i + 1]->value)
            );
        }
    }

    public function test_dispatch_may_expire_before_acceptance(): void
    {
        $machine = new VtcRideStateMachine;

        self::assertTrue($machine->canTransitionTo(VtcRideStatus::Requested, VtcRideStatus::Expired));
        self::assertTrue($machine->canTransitionTo(VtcRideStatus::Dispatching, VtcRideStatus::Expired));
    }

    public function test_cancellation_window(): void
    {
        $machine = new VtcRideStateMachine;

        // Annulable depuis les états avant démarrage effectif…
        foreach ([VtcRideStatus::Requested, VtcRideStatus::Dispatching, VtcRideStatus::Accepted, VtcRideStatus::Arrived] as $status) {
            self::assertTrue($machine->canTransitionTo($status, VtcRideStatus::Cancelled), $status->value);
        }

        // …mais plus une fois la course démarrée.
        self::assertFalse($machine->canTransitionTo(VtcRideStatus::InProgress, VtcRideStatus::Cancelled));
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $machine = new VtcRideStateMachine;

        $invalid = [
            // Sauts d'étape.
            [VtcRideStatus::Requested, VtcRideStatus::Accepted],
            [VtcRideStatus::Requested, VtcRideStatus::InProgress],
            [VtcRideStatus::Dispatching, VtcRideStatus::InProgress],
            [VtcRideStatus::Accepted, VtcRideStatus::Completed],
            // Retours arrière.
            [VtcRideStatus::Dispatching, VtcRideStatus::Requested],
            [VtcRideStatus::InProgress, VtcRideStatus::Arrived],
            // Réouverture d'états terminaux.
            [VtcRideStatus::Completed, VtcRideStatus::Requested],
            [VtcRideStatus::Expired, VtcRideStatus::Dispatching],
            [VtcRideStatus::Cancelled, VtcRideStatus::Requested],
        ];

        foreach ($invalid as [$from, $to]) {
            self::assertFalse(
                $machine->canTransitionTo($from, $to),
                sprintf('Transition %s → %s devrait être invalide.', $from->value, $to->value)
            );
        }

        $this->expectException(InvalidRideTransitionException::class);
        $machine->assertCanTransitionTo(VtcRideStatus::Requested, VtcRideStatus::Completed);
    }

    public function test_terminal_statuses(): void
    {
        $machine = new VtcRideStateMachine;

        foreach ([VtcRideStatus::Completed, VtcRideStatus::Expired, VtcRideStatus::Cancelled] as $status) {
            self::assertTrue($machine->isTerminal($status), $status->value);
        }

        foreach ([VtcRideStatus::Requested, VtcRideStatus::Dispatching, VtcRideStatus::Accepted, VtcRideStatus::Arrived, VtcRideStatus::InProgress] as $status) {
            self::assertFalse($machine->isTerminal($status), $status->value);
        }
    }
}
