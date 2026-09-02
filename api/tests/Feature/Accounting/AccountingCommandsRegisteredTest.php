<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Issue #6574 — SendPaymentRemindersCommand était silencieusement inactive :
 * Laravel 12 ne découvre que app/Console/Commands ; une commande de module
 * doit être enregistrée via le ServiceProvider du module (withCommands /
 * $this->commands()). Le critère d'acceptation de l'issue :
 * `php artisan list | grep send-payment` → présente.
 */
class AccountingCommandsRegisteredTest extends TestCase
{
    public function test_send_payment_reminders_command_is_registered(): void
    {
        $this->assertArrayHasKey(
            'accounting:send-payment-reminders',
            Artisan::all(),
            'La commande accounting:send-payment-reminders doit être enregistrée par AccountingServiceProvider (issue #6574).'
        );
    }
}
