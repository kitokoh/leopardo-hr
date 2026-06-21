<?php

namespace App\Listeners;

use App\Events\SubscriptionPaid;
use App\Services\CommissionService;
use Illuminate\Support\Facades\Log;

class ProcessCommissionOnPayment
{
    public function __construct(private CommissionService $commissionService)
    {}

    public function handle(SubscriptionPaid $event): void
    {
        try {
            $this->commissionService->recordCommissionForPayment($event->payment);
        } catch (\Throwable $e) {
            Log::error("Failed to record commission for payment {$event->payment->id}: " . $e->getMessage());
        }
    }
}
