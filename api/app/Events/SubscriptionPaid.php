<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionPaid
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Payment $payment)
    {}
}
