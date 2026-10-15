<?php

declare(strict_types=1);

namespace Tests\Unit\Trade;

use App\Modules\Trade\Domain\Enums\TradeOrderStatus;
use App\Modules\Trade\Domain\Enums\TradeQuoteStatus;
use App\Modules\Trade\Domain\Enums\TradeRfqStatus;
use App\Modules\Trade\Domain\Enums\TradeShipmentStatus;
use App\Modules\Trade\Domain\Exceptions\InvalidTradeStateTransitionException;
use App\Modules\Trade\Domain\Support\TradeOrderStateMachine;
use App\Modules\Trade\Domain\Support\TradeQuoteStateMachine;
use App\Modules\Trade\Domain\Support\TradeRfqStateMachine;
use App\Modules\Trade\Domain\Support\TradeShipmentStateMachine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Machines a etats de la verticale Trade (BC-36, TRADE-003/004).
 *
 * Ces classes sont PURES (aucune dependance DB) : le test s'execute donc en
 * environnement sans base, ce qui est le seul type de test verifiable quand
 * Composer/PostgreSQL ne sont pas disponibles.
 */
final class TradeStateMachinesTest extends TestCase
{
    public function test_rfq_accepts_the_nominal_lifecycle(): void
    {
        $machine = new TradeRfqStateMachine;

        $machine->assertCanTransitionTo(TradeRfqStatus::Draft, TradeRfqStatus::Open);
        $machine->assertCanTransitionTo(TradeRfqStatus::Open, TradeRfqStatus::Awarded);

        $this->assertTrue(true, 'Le cycle nominal draft -> open -> awarded doit passer.');
    }

    public function test_rfq_refuses_to_leave_a_terminal_state(): void
    {
        $machine = new TradeRfqStateMachine;

        $this->assertFalse($machine->canTransitionTo(TradeRfqStatus::Awarded, TradeRfqStatus::Open));
        $this->assertFalse($machine->canTransitionTo(TradeRfqStatus::Cancelled, TradeRfqStatus::Draft));

        $this->expectException(InvalidTradeStateTransitionException::class);
        $machine->assertCanTransitionTo(TradeRfqStatus::Awarded, TradeRfqStatus::Open);
    }

    /**
     * Une RFQ en brouillon ne s'attribue pas : il faut d'abord la publier.
     * C'est ce qui garantit qu'une attribution implique une diffusion reelle.
     */
    public function test_rfq_cannot_be_awarded_straight_from_draft(): void
    {
        $machine = new TradeRfqStateMachine;

        $this->assertFalse($machine->canTransitionTo(TradeRfqStatus::Draft, TradeRfqStatus::Awarded));
    }

    public function test_quote_lifecycle_allows_negotiation_then_acceptance(): void
    {
        $machine = new TradeQuoteStateMachine;

        $machine->assertCanTransitionTo(TradeQuoteStatus::Draft, TradeQuoteStatus::Submitted);
        $machine->assertCanTransitionTo(TradeQuoteStatus::Submitted, TradeQuoteStatus::Negotiating);
        $machine->assertCanTransitionTo(TradeQuoteStatus::Negotiating, TradeQuoteStatus::UnderReview);
        $machine->assertCanTransitionTo(TradeQuoteStatus::UnderReview, TradeQuoteStatus::Accepted);

        $this->assertTrue(true, 'La negociation doit pouvoir revenir en revue avant acceptation.');
    }

    public function test_accepted_quote_is_terminal(): void
    {
        $machine = new TradeQuoteStateMachine;

        $this->assertFalse($machine->canTransitionTo(TradeQuoteStatus::Accepted, TradeQuoteStatus::Negotiating));
        $this->assertTrue(TradeQuoteStatus::Accepted->allowsOrderCreation());
        $this->assertFalse(TradeQuoteStatus::Draft->allowsOrderCreation());
    }

    public function test_a_draft_quote_cannot_expire(): void
    {
        $machine = new TradeQuoteStateMachine;

        $this->assertFalse($machine->canTransitionTo(TradeQuoteStatus::Draft, TradeQuoteStatus::Expired));
        $this->assertTrue($machine->canTransitionTo(TradeQuoteStatus::Submitted, TradeQuoteStatus::Expired));
    }

