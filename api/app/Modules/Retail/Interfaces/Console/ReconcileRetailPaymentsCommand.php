<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Console;

use App\Modules\Retail\Application\Services\RetailPaymentService;
use Illuminate\Console\Command;

/**
 * `retail:payments:reconcile` (BC-17 RETAIL, #7812 D) — filet de securite
 * quand un webhook s'est perdu : re-verifie aupres du provider les intents
 * de paiement `pending|processing` plus vieux que X minutes (defaut :
 * config `retail.payments.reconcile_after_minutes`) et applique les MEMES
 * transitions que le webhook (voie unique RetailPaymentService). A
 * planifier (scheduler/cron) toutes les 15-30 minutes.
 */
class ReconcileRetailPaymentsCommand extends Command
{
    protected $signature = 'retail:payments:reconcile {--minutes= : Age minimal (minutes) des intents a re-verifier}';

    protected $description = 'Re-verifie aupres du provider les intents de paiement marketplace en attente et applique les transitions (BC-17/#7812)';

    public function handle(RetailPaymentService $payments): int
    {
        $option = $this->option('minutes');
        $minutes = is_numeric($option)
            ? (int) $option
            : (int) config('retail.payments.reconcile_after_minutes', 30);

        $summary = $payments->reconcilePendingIntents($minutes);

        $this->info(sprintf(
            'Reconciliation done: %d intent(s) checked, %d updated (older than %d min).',
            $summary['checked'],
            $summary['updated'],
            $minutes,
        ));

        return self::SUCCESS;
    }
}
