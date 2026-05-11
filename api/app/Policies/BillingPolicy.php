<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;

class BillingPolicy
{
    public function viewSubscription(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function manageSubscription(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal');
    }

    public function viewInvoices(Employee $actor): bool
    {
        return $actor->isManager();
    }

    public function downloadInvoice(Employee $actor): bool
    {
        return $actor->isManager();
    }
}
