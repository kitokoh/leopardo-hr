<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use App\Modules\TravelAgency\Domain\Models\TravelSalesSettlement;
use App\Modules\TravelAgency\Infrastructure\Services\TravelSalesSettlementService;
use Laravel\Sanctum\Sanctum;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use App\Modules\TravelAgency\Infrastructure\Services\TravelSalesSettlementService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-417 (#6069) — Synthèse Accounting (travel.sales.settled.v1).
 *
 * Couvre le contenu de la synthèse (période, unités mineures, devise,
 * nombre de paiements confirmés/remboursés), l'idempotence (même période =
 * mêmes montants, un seul événement), et la périodisation (les paiements
 * hors période sont exclus).
 * TRAVEL-417 (#6069) — synthèse Accounting des ventes confirmées.
 *
 * Verrouille : montants minor units + devises + compteurs, périodisation,
 * idempotence (même période = même montant, un seul événement), aucune
 * écriture dans les tables Accounting depuis la verticale.
 */
class TravelSalesSettlementTest extends TestCase
{
    use RefreshTenantDatabase;

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    /**
     * @param  list<array{status: string, amount: int, days_ago: int}>  $payments
     */
    private function seedPayments(Company $company, array $payments): void
    {
        app(TenantManager::class)->withinTenant($company, function () use ($company, $payments): void {
            foreach ($payments as $i => $payment) {
                $booking = TravelBooking::factory()->create();

                TravelPayment::factory()->create([
                    'company_id' => $company->id,
                    'booking_id' => $booking->id,
                    'status' => $payment['status'],
                    'amount_minor' => $payment['amount'],
                    'currency' => 'XAF',
                    'created_at' => now()->subDays($payment['days_ago']),
                ]);
            }
        });
    }

    public function test_settlement_content_is_correct(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        // Hier : 2 paiements confirmés (10 000 + 5 000) + 1 remboursement (3 000).
        $this->seedPayments($company, [
            ['status' => PaymentStatus::CONFIRMED->value, 'amount' => 10000, 'days_ago' => 1],
            ['status' => PaymentStatus::CONFIRMED->value, 'amount' => 5000, 'days_ago' => 1],
            ['status' => PaymentStatus::REFUNDED->value, 'amount' => 3000, 'days_ago' => 1],
        ]);

        $settlement = app(TravelSalesSettlementService::class)->settle(
            $company->id,
            now()->subDay()->startOfDay(),
            now()->subDay()->endOfDay(),
        )['settlement'];

        $this->assertSame(2, $settlement->confirmed_payments_count);
        $this->assertSame(15000, $settlement->confirmed_amount_minor);
        $this->assertSame(1, $settlement->refunded_count);
        $this->assertSame(3000, $settlement->refunded_amount_minor);
        $this->assertSame(12000, $settlement->net_amount_minor);

        // Événement de synthèse publié.
        $this->assertSame(1, TravelOutboxEvent::query()
            ->where('event_type', 'travel.sales.settled.v1')
            ->whereJsonContains('payload_redacted', ['net_amount_minor' => 12000])
            ->count());
    }

    public function test_settlement_is_idempotent_and_replayable(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->seedPayments($company, [
            ['status' => PaymentStatus::CONFIRMED->value, 'amount' => 8000, 'days_ago' => 1],
        ]);

        $service = app(TravelSalesSettlementService::class);

        $first = $service->settle($company->id, now()->subDay()->startOfDay(), now()->subDay()->endOfDay());
        $second = $service->settle($company->id, now()->subDay()->startOfDay(), now()->subDay()->endOfDay());

        $this->assertSame(8000, $first['settlement']->confirmed_amount_minor);
        $this->assertSame(8000, $second['settlement']->confirmed_amount_minor);
        $this->assertSame(1, TravelSalesSettlement::query()->count());

        // Un seul événement publié pour la période (pas de doublon au rejeu).
        $this->assertSame(1, TravelOutboxEvent::query()
            ->where('event_type', 'travel.sales.settled.v1')
            ->count());
    }

    public function test_payments_outside_period_are_excluded(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->seedPayments($company, [
            ['status' => PaymentStatus::CONFIRMED->value, 'amount' => 9000, 'days_ago' => 7],
            ['status' => PaymentStatus::CONFIRMED->value, 'amount' => 4000, 'days_ago' => 1],
        ]);

        $settlement = app(TravelSalesSettlementService::class)->settle(
            $company->id,
            now()->subDay()->startOfDay(),
            now()->subDay()->endOfDay(),
        )['settlement'];

        $this->assertSame(1, $settlement->confirmed_payments_count);
        $this->assertSame(4000, $settlement->confirmed_amount_minor);
    private Company $companyA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'CM',
            'currency' => 'XAF',
            'features' => ['travelagency' => true],
        ]);
        $this->companyA = $companyA;
    }

    public function test_settle_publishes_totals_for_period(): void
    {
        app(TenantManager::class)->withinTenant($this->companyA, function (): void {
            // 2 paiements confirmés + 1 remboursé dans la période.
            $this->payment('2026-09-02 10:00:00', PaymentStatus::CONFIRMED, 15000);
            $this->payment('2026-09-05 10:00:00', PaymentStatus::CONFIRMED, 25000);
            $this->payment('2026-09-06 10:00:00', PaymentStatus::REFUNDED, 5000);
            // Hors période + échec : exclus.
            $this->payment('2026-08-01 10:00:00', PaymentStatus::CONFIRMED, 99999);
            $this->payment('2026-09-03 10:00:00', PaymentStatus::FAILED, 100);

            $payload = app(TravelSalesSettlementService::class)->settle(
                (string) $this->companyA->id,
                '2026-09-01',
                '2026-09-30'
            );

            $this->assertNotNull($payload);
            $this->assertSame(2, $payload['confirmed_count']);
            $this->assertSame(40000, $payload['confirmed_amount_minor']);
            $this->assertSame(1, $payload['refunded_count']);
            $this->assertSame(5000, $payload['refunded_amount_minor']);
            $this->assertSame('XAF', $payload['currency']);

            $this->assertSame(1, TravelOutboxEvent::query()
                ->where('event_type', TravelSalesSettlementService::EVENT_SALES_SETTLED)
                ->count());

            // Aucune écriture dans les tables Accounting.
            $this->assertSame(0, \Illuminate\Support\Facades\DB::table('accounting_documents')->count());
        });
    }

    /**
     * Crée un paiement avec une date de création contrôlée (created_at n'est
     * pas mass-assignable — passage par attribut + save).
     */
    private function payment(string $createdAt, PaymentStatus $status, int $amountMinor): TravelPayment
    {
        $payment = TravelPayment::factory()->create([
            'status' => $status->value,
            'amount_minor' => $amountMinor,
            'currency' => 'XAF',
        ]);
        $payment->created_at = \Illuminate\Support\Carbon::parse($createdAt);
        $payment->save();

        return $payment;
    }

    public function test_settle_is_idempotent_per_period(): void
    {
        app(TenantManager::class)->withinTenant($this->companyA, function (): void {
            $this->payment('2026-09-02 10:00:00', PaymentStatus::CONFIRMED, 15000);

            $service = app(TravelSalesSettlementService::class);

            $first = $service->settle((string) $this->companyA->id, '2026-09-01', '2026-09-30');
            $second = $service->settle((string) $this->companyA->id, '2026-09-01', '2026-09-30');

            $this->assertNotNull($first);
            $this->assertSame($first['confirmed_amount_minor'], $second['confirmed_amount_minor']);

            // Rejeu : un SEUL événement (clé période+devise dédupliquée).
            $this->assertSame(1, TravelOutboxEvent::query()
                ->where('event_type', TravelSalesSettlementService::EVENT_SALES_SETTLED)
                ->count());
        });
    }

    public function test_settle_returns_null_without_payments(): void
    {
        $result = app(TenantManager::class)->withinTenant(
            $this->companyA,
            fn (): ?array => app(TravelSalesSettlementService::class)->settle(
                (string) $this->companyA->id,
                '2026-09-01',
                '2026-09-30'
            )
        );

        $this->assertNull($result);
        $this->assertSame(0, TravelOutboxEvent::query()->count());
    }
}
