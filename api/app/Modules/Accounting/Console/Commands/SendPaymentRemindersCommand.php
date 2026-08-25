<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Console\Commands;

use App\Modules\Accounting\Infrastructure\Services\PaymentReminderService;
use Illuminate\Console\Command;

/**
 * Relances de paiement automatiques (issue #5229) — stages J+7/J+15/J+30
 * paramétrables par entreprise. Idempotent : une relance par (document, stage),
 * jamais de doublon même en double exécution.
 */
class SendPaymentRemindersCommand extends Command
{
    protected $signature = 'accounting:send-payment-reminders';

    protected $description = 'Envoie les relances de paiement dues (J+7/J+15/J+30 paramétrables)';

    public function handle(PaymentReminderService $reminders): int
    {
        $sent = $reminders->run();

        $this->info("Relances envoyées : {$sent}");

        return self::SUCCESS;
    }
}
