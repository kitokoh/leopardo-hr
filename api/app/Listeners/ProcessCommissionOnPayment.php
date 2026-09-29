<?php

namespace App\Listeners;

use App\Events\SubscriptionPaid;
use App\Modules\Payroll\Infrastructure\Services\CommissionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * #8206 (BOS-017) : listener mis en file (`ShouldQueue`, queue `default`) —
 * l'enregistrement de la commission (écritures Payroll) sort du cycle du
 * webhook de paiement. Le handler reste best-effort (catch Throwable).
 */
class ProcessCommissionOnPayment implements ShouldQueue
{
    public function __construct(private CommissionService $commissionService) {}

    public function handle(SubscriptionPaid $event): void
    {
        try {
            $this->commissionService->recordCommissionForPayment($event->payment);
        } catch (\Throwable $e) {
            Log::error("Failed to record commission for payment {$event->payment->id}: ".$e->getMessage());
        }
    }
}