    public function test_order_follows_the_logistics_lifecycle(): void
    {
        $machine = new TradeOrderStateMachine;

        $machine->assertCanTransitionTo(TradeOrderStatus::Draft, TradeOrderStatus::Confirmed);
        $machine->assertCanTransitionTo(TradeOrderStatus::Confirmed, TradeOrderStatus::Preparing);
        $machine->assertCanTransitionTo(TradeOrderStatus::Preparing, TradeOrderStatus::Ready);
        $machine->assertCanTransitionTo(TradeOrderStatus::Ready, TradeOrderStatus::Shipped);
        $machine->assertCanTransitionTo(TradeOrderStatus::Shipped, TradeOrderStatus::Delivered);
        $machine->assertCanTransitionTo(TradeOrderStatus::Delivered, TradeOrderStatus::Closed);

        $this->assertTrue(true, 'Le cycle commande complet doit etre accepte.');
    }

    /**
     * REGLE METIER CENTRALE : une commande expediee ne s'annule plus. Un
     * `cancelled` apres depart masquerait une expedition reelle.
     */
    public function test_a_shipped_order_cannot_be_cancelled(): void
    {
        $machine = new TradeOrderStateMachine;

        $this->assertFalse($machine->canTransitionTo(TradeOrderStatus::Shipped, TradeOrderStatus::Cancelled));
        $this->assertFalse($machine->canTransitionTo(TradeOrderStatus::Delivered, TradeOrderStatus::Cancelled));
        $this->assertTrue(TradeOrderStatus::Shipped->isCancellable() === false);
        $this->assertTrue(TradeOrderStatus::Ready->isCancellable());
    }

    public function test_shipment_reaches_customs_then_delivery(): void
    {
        $machine = new TradeShipmentStateMachine;

        $machine->assertCanTransitionTo(TradeShipmentStatus::Pending, TradeShipmentStatus::Booked);
        $machine->assertCanTransitionTo(TradeShipmentStatus::Booked, TradeShipmentStatus::InTransit);
        $machine->assertCanTransitionTo(TradeShipmentStatus::InTransit, TradeShipmentStatus::Arrived);
        $machine->assertCanTransitionTo(TradeShipmentStatus::Arrived, TradeShipmentStatus::InCustoms);
        $machine->assertCanTransitionTo(TradeShipmentStatus::InCustoms, TradeShipmentStatus::Delivered);

        $this->assertTrue(TradeShipmentStatus::Delivered->isTerminal());
    }

    public function test_shipment_exception_is_reachable_from_any_live_state(): void
    {
        $machine = new TradeShipmentStateMachine;

        foreach ([TradeShipmentStatus::Pending, TradeShipmentStatus::Booked, TradeShipmentStatus::InTransit] as $from) {
            $this->assertTrue(
                $machine->canTransitionTo($from, TradeShipmentStatus::Exception),
                sprintf('Un incident doit etre declarable depuis %s.', $from->value),
            );
        }
    }

    /**
     * Chaque etat declare doit figurer dans la table de transitions : une enum
     * ajoutee sans regle rendrait la machine silencieusement permissive ou
     * provoquerait une erreur d'index — les deux sont des bugs.
     *
     * @dataProvider stateMachinesProvider
     */
    public function test_every_state_has_an_entry_in_the_transition_table(string $machineClass, array $states): void
    {
        $machine = new $machineClass;

        foreach ($states as $state) {
            $this->assertIsArray(
                $machine->allowedFrom($state),
                sprintf('%s doit declarer ses transitions sortantes.', $state->value),
            );
        }
    }

    /**
     * @return array<string, array{0: class-string, 1: array<int, object>}>
     */
    public static function stateMachinesProvider(): array
    {
        return [
            'rfq' => [TradeRfqStateMachine::class, TradeRfqStatus::cases()],
            'quote' => [TradeQuoteStateMachine::class, TradeQuoteStatus::cases()],
            'order' => [TradeOrderStateMachine::class, TradeOrderStatus::cases()],
            'shipment' => [TradeShipmentStateMachine::class, TradeShipmentStatus::cases()],
        ];
    }
}
