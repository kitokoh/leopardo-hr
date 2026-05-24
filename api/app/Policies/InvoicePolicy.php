<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Employee;
use App\Models\Invoice;

class InvoicePolicy
{
    public function viewAny(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'comptable');
    }

    public function view(Employee $actor, Invoice $invoice): bool
    {
        return $invoice->company_id === $actor->company_id
            && $actor->hasManagerRole('principal', 'comptable');
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal');
    }

    public function pay(Employee $actor, Invoice $invoice): bool
    {
        return $invoice->company_id === $actor->company_id
            && $actor->hasManagerRole('principal');
    }
}
