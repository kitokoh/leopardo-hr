<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Enums\InvoiceStatus;
use App\Modules\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\Subscription;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DEP-BC21 (#6251) — supervision billing : `billing:report` agrège des
 * compteurs non nominatifs et ne lève pas sur une base réelle.
 */
class BillingReportCommandTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_report_aggregates_counts_without_error(): void
    {
        $company = Company::factory()->create();
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'business',
            'status' => SubscriptionStatus::Active->value,
            'payment_method' => 'stripe',
        ]);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'business',
            'status' => SubscriptionStatus::PastDue->value,
            'payment_method' => 'stripe',
        ]);

        $subscription = Subscription::query()->firstOrFail();
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'number' => 'LEO-REPORT-1',
            'amount' => 99.00,
            'currency' => 'EUR',
            'total' => 99.00,
            'status' => InvoiceStatus::Overdue->value,
            'due_date' => now()->subDay(),
        ]);
        DB::table('payments')->insert([
            'invoice_id' => $invoice->id,
            'company_id' => $company->id,
            'amount' => 99.00,
            'currency' => 'EUR',
            'method' => 'card',
            'status' => 'completed',
            'paid_at' => now(),
            'created_at' => now(),
        ]);

        $exit = Artisan::call('billing:report');
        $output = Artisan::output();

        self::assertSame(0, $exit);
        self::assertStringContainsString('active', $output);
        self::assertStringContainsString('past_due', $output);
        self::assertStringContainsString('overdue', $output);
        self::assertStringContainsString('completed', $output);
    }

    public function test_report_handles_empty_database(): void
    {
        $exit = Artisan::call('billing:report');

        self::assertSame(0, $exit, 'le rapport ne lève pas sur une base vide');
    }
}
