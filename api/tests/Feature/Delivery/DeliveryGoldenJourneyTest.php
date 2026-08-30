<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Models\DeliveryCodSettlement;
use App\Modules\Delivery\Domain\Models\DeliveryNotification;
use App\Modules\Delivery\Domain\Models\DeliveryRoute;
use App\Modules\Delivery\Domain\Models\DeliveryTrackingShare;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-26-D12 (#6297) — Golden journey DeliveryAgency.
 *
 * Parcours E2E complet du seed pilote synthétique jusqu'aux rapports :
 * création multi-sources → tournée → affectation → route du jour (rider) →
 * événements (POD) → clôture → règlement COD (collect/settle/reconcile) →
 * lien public de suivi → notifications → KPIs.
 */
class DeliveryGoldenJourneyTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $manager;

    private Employee $rider;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $company->setFeature('delivery', true);
        $company->save();
        $this->company = $company;

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'id' => 90,
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        $this->manager = $manager;

        /** @var Employee $rider */
        $rider = Employee::factory()->create([
            'id' => 91,
            'company_id' => $this->company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        $this->rider = $rider;
    }

    public function test_full_journey_from_seed_to_report(): void
    {
        // ── 1. Seed pilote : 2 livraisons manual + 1 restaurant (contrats sources).
        Sanctum::actingAs($this->manager);

        $manual = [];
        foreach ([5000, 3000] as $i => $cod) {
            $created = $this->postJson('/api/v1/delivery/deliveries', [
                'source' => 'manual',
                'type' => 'parcel',
                'cod_amount_minor' => $cod,
                'dropoff_contact' => 'Client '.$i,
                'dropoff_phone' => '+2135550'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'dropoff_address' => 'Alger, Rue '.$i,
            ])->assertStatus(201)->json('data');
            $manual[] = (int) $created['id'];
        }

        $resto = $this->postJson('/api/v1/delivery/deliveries', [
            'source' => 'restaurant',
            'source_reference' => 'RST-2026-0001',
            'type' => 'food',
            'dropoff_contact' => 'Resto Client',
            'dropoff_phone' => '+21355509999',
            'dropoff_address' => 'Bab Ezzouar',
        ])->assertStatus(201)->json('data');

        // ── 2. Tournée du jour + affectation au rider 91.
        $route = $this->postJson('/api/v1/delivery/deliveries/routes', [
            'route_date' => now()->toDateString(),
            'zone' => 'Alger Centre',
            'delivery_ids' => [...$manual, (int) $resto['id']],
        ])->assertStatus(201)->json('data');

        $this->postJson(sprintf('/api/v1/delivery/deliveries/routes/%d/assign', $route['id']), [
            'driver_id' => 91,
            'vehicle_code' => 'VEH-AG-01',
        ])->assertOk()->assertJsonPath('data.status', 'assigned');

        // ── 3. Le rider voit SA tournée du jour.
        Sanctum::actingAs($this->rider);
        $today = $this->getJson('/api/v1/delivery/deliveries/routes/today')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.driver_id', 91);
        $stops = collect($today->json('data.0.stops'));

        // ── 4. Exécution : picked_up → en_route → arrived → delivered (POD).
        $firstStopId = (int) $stops->first()['id'];

        $this->postJson('/api/v1/delivery/deliveries/events', [
            'delivery_id' => (int) $stops->first()['delivery_id'],
            'type' => 'picked_up',
            'origin' => 'mobile',
        ])->assertStatus(201);

        foreach (['en_route', 'arrived'] as $status) {
            $this->postJson(sprintf('/api/v1/delivery/deliveries/stops/%d/status', $firstStopId), [
                'status' => $status,
            ])->assertOk();
        }

        $this->postJson(sprintf('/api/v1/delivery/deliveries/stops/%d/status', $firstStopId), [
            'status' => 'delivered',
            'proof_document_id' => 1001,
        ])->assertOk()->assertJsonPath('data.status', 'delivered');

        // Le 2e stop : colis pris puis échec (client absent) — la machine à
        // états interdit failed depuis assigned (invariant DELIVERY-103).
        $secondStopId = (int) $stops->get(1)['id'];
        $this->postJson('/api/v1/delivery/deliveries/events', [
            'delivery_id' => (int) $stops->get(1)['delivery_id'],
            'type' => 'picked_up',
            'origin' => 'mobile',
        ])->assertStatus(201);

        $this->postJson(sprintf('/api/v1/delivery/deliveries/stops/%d/status', $secondStopId), [
            'status' => 'failed',
        ])->assertOk();

        // ── 5. Clôture de la tournée → totaux dénormalisés.
        Sanctum::actingAs($this->manager);
        $closed = $this->postJson(sprintf('/api/v1/delivery/deliveries/routes/%d/close', $route['id']))
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.deliveries_count', 3)
            ->assertJsonPath('data.delivered_count', 1)
            ->assertJsonPath('data.failed_count', 1)
            ->json('data');
        self::assertSame(5000, $closed['cod_collected_minor']);

        // ── 6. Règlement COD : create → collect → settle → reconcile.
        $settlement = $this->postJson(sprintf('/api/v1/delivery/deliveries/routes/%d/settlement', $route['id']))
            ->assertStatus(201)
            ->assertJsonPath('data.expected_minor', 5000)
            ->json('data');

        $this->postJson(sprintf('/api/v1/delivery/deliveries/cod-settlements/%d/collect', $settlement['id']), [
            'collected_minor' => 5000,
        ])->assertOk()->assertJsonPath('data.status', 'collected');

        $this->postJson(sprintf('/api/v1/delivery/deliveries/cod-settlements/%d/settle', $settlement['id']))
            ->assertOk()->assertJsonPath('data.status', 'settled');

        $this->postJson(sprintf('/api/v1/delivery/deliveries/cod-settlements/%d/reconcile', $settlement['id']))
            ->assertOk()->assertJsonPath('data.status', 'reconciled');

        // ── 7. Lien public de suivi (token = credential, sans auth).
        $link = $this->postJson(sprintf('/api/v1/delivery/deliveries/%d/tracking-link', (int) $resto['id']))
            ->assertOk()->json('data');

        $token = (string) str($link['tracking_url'])->afterLast('/');
        $this->getJson('/api/v1/deliveries/tracking/'.$token)
            ->assertOk()
            ->assertJsonPath('data.reference', $resto['reference']);

        // ── 8. Notifications planifiées (outbox) — 6 événements notifiables :
        // colis 1 (picked_up, out_for_delivery, arrived, delivered) + colis 2
        // (picked_up, failed).
        Sanctum::actingAs($this->manager);
        $this->getJson('/api/v1/delivery/deliveries/notifications?per_page=100')
            ->assertOk()
            ->assertJsonCount(6, 'data');

        // ── 9. KPIs : le summary reflète le journey (3 livraisons, 1 livrée).
        $summary = $this->getJson('/api/v1/delivery/deliveries/reports/summary')
            ->assertOk()
            ->json('data.totals');

        self::assertSame(3, $summary['deliveries']);
        self::assertSame(1, $summary['delivered']);
        self::assertSame(1, $summary['failed']);
        self::assertSame(5000, $summary['cod_expected_minor']);
        self::assertSame(5000, $summary['cod_collected_minor']);
    }
}
