<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\FarmOs;

use App\Modules\FarmOs\Domain\Enums\FarmOperationStatus;
use PHPUnit\Framework\TestCase;

/**
 * Farm OS — Machine à états des opérations agricoles.
 *
 * Une opération clôturée est IMMUABLE : c'est la garantie qu'une déclaration de
 * terrain ne peut pas être réécrite silencieusement après coup.
 */
final class FarmOperationStatusTest extends TestCase
{
    public function test_planned_operation_can_start_skip_or_be_cancelled(): void
    {
        $status = FarmOperationStatus::Planned;

        $this->assertTrue($status->canTransitionTo(FarmOperationStatus::InProgress));
        $this->assertTrue($status->canTransitionTo(FarmOperationStatus::Skipped));
        $this->assertTrue($status->canTransitionTo(FarmOperationStatus::Cancelled));
        $this->assertFalse($status->canTransitionTo(FarmOperationStatus::Done));
    }

    public function test_started_operation_can_be_completed_or_cancelled_but_not_silently_reset(): void
    {
        $status = FarmOperationStatus::InProgress;

        $this->assertTrue($status->canTransitionTo(FarmOperationStatus::Done));
        $this->assertTrue($status->canTransitionTo(FarmOperationStatus::Cancelled));
        $this->assertFalse($status->canTransitionTo(FarmOperationStatus::Planned));
        $this->assertFalse($status->canTransitionTo(FarmOperationStatus::Skipped));
    }

    public function test_closed_operations_are_terminal_and_immutable(): void
    {
        foreach ([FarmOperationStatus::Done, FarmOperationStatus::Skipped, FarmOperationStatus::Cancelled] as $status) {
            $this->assertTrue($status->isTerminal(), $status->value.' must be terminal');
            $this->assertSame([], $status->allowedTransitions());
            $this->assertTrue($status->requiresFinishedAt());
        }
    }

    public function test_status_registry_exposes_all_values(): void
    {
        $this->assertSame(
            ['planned', 'in_progress', 'done', 'skipped', 'cancelled'],
            FarmOperationStatus::values()
        );
    }
}
