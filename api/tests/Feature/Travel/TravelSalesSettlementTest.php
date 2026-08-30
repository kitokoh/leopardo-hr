<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use App\Modules\TravelAgency\Infrastructure\Services\TravelSalesSettlementService;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-417 (#6069) — synthèse Accounting des ventes confirmées.
 *
 * Verrouille : montants minor units + devises + compteurs, périodisation,
 * idempotence (même période = même montant, un seul événement), aucune
 * écriture dans les tables Accounting depuis la verticale.
 */
class TravelSalesSettlementTest extends TestCase
{
    use RefreshTenantDatabase;

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
