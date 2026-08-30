<?php

declare(strict_types=1);

namespace Tests\Unit\Delivery;

use App\Modules\Delivery\Domain\Enums\DeliveryStatus;
use App\Modules\Delivery\Domain\Exceptions\InvalidDeliveryTransitionException;
use App\Modules\Delivery\Domain\Support\DeliveryStateMachine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * DELIVERY-103 (#6284) — invariants du cycle de vie d'une livraison.
 *
 * Verrouille la machine à états : transitions autorisées, états terminaux
 * non réouvrables, POD obligatoire pour `delivered`.
 */
final class DeliveryStateMachineTest extends TestCase
{
    private DeliveryStateMachine $machine;

    protected function setUp(): void
    {
        $this->machine = new DeliveryStateMachine();
    }

    /**
     * @return iterable<string, array{DeliveryStatus, DeliveryStatus}>
     */
    public static function legalTransitions(): iterable
    {
        yield 'created → assigned' => [DeliveryStatus::Created, DeliveryStatus::Assigned];
        yield 'created → cancelled' => [DeliveryStatus::Created, DeliveryStatus::Cancelled];
        yield 'assigned → picked_up' => [DeliveryStatus::Assigned, DeliveryStatus::PickedUp];
        yield 'picked_up → out_for_delivery' => [DeliveryStatus::PickedUp, DeliveryStatus::OutForDelivery];
        yield 'out_for_delivery → arrived' => [DeliveryStatus::OutForDelivery, DeliveryStatus::Arrived];
        yield 'arrived → delivered (avec POD)' => [DeliveryStatus::Arrived, DeliveryStatus::Delivered];
        yield 'out_for_delivery → failed' => [DeliveryStatus::OutForDelivery, DeliveryStatus::Failed];
        yield 'arrived → failed' => [DeliveryStatus::Arrived, DeliveryStatus::Failed];
        yield 'failed → returned' => [DeliveryStatus::Failed, DeliveryStatus::Returned];
    }

    #[DataProvider('legalTransitions')]
    public function test_legal_transitions_are_accepted(DeliveryStatus $from, DeliveryStatus $to): void
    {
        self::assertTrue($this->machine->canTransitionTo($from, $to, hasProof: $to === DeliveryStatus::Delivered));
        $this->machine->assertCanTransitionTo($from, $to, hasProof: $to === DeliveryStatus::Delivered);
        self::assertSame($from, $from);
    }

    /**
     * @return iterable<string, array{DeliveryStatus, DeliveryStatus}>
     */
    public static function illegalTransitions(): iterable
    {
        yield "created → delivered (saut d'étapes)" => [DeliveryStatus::Created, DeliveryStatus::Delivered];
        yield 'assigned → arrived' => [DeliveryStatus::Assigned, DeliveryStatus::Arrived];
        yield 'delivered → assigned (état terminal réouvert)' => [DeliveryStatus::Delivered, DeliveryStatus::Assigned];
        yield 'returned → delivered' => [DeliveryStatus::Returned, DeliveryStatus::Delivered];
        yield 'cancelled → picked_up' => [DeliveryStatus::Cancelled, DeliveryStatus::PickedUp];
        yield 'delivered → failed' => [DeliveryStatus::Delivered, DeliveryStatus::Failed];
    }

    #[DataProvider('illegalTransitions')]
    public function test_illegal_transitions_are_rejected(DeliveryStatus $from, DeliveryStatus $to): void
    {
        self::assertFalse($this->machine->canTransitionTo($from, $to));
        self::expectException(InvalidDeliveryTransitionException::class);
        $this->machine->assertCanTransitionTo($from, $to);
    }

    public function test_delivered_requires_proof_of_delivery(): void
    {
        self::assertFalse($this->machine->canTransitionTo(DeliveryStatus::Arrived, DeliveryStatus::Delivered));

        try {
            $this->machine->assertCanTransitionTo(DeliveryStatus::Arrived, DeliveryStatus::Delivered);
            self::fail('Expected InvalidDeliveryTransitionException (POD required).');
        } catch (InvalidDeliveryTransitionException $e) {
            self::assertStringContainsString('POD', $e->getMessage());
        }

        self::assertTrue($this->machine->canTransitionTo(DeliveryStatus::Arrived, DeliveryStatus::Delivered, hasProof: true));
    }

    public function test_terminal_statuses_cannot_be_reopened(): void
    {
        foreach ([DeliveryStatus::Delivered, DeliveryStatus::Returned, DeliveryStatus::Cancelled] as $terminal) {
            self::assertTrue($this->machine->isTerminal($terminal));
            foreach (DeliveryStatus::cases() as $candidate) {
                if ($candidate === $terminal) {
                    continue;
                }
                self::assertFalse(
                    $this->machine->canTransitionTo($terminal, $candidate),
                    sprintf('Terminal status "%s" must not transition to "%s".', $terminal->value, $candidate->value),
                );
            }
        }
    }
}
