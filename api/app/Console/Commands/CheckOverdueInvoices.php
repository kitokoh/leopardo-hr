<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckOverdueInvoices extends Command
{
    protected $signature = 'billing:check-overdue';

    protected $description = 'Mark and notify overdue invoices';

    public function handle(): int
    {
        $overdueInvoices = Invoice::where('status', 'pending')
            ->where('due_date', '<', now())
            ->get();

        foreach ($overdueInvoices as $invoice) {
            $invoice->update(['status' => 'overdue']);
            Log::warning("Invoice overdue: id={$invoice->id} company={$invoice->company_id} amount={$invoice->amount}");
        }

        $this->info("Marked {$overdueInvoices->count()} invoice(s) as overdue.");

        return self::SUCCESS;
    }
}
