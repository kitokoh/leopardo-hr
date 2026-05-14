<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class AccrueLeaveBalancesTest extends TestCase
{
    public function test_command_skips_when_not_first_of_month(): void
    {
        $this->travelTo(now()->day(15));

        $this->artisan('leave:accrue')
            ->expectsOutput('Skipped — accrual runs on 1st of month. Use --force to override.')
            ->assertSuccessful();
    }

    public function test_command_runs_with_force(): void
    {
        $this->travelTo(now()->day(15));

        $this->artisan('leave:accrue', ['--force' => true])
            ->assertSuccessful();
    }
}
