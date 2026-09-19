<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Console\Commands\GenerateMonthlyInvoices;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Mail\InvoiceIssuedMail;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\Subscription;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\PendingCommand;
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

    // ── #7763 — prix depuis la table `plans` ─────────────────────────────

    public function test_invoice_amount_is_read_from_plans_table(): void
    {
        // Prix paramétré depuis l'admin (#7430) : c'est LUI qui doit être
        // facturé, pas l'ancien barème codé en dur.
        DB::table('plans')->updateOrInsert(
            ['name' => 'Operations'],
            ['price_monthly' => 149.50, 'is_active' => true],
        );

        /** @var Company $company */
        $company = Company::factory()->create();
        $this->activeSubscription($company);

        Artisan::call(GenerateMonthlyInvoices::class);

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame('149.50', (string) $invoice->amount);
        $this->assertSame('149.50', (string) $invoice->total);
        $this->assertSame('EUR', $invoice->currency);
    }

    public function test_missing_plan_price_falls_back_to_documented_default(): void
    {
        // Plan absent de la table (ou inactif) → repli sûr documenté
        // (FALLBACK_PLAN_PRICES) : on facture le tarif public historique.
        DB::table('plans')->whereRaw('LOWER(name) = ?', ['operations'])->delete();

        /** @var Company $company */
        $company = Company::factory()->create();
        $this->activeSubscription($company);

        Artisan::call(GenerateMonthlyInvoices::class);

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame('99.00', (string) $invoice->amount);
        $this->assertSame('EUR', $invoice->currency);
    }

    public function test_inactive_plan_price_is_ignored_in_favor_of_fallback(): void
    {
        DB::table('plans')->updateOrInsert(
            ['name' => 'Operations'],
            ['price_monthly' => 500.00, 'is_active' => false],
        );

        /** @var Company $company */
        $company = Company::factory()->create();
        $this->activeSubscription($company);

        Artisan::call(GenerateMonthlyInvoices::class);

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame('99.00', (string) $invoice->amount);
    }

    // ── #7763 — email « facture émise » au principal du tenant ────────────

    public function test_issued_invoice_email_is_queued_to_tenant_principal(): void
    {
        Mail::fake();

        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->activeSubscription($company);

        Artisan::call(GenerateMonthlyInvoices::class);

        $this->assertSame(1, Invoice::query()->count());
        Mail::assertQueued(
            InvoiceIssuedMail::class,
            fn (InvoiceIssuedMail $mail): bool => $mail->hasTo($principal->email)
        );
    }

    public function test_invoice_is_still_generated_when_tenant_has_no_principal(): void
    {
        Mail::fake();

        /** @var Company $company */
        $company = Company::factory()->create();
        $this->activeSubscription($company);

        Artisan::call(GenerateMonthlyInvoices::class);

        // Pas de principal → pas d'email, mais la facturation N'ÉCHOUE PAS.
        $this->assertSame(1, Invoice::query()->count());
        Mail::assertNotQueued(InvoiceIssuedMail::class);
    }
}
