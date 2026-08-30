<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Models\DeliveryCodSettlement;
use App\Modules\Delivery\Domain\Models\DeliveryRoute;
use App\Modules\Delivery\Domain\Models\DeliveryStop;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DELIVERY-207 (#6291) — Rapports & KPIs livraison.
 *
 * - read model déterministe : 2 recalculs → résultats identiques (test golden,
 *   montants et taux vérifiés à la main) ;
 * - ventilation par source (v0.2 multi-tenant) ;
 * - RBAC 401/403 + isolation tenant (les données du tenant B n'apparaissent
 *   jamais dans le summary du tenant A).
 */
class DeliveryReportApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $company->setFeature('delivery', true);
        $company->save();
        $this->company = $company;
    }

    private function manager(): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return $manager;
    }

    private function seedDeliveries(): void
    {
        $created = Carbon::parse('2026-08-01 09:00:00');
        $now = now();

        $manualIds = [];
        // 2 livrées (COD 5000 + 3000) + 1 échec, source manual.
        foreach ([5000, 3000] as $i => $cod) {
            $delivery = Delivery::query()->create([
                'company_id' => $this->company->id,
                'reference' => 'DLV-2026-1000'.($i + 1),
                'source' => 'manual',
                'type' => 'parcel',
                'status' => 'delivered',
                'cod_amount_minor' => $cod,
                'dropoff_contact' => 'Client',
                'dropoff_address' => 'Alger',
            ]);
            $delivery->forceFill([
                'created_at' => $created->copy()->addHours($i),
                'delivered_at' => $created->copy()->addHours($i + 2),
                'updated_at' => $now,
            ])->save();
            $manualIds[] = (int) $delivery->id;
        }

        $failed = Delivery::query()->create([
            'company_id' => $this->company->id,
            'reference' => 'DLV-2026-10003',
            'source' => 'manual',
            'type' => 'parcel',
            'status' => 'failed',
            'cod_amount_minor' => 8000,
            'dropoff_contact' => 'Client',
            'dropoff_address' => 'Alger',
        ]);
        $failed->forceFill([
            'created_at' => $created->copy()->addHours(3),
            'failed_at' => $created->copy()->addHours(4),
            'updated_at' => $now,
        ])->save();
        $manualIds[] = (int) $failed->id;

        // 1 livrée source restaurant (COD 0), hors tournée (pas de stop).
        $resto = Delivery::query()->create([
            'company_id' => $this->company->id,
            'reference' => 'DLV-2026-10004',
            'source' => 'restaurant',
            'source_reference' => 'RST-2026-0001',
            'type' => 'food',
            'status' => 'delivered',
            'cod_amount_minor' => 0,
            'dropoff_contact' => 'Resto Client',
            'dropoff_address' => 'Bab Ezzouar',
        ]);
        $resto->forceFill([
            'created_at' => $created->copy()->addHours(5),
            'delivered_at' => $created->copy()->addHours(6),
            'updated_at' => $now,
        ])->save();

        // Tournée du livreur 5 : les 3 colis manual (délais 2h, 2h, 1h).
        $route = DeliveryRoute::query()->create([
            'company_id' => $this->company->id,
            'route_date' => '2026-08-01',
            'driver_id' => 5,
            'vehicle_code' => 'VEH-001',
            'status' => 'completed',
            'deliveries_count' => 3,
            'delivered_count' => 2,
            'failed_count' => 1,
            'cod_collected_minor' => 7000,
            'closed_at' => $created->copy()->addHours(7),
        ]);

        foreach ($manualIds as $index => $deliveryId) {
            DeliveryStop::query()->create([
                'company_id' => $this->company->id,
                'route_id' => $route->id,
                'delivery_id' => $deliveryId,
                'sort_order' => $index + 1,
                'status' => $index < 2 ? 'delivered' : 'failed',
                'address' => 'Alger',
                'contact' => 'Client',
                'delivered_at' => $index < 2 ? $created->copy()->addHours($index + 2) : null,
            ]);
        }

        // COD collecté : 7000 sur la tournée du livreur 5.
        DeliveryCodSettlement::query()->create([
            'company_id' => $this->company->id,
            'route_id' => $route->id,
            'driver_id' => 5,
            'expected_minor' => 8000,
            'collected_minor' => 7000,
            'status' => 'collected',
            'created_at' => $created->copy()->addHours(6),
        ]);
    }

    public function test_summary_is_deterministic_and_hand_computed(): void
    {
        Sanctum::actingAs($this->manager());
        $this->seedDeliveries();

        $body = [
            'from' => '2026-08-01',
            'to' => '2026-08-31',
        ];

        $first = $this->getJson('/api/v1/delivery/deliveries/reports/summary?'.http_build_query($body))->assertOk();
        $second = $this->getJson('/api/v1/delivery/deliveries/reports/summary?'.http_build_query($body))->assertOk();

        // Déterminisme strict : 2 recalculs → mêmes résultats.
        self::assertSame($first->json('data'), $second->json('data'));

        // Totaux vérifiés à la main : 4 livraisons, 3 livrées, 1 échec.
        $totals = $first->json('data.totals');
        self::assertSame(4, $totals['deliveries']);
        self::assertSame(3, $totals['delivered']);
        self::assertSame(1, $totals['failed']);
        self::assertSame(75.0, $totals['success_rate_pct']);
        self::assertSame(8000, $totals['cod_expected_minor']);      // 5000 + 3000 (livrées)
        self::assertSame(7000, $totals['cod_collected_minor']);     // règlement existant
        self::assertSame(100, $totals['avg_delivery_delay_minutes']); // (2h, 2h, 1h) → 100 min

        // Ventilation par source : manual 3 (2 livrées), restaurant 1.
        $bySource = collect($first->json('data.by_source'))->keyBy('source');
        self::assertSame(3, $bySource['manual']['deliveries']);
        self::assertSame(2, $bySource['manual']['delivered']);
        self::assertSame(1, $bySource['restaurant']['deliveries']);
        self::assertSame(100.0, $bySource['restaurant']['success_rate_pct']);

        // Par jour : 2026-08-01 concentre les 4 livraisons.
        $byDay = collect($first->json('data.by_day'))->keyBy('date');
        self::assertSame(4, $byDay['2026-08-01']['deliveries']);

        // Par livreur : driver 5 → 3 livraisons.
        $byDriver = collect($first->json('data.by_driver'))->keyBy('driver_id');
        self::assertSame(3, $byDriver[5]['deliveries']);
    }

    public function test_summary_requires_manager_and_authentication(): void
    {
        $this->getJson('/api/v1/delivery/deliveries/reports/summary')->assertStatus(401);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        Sanctum::actingAs($employee);
        $this->getJson('/api/v1/delivery/deliveries/reports/summary')->assertStatus(403);
    }

    public function test_summary_is_tenant_scoped(): void
    {
        Sanctum::actingAs($this->manager());
        $this->seedDeliveries();

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $other->setFeature('delivery', true);
        $other->save();

        /** @var Employee $managerB */
        $managerB = Employee::factory()->create([
            'company_id' => $other->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        Sanctum::actingAs($managerB);

        $response = $this->getJson('/api/v1/delivery/deliveries/reports/summary?from=2026-08-01&to=2026-08-31')
            ->assertOk();

        self::assertSame(0, $response->json('data.totals.deliveries'));
        self::assertSame([], $response->json('data.by_source'));
    }

    public function test_export_returns_csv_stream(): void
    {
        Sanctum::actingAs($this->manager());
        $this->seedDeliveries();

        $response = $this->get('/api/v1/delivery/deliveries/reports/export?from=2026-08-01&to=2026-08-31');

        $response->assertOk();
        self::assertStringContainsString('text/csv', $response->headers->get('Content-Type') ?? '');
        self::assertStringContainsString('.csv', $response->headers->get('Content-Disposition') ?? '');

        $csv = $response->streamedContent();
        self::assertStringContainsString('reference,source,type,status,cod_amount_minor', $csv);
        self::assertStringContainsString('DLV-2026-10001', $csv);
        self::assertStringContainsString('DLV-2026-10004', $csv);
    }

    public function test_summary_rejects_invalid_date_range(): void
    {
        Sanctum::actingAs($this->manager());

        $this->getJson('/api/v1/delivery/deliveries/reports/summary?from=2026-09-01&to=2026-08-01')
            ->assertStatus(422);
    }
}
