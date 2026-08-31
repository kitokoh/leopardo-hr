<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Billing\Domain\Enums\PlanCode;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyInvoices extends Command implements Isolatable
{
    protected $signature = 'billing:generate-invoices';

    /** Issue #6549 : deuxième exécution simultanée → sortie immédiate sans effet. */
    protected $isolatedExitCode = self::SUCCESS;

    protected $description = 'Generate monthly invoices for active subscriptions';

    private const PLAN_PRICES = [
        'free' => ['amount' => 0.00, 'currency' => 'EUR'],
        'pilot' => ['amount' => 29.00, 'currency' => 'EUR'],
        'operations' => ['amount' => 99.00, 'currency' => 'EUR'],
        'enterprise' => ['amount' => 299.00, 'currency' => 'EUR'],
    ];

    public function handle(): int
    {
        // #6549 (audit) : jamais deux exécutions en parallèle — le contrat
        // Isolatable ajoute l'option --isolated et verrouille via le mutex
        // (une 2e exécution sort immédiatement, exit code 0).
        $activeSubscriptions = Subscription::where('status', 'active')
            ->where('current_period_end', '<=', now())
            ->get();

        $generated = 0;

        foreach ($activeSubscriptions as $subscription) {
            try {
                $outcome = DB::transaction(function () use ($subscription): string {
                    // Verrou pessimiste : les runs concurrents se sérialisent
                    // par subscription (l'anti-doublon lit le solde réel).
                    $locked = Subscription::query()->lockForUpdate()->find($subscription->id);

                    if ($locked === null || $locked->status !== 'active') {
                        return 'skipped';
                    }

                    $period = now()->format('Y-m');

                    // #6549 : anti-doublon — une facture existe déjà pour ce
                    // subscription/période → skip. L'index unique
                    // (company_id, subscription_id, period) est le verrou final
                    // en cas de course (catch 23505 ci-dessous).
                    $alreadyGenerated = Invoice::query()
                        ->where('company_id', $locked->company_id)
                        ->where('subscription_id', $locked->id)
                        ->where('period', $period)
                        ->exists();

                    if ($alreadyGenerated) {
                        Log::info('Invoice already generated for period, skipping', [
                            'subscription_id' => $locked->id,
                            'company_id' => $locked->company_id,
                            'period' => $period,
                        ]);

                        return 'skipped';
                    }

                    $plan = PlanCode::normalize((string) $locked->plan)->value;
                    // normalize() ne retourne que free/pilot/operations/enterprise.
                    $pricing = self::PLAN_PRICES[$plan];
                    $year = (int) now()->format('Y');
                    // #6549 : numérotation atomique GLOBALE par année (upsert
                    // ON CONFLICT) — la colonne invoices.number est UNIQUE
                    // globale, un compteur par entreprise créerait des
                    // collisions inter-entreprises. Plus jamais de count()+1
                    // concurrent-unsafe.
                    $seq = $this->nextSequence($year);

                    $invoice = Invoice::create([
                        'company_id' => $locked->company_id,
                        'subscription_id' => $locked->id,
                        'period' => $period,
                        'number' => sprintf('LEO-%d-%04d', $year, $seq),
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

                    Log::info("Invoice generated: {$invoice->number} for company={$locked->company_id}");

                    return 'created';
                });

                if ($outcome === 'created') {
                    $generated++;
                }
            } catch (QueryException $e) {
                // #6549 : course perdue sur l'index unique — le concurrent a
                // déjà émis la facture de la période, ce n'est pas une erreur.
                if ($e->getCode() === '23505') {
                    Log::info('Invoice generation skipped (concurrent run won the unique index race)', [
                        'subscription_id' => $subscription->id,
                    ]);

                    continue;
                }

                Log::error("Failed to generate invoice for subscription {$subscription->id}: {$e->getMessage()}");
            } catch (\Throwable $e) {
                Log::error("Failed to generate invoice for subscription {$subscription->id}: {$e->getMessage()}");
            }
        }

        $this->info("Generated {$generated} invoice(s).");

        return self::SUCCESS;
    }

    /**
     * Incrément atomique (upsert ON CONFLICT) — thread/concurrent-safe,
     * pattern #5223 (accounting_number_counters).
     */
    private function nextSequence(int $year): int
    {
        /** @var object{last_number: int}|null $row */
        $row = DB::selectOne(
            <<<'SQL'
            INSERT INTO billing_invoice_number_counters (year, last_number, created_at, updated_at)
            VALUES (?, 1, now(), now())
            ON CONFLICT (year)
            DO UPDATE SET last_number = billing_invoice_number_counters.last_number + 1, updated_at = now()
            RETURNING last_number
            SQL,
            [$year],
        );

        return $row === null ? 1 : (int) $row->last_number;
    }
}
