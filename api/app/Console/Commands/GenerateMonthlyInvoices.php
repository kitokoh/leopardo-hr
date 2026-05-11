<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'billing:generate-invoices';

    protected $description = 'Generate monthly invoices for active subscriptions';

    private const PLAN_PRICES = [
        'starter' => ['amount' => 29.00, 'currency' => 'EUR'],
        'business' => ['amount' => 99.00, 'currency' => 'EUR'],
        'enterprise' => ['amount' => 299.00, 'currency' => 'EUR'],
    ];

    public function handle(): int
    {
        $activeSubscriptions = Subscription::where('status', 'active')
            ->where('current_period_end', '<=', now())
            ->get();

        $generated = 0;

        foreach ($activeSubscriptions as $subscription) {
            try {
                DB::transaction(function () use ($subscription, &$generated): void {
                    $pricing = self::PLAN_PRICES[$subscription->plan] ?? self::PLAN_PRICES['starter'];
                    $year = now()->format('Y');
                    $seq = Invoice::where('company_id', $subscription->company_id)->count() + 1;

                    $invoice = Invoice::create([
                        'company_id' => $subscription->company_id,
                        'subscription_id' => $subscription->id,
                        'invoice_number' => sprintf('LEO-%s-%04d', $year, $seq),
                        'amount' => $pricing['amount'],
                        'amount_ht' => $pricing['amount'],
                        'tax_rate' => 0,
                        'tax_amount' => 0,
                        'total' => $pricing['amount'],
                        'currency' => $pricing['currency'],
                        'status' => 'pending',
                        'plan_name' => ucfirst($subscription->plan),
                        'period' => now()->format('F Y'),
                        'due_date' => now()->addDays(30),
                    ]);

                    $subscription->update([
                        'current_period_start' => now(),
                        'current_period_end' => now()->addMonth(),
                    ]);

                    $generated++;
                    Log::info("Invoice generated: {$invoice->invoice_number} for company={$subscription->company_id}");
                });
            } catch (\Throwable $e) {
                Log::error("Failed to generate invoice for subscription {$subscription->id}: {$e->getMessage()}");
            }
        }

        $this->info("Generated {$generated} invoice(s).");

        return self::SUCCESS;
    }
}
