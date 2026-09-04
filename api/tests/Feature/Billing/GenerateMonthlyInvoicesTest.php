<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Console\Commands\GenerateMonthlyInvoices;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\Subscription;
use Illuminate\Support\Facades\Artisan;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB
use Illuminate\Testing\PendingCommand;

/**
 * #6549 — GenerateMonthlyInvoices : anti-doublon par période et
 * numérotation sans collision.
 */
class GenerateMonthlyInvoicesTest extends TestCase
{
    use RefreshTenantDatabase;

    private function activeSubscription(Company $company): Subscription
    {
        /** @var Subscription $subscription */
        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan' => 'operations',
            'status' => 'active',
            'current_period_start' => now()->subMonths(1)->startOfMonth(),
            'current_period_end' => now()->subDays(1),
        ]);

        return $subscription;
    }

    public function test_generates_one_invoice_per_subscription_for_the_period(): void
    {
/** @var Company $company */
        $company = Company::factory()->create();
        $subscription = $this->activeSubscription($company);

        $exitCode = Artisan::call(GenerateMonthlyInvoices::class);

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, Invoice::query()->count());

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame($subscription->id, $invoice->subscription_id);
        $this->assertSame(now()->format('Y-m'), $invoice->period);
        $this->assertSame('LEO-'.now()->format('Y').'-0001', $invoice->number);
        $this->assertSame('pending', $invoice->status);

        // Période suivante avancée.
        $this->assertNotNull($subscription->refresh()->current_period_end);
    }

    public function test_second_run_does_not_duplicate_invoices_for_the_same_period(): void
    {
/** @var Company $company */
        $company = Company::factory()->create();
        $this->activeSubscription($company);

        Artisan::call(GenerateMonthlyInvoices::class);
        Artisan::call(GenerateMonthlyInvoices::class);

        // Une seule facture pour la période courante (garde douce + index
        // unique (company_id, subscription_id, period)).
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_invoice_numbers_are_contiguous_and_never_collide(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create();
        /** @var Company $companyB */
        $companyB = Company::factory()->create();
        $subscriptionA = $this->activeSubscription($companyA);
        $subscriptionB = $this->activeSubscription($companyB);

        Artisan::call(GenerateMonthlyInvoices::class);

        $numbers = Invoice::query()
            ->orderBy('id')
            ->pluck('number')
            ->all();

        // Numérotation séquentielle par entreprise (count()+1 sous verrou).
        $this->assertSame('LEO-'.now()->format('Y').'-0001', $numbers[0]);
        $this->assertSame('LEO-'.now()->format('Y').'-0001', $numbers[1]);
        $this->assertCount(2, array_unique($numbers));
        $this->assertSame($subscriptionA->id, Invoice::query()->where('company_id', $companyA->id)->value('subscription_id'));
        $this->assertSame($subscriptionB->id, Invoice::query()->where('company_id', $companyB->id)->value('subscription_id'));
    }

    public function test_subscription_already_billed_for_period_is_skipped(): void
    {
/** @var Company $company */
        $company = Company::factory()->create();
        $subscription = $this->activeSubscription($company);

        // Facture déjà émise pour la période courante.
        Invoice::create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'period' => now()->format('Y-m'),
            'number' => 'LEO-'.now()->format('Y').'-0001',
            'amount' => 99.00,
            'tax_amount' => 0,
            'total' => 99.00,
            'currency' => 'EUR',
            'status' => 'pending',
            'due_date' => now()->addDays(30),
        ]);

        Artisan::call(GenerateMonthlyInvoices::class);

        $this->assertSame(1, Invoice::query()->count());
    }


    public function test_two_runs_generate_a_single_invoice_per_period(): void
    {
        $subscription = $this->activeSubscription();

        /** @var PendingCommand $first */
        $first = $this->artisan('billing:generate-invoices');
        $first->run();

        /** @var PendingCommand $second */
        $second = $this->artisan('billing:generate-invoices');
        $second->run();

        $this->assertSame(1, Invoice::query()->where('subscription_id', $subscription->getKey())->count());
        $this->assertSame('pilot', $subscription->refresh()->plan);

        $invoice = Invoice::query()->where('subscription_id', $subscription->getKey())->firstOrFail();
        $this->assertSame(now()->format('Y-m'), $invoice->period);
    }


    public function test_existing_invoice_for_period_is_detected_by_dedup_check(): void
    {
        $subscription = $this->activeSubscription();

        // Facture déjà émise pour la période courante (ex. générée par un
        // run précédent) → le contrôle applicatif doit la détecter et skippper.
        Invoice::create([
            'company_id' => $subscription->company_id,
            'subscription_id' => $subscription->id,
            'period' => now()->format('Y-m'),
            'number' => 'LEO-'.now()->format('Y').'-0001',
            'amount' => 29.00,
            'tax_amount' => 0,
            'total' => 29.00,
            'currency' => 'EUR',
            'status' => 'pending',
            'due_date' => now()->addDays(30),
        ]);

        /** @var PendingCommand $cmd */
        $cmd = $this->artisan('billing:generate-invoices');
        $cmd->run();

        $this->assertSame(1, Invoice::query()->where('subscription_id', $subscription->getKey())->count());
    }


    public function test_invoice_numbers_are_contiguous_and_atomic(): void
    {
        $first = $this->activeSubscription();
        $second = $this->activeSubscription();

        /** @var PendingCommand $cmd */
        $cmd = $this->artisan('billing:generate-invoices');
        $cmd->run();

        $numbers = Invoice::query()->orderBy('number')->pluck('number')->all();

        $this->assertCount(2, $numbers);
        $this->assertSame('LEO-'.now()->format('Y').'-0001', $numbers[0]);
        $this->assertSame('LEO-'.now()->format('Y').'-0002', $numbers[1]);

        // L'incrément atomique est persistant : un run suivant continue la
        // séquence au lieu de recompter les lignes (count()+1).
        $third = $this->activeSubscription();
        /** @var PendingCommand $cmd2 */
        $cmd2 = $this->artisan('billing:generate-invoices');
        $cmd2->run();

        $next = Invoice::query()
            ->where('subscription_id', $third->getKey())
            ->firstOrFail();

        $this->assertSame('LEO-'.now()->format('Y').'-0003', $next->number);
    }


    public function test_cancelled_plan_inactive_subscription_is_skipped(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'pilot',
            'status' => 'cancelled',
            'current_period_end' => now()->subDay(),
        ]);

        /** @var PendingCommand $cmd */
        $cmd = $this->artisan('billing:generate-invoices');
        $cmd->run();

        $this->assertSame(0, Invoice::query()->count());
    }


    public function test_counters_table_is_used_for_atomic_numbering(): void
    {
        $this->activeSubscription();

        /** @var PendingCommand $cmd */
        $cmd = $this->artisan('billing:generate-invoices');
        $cmd->run();

        /** @var object{last_number: int}|null $counter */
        $counter = DB::table('billing_invoice_number_counters')->first();
        $this->assertNotNull($counter);
        $this->assertSame(1, $counter->last_number);
    }
}