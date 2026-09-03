<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Issue #6574 — les commandes console des modules doivent être enregistrées
 * (Laravel ne découvre que app/Console/Commands ; les commandes modules
 * doivent être déclarées via leur ServiceProvider).
 */
final class AccountingCommandsRegisteredTest extends TestCase
{
    public function test_send_payment_reminders_command_is_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey(
            'accounting:send-payment-reminders',
            $commands,
            'accounting:send-payment-reminders doit être enregistré (issue #6574).',
        );
    }
}
