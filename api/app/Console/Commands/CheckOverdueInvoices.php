<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Billing\Domain\Enums\InvoiceStatus;
use App\Modules\Billing\Domain\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckOverdueInvoices extends Command
{
    protected $signature = 'billing:check-overdue';

    protected $description = 'Mark and notify overdue invoices';

    public function handle(): int
    {
        // DEP-BC21 #6248 : `pending` (hérité de la génération mensuelle) et
        // `sent` passent par la machine à états → `overdue` dès due_date passée.
        $overdueInvoices = Invoice::whereIn('status', [
            InvoiceStatus::Pending->value,
            InvoiceStatus::Sent->value,
        ])
            ->where('due_date', '<', now())
            ->get();

        foreach ($overdueInvoices as $invoice) {
            $invoice->transitionTo(InvoiceStatus::Overdue);
            Log::warning("Invoice overdue: id={$invoice->id} company={$invoice->company_id} amount={$invoice->amount}");
        }

        $this->info("Marked {$overdueInvoices->count()} invoice(s) as overdue.");

        return self::SUCCESS;
    }
}
