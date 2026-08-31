<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Billing\Domain\Enums\PlanCode;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'billing:generate-invoices';

    protected $description = 'Generate monthly invoices for active subscriptions';

    private const PLAN_PRICES = [
        'free' => ['amount' => 0.00, 'currency' => 'EUR'],
        'pilot' => ['amount' => 29.00, 'currency' => 'EUR'],
        'operations' => ['amount' => 99.00, 'currency' => 'EUR'],
        'enterprise' => ['amount' => 299.00, 'currency' => 'EUR'],
    ];

    public function handle(): int
    {
        // #6549 : jamais deux exécutions simultanées de la génération de
        // factures (double trigger schedule/manuel). Disponible sur la base
        // Command de Laravel 12 (trait Isolatable) — garde défensive.
        if (method_exists($this, 'withoutOverlapping')) {
            $this->withoutOverlapping();
        }

        $period = now()->format('Y-m');

        $activeSubscriptions = Subscription::where('status', 'active')
            ->where('current_period_end', '<=', now())
            ->get();

        $generated = 0;

        foreach ($activeSubscriptions as $subscription) {
            try {
                // Garde douce : une facture existe déjà pour cette période →
                // on ne facture pas deux fois (l'index unique
                // (company_id, subscription_id, period) reste la garde dure :
                // si deux runs passent cette garde simultanément, le 23505 est
                // attrapé et journalisé ci-dessous, sans doublon en base).
                $alreadyBilled = Invoice::query()
                    ->where('company_id', $subscription->company_id)
                    ->where('subscription_id', $subscription->id)
                    ->where('period', $period)
                    ->exists();

                if ($alreadyBilled) {
                    Log::info("Invoice already billed for subscription {$subscription->id} period {$period} — skipped.");

                    continue;
                }

                DB::transaction(function () use ($subscription, $period, &$generated): void {
                    // #6549 : verrou pessimiste sur l'abonnement → la
                    // numérotation est calculée sous verrou (plus de collision
                    // de numéros entre deux runs concurrents : le second attend
                    // la fin du premier avant de compter les factures).
                    /** @var Subscription $locked */
                    $locked = Subscription::query()
                        ->lockForUpdate()
                        ->findOrFail($subscription->id);

                    $plan = PlanCode::normalize((string) $locked->plan)->value;
                    $pricing = self::PLAN_PRICES[$plan];
                    $year = now()->format('Y');
                    $seq = Invoice::where('company_id', $locked->company_id)->count() + 1;

                    // Audit #1702 : la colonne réelle est `number` (le champ
                    // `invoice_number` n'est pas fillable — le numéro était
                    // silencieusement perdu) ; amount_ht/tax_rate/plan_name/
                    // period ne sont pas des colonnes du modèle.
                    $invoice = Invoice::create([
                        'company_id' => $locked->company_id,
                        'subscription_id' => $locked->id,
                        'period' => $period,
                        'number' => sprintf('LEO-%s-%04d', $year, $seq),
                        'amount' => $pricing['amount'],
                        'tax_amount' => 0,
                        'total' => $pricing['amount'],
                        'currency' => $pricing['currency'],
                        'status' => 'pending',
                        'due_date' => now()->addDays(30),
                    ]);

                    $locked->update([
                        'current_period_start' => now(),
                        'current_period_end' => now()->addMonth(),
                    ]);

                    $generated++;
                    Log::info("Invoice generated: {$invoice->number} for company={$locked->company_id}");
                });
            } catch (\Throwable $e) {
                // #6549 : deux runs ayant passé la garde douce simultanément →
                // le 23505 de l'index unique (company_id, subscription_id,
                // period) est un skip attendu, pas une erreur.
                if ($e instanceof \Illuminate\Database\QueryException
                    && str_contains($e->getMessage(), 'invoices_company_subscription_period_unique')) {
                    Log::warning("Concurrent invoice generation skipped for subscription {$subscription->id} period {$period} (unique constraint).");

                    continue;
                }

                Log::error("Failed to generate invoice for subscription {$subscription->id}: {$e->getMessage()}");
            }
        }

        $this->info("Generated {$generated} invoice(s).");

        return self::SUCCESS;
    }
}
