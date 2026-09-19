<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Billing\Domain\Enums\InvoiceStatus;
use App\Modules\Billing\Domain\Enums\PlanCode;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\Subscription;
use App\Modules\Billing\Infrastructure\Services\InvoiceMailer;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'billing:generate-invoices';

    protected $description = 'Generate monthly invoices for active subscriptions';

    /**
     * #7763 : repli de SÉCURITÉ uniquement. La source de vérité des prix est
     * la table `plans` (paramétrable depuis l'admin plateforme, #7430) — ces
     * montants ne servent que si le plan est introuvable en base ou sans prix
     * (`price_monthly` nul) : on facture alors les tarifs publics historiques
     * plutôt que 0 ou que de sauter la facturation, et on journalise un
     * warning pour que l'admin corrige la table.
     *
     * La table `plans` ne porte pas de devise : EUR, comme historiquement.
     */
    private const FALLBACK_PLAN_PRICES = [
        'free' => ['amount' => 0.00, 'currency' => 'EUR'],
        'pilot' => ['amount' => 29.00, 'currency' => 'EUR'],
        'operations' => ['amount' => 99.00, 'currency' => 'EUR'],
        'enterprise' => ['amount' => 299.00, 'currency' => 'EUR'],
    ];

    /** @var array<string, array{amount: float, currency: string}> cache par run */
    private array $planPricing = [];

    public function __construct(private readonly InvoiceMailer $invoiceMailer)
    {
        parent::__construct();
    }

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

                /** @var Invoice $invoice */
                $invoice = DB::transaction(function () use ($subscription, $period): Invoice {
                    // #6549 : verrou pessimiste sur l'abonnement → la
                    // numérotation est calculée sous verrou (plus de collision
                    // de numéros entre deux runs concurrents : le second attend
                    // la fin du premier avant de compter les factures).
                    /** @var Subscription $locked */
                    $locked = Subscription::query()
                        ->lockForUpdate()
                        ->findOrFail($subscription->id);

                    $plan = PlanCode::normalize((string) $locked->plan)->value;
                    // #7763 : prix lus depuis la table `plans` (admin),
                    // fallback documenté FALLBACK_PLAN_PRICES.
                    $pricing = $this->resolvePlanPricing($plan);
                    $year = now()->format('Y');
                    $seq = Invoice::where('company_id', $locked->company_id)->count() + 1;

                    // Audit #1702 : la colonne réelle est `number` (le champ
                    // `invoice_number` n'est pas fillable — le numéro était
                    // silencieusement perdu) ; amount_ht/tax_rate/plan_name/
                    // period ne sont pas des colonnes du modèle.
                    /** @var Invoice $created */
                    $created = Invoice::create([
                        'company_id' => $locked->company_id,
                        'subscription_id' => $locked->id,
                        'period' => $period,
                        'number' => sprintf('LEO-%s-%04d', $year, $seq),
                        'amount' => $pricing['amount'],
                        'tax_amount' => 0,
                        'total' => $pricing['amount'],
                        'currency' => $pricing['currency'],
                        'status' => InvoiceStatus::Sent->value,
                        'due_date' => now()->addDays(30),
                    ]);

                    $locked->update([
                        'current_period_start' => now(),
                        'current_period_end' => now()->addMonth(),
                    ]);

                    Log::info("Invoice generated: {$created->number} for company={$locked->company_id}");

                    return $created;
                });

                $generated++;

                // #7763 : email « facture émise » (PDF joint) au principal du
                // tenant, APRÈS commit — un échec d'envoi ne remet jamais en
                // cause la facture générée ni les suivantes.
                try {
                    $this->invoiceMailer->sendInvoiceIssued($invoice);
                } catch (\Throwable $mailError) {
                    Log::warning("Failed to queue issued-invoice email for invoice {$invoice->id}: {$mailError->getMessage()}");
                }
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

    /**
     * #7763 : source de prix UNIQUE — la table `plans` (paramétrée depuis
     * l'admin plateforme, #7430), rapprochée par nom insensible à la casse
     * (`Operations` en base ↔ code canonique `operations`, cf. PlanSeeder).
     * Seules les offres actives sont facturables ; un plan absent ou sans
     * prix retombe sur FALLBACK_PLAN_PRICES (warning journalisé).
     *
     * Lecture qualifiée `public.plans` : la commande tourne hors contexte
     * tenant, le search_path n'est pas garanti (même garde que
     * VerifyTrialSignup::publicTable).
     *
     * @return array{amount: float, currency: string}
     */
    private function resolvePlanPricing(string $plan): array
    {
        if (isset($this->planPricing[$plan])) {
            return $this->planPricing[$plan];
        }

        $table = DB::getDriverName() === 'pgsql' ? 'public.plans' : 'plans';

        $priceMonthly = DB::table($table)
            ->where('is_active', true)
            ->whereRaw('LOWER(name) = ?', [$plan])
            ->value('price_monthly');

        if (is_numeric($priceMonthly)) {
            return $this->planPricing[$plan] = [
                'amount' => (float) $priceMonthly,
                'currency' => 'EUR',
            ];
        }

        // PlanCode::normalize garantit un code canonique — le `?? ` est une
        // ceinture de sécurité (types stricts) : plan inconnu ⇒ 0, jamais crash.
        $fallback = self::FALLBACK_PLAN_PRICES[$plan] ?? ['amount' => 0.00, 'currency' => 'EUR'];
        Log::warning("Plan '{$plan}' has no active price in the plans table — falling back to the documented default price.", [
            'plan' => $plan,
            'fallback_amount' => $fallback['amount'],
        ]);

        return $this->planPricing[$plan] = $fallback;
    }
}
