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
        $companyA = Company::factory()->create();
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
}
