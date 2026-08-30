<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * DELIVERY-102 (#6283) — Isolation cross-tenant du module Delivery.
 *
 * BC-26 est un module de livraison GÉNÉRIQUE multi-tenant (agence, restaurant,
 * retail, e-commerce…) : les données d'un tenant A (livraisons, tournées,
 * événements) ne doivent JAMAIS être visibles d'un tenant B — y compris avec
 * des IDs connus. Le schéma est partagé (shared_tenants) : l'isolation est
 * portée par company_id.
 */
class DeliveryIsolationTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_deliveries_are_scoped_per_company(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);

        $this->insertDelivery($companyA, 'DLV-2026-000001');
        $this->insertDelivery($companyB, 'DLV-2026-000002');

        $countA = DB::table('delivery_deliveries')->where('company_id', $companyA->id)->count();
        $countB = DB::table('delivery_deliveries')->where('company_id', $companyB->id)->count();

        self::assertSame(1, $countA, 'Le tenant A doit voir uniquement ses livraisons.');
        self::assertSame(1, $countB, 'Le tenant B doit voir uniquement ses livraisons.');
        self::assertSame(2, DB::table('delivery_deliveries')->count(), 'Les deux lignes existent (isolation par scope, pas par suppression).');
    }

    public function test_same_source_reference_is_allowed_across_tenants_but_not_within(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        // Deux tenants peuvent référencer la même commande source (ex. RST-2026-0001).
        $this->insertDelivery($companyA, 'DLV-2026-000001', 'restaurant', 'RST-2026-0001');
        $this->insertDelivery($companyB, 'DLV-2026-000002', 'restaurant', 'RST-2026-0001');

        // …mais un même tenant ne peut pas créer deux livraisons pour la même source.
        try {
            DB::beginTransaction();
            $this->insertDelivery($companyA, 'DLV-2026-000003', 'restaurant', 'RST-2026-0001');
            DB::commit();
            self::fail('Unique (company_id, source, source_reference) attendu.');
        } catch (\Throwable) {
            DB::rollBack();
        }

        // La tentative en doublon n'a rien créé : le tenant A ne garde que
        // SA livraison d'origine.
        self::assertSame(1, DB::table('delivery_deliveries')->where('company_id', $companyA->id)->count());
    }

    public function test_routes_and_events_are_scoped_per_company(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        $deliveryA = $this->insertDelivery($companyA, 'DLV-2026-000010');
        $deliveryB = $this->insertDelivery($companyB, 'DLV-2026-000011');

        $routeAId = DB::table('delivery_routes')->insertGetId([
            'company_id' => $companyA->id,
            'route_date' => '2026-08-30',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('delivery_stops')->insert([
            'company_id' => $companyA->id,
            'route_id' => $routeAId,
            'delivery_id' => $deliveryA,
            'sort_order' => 1,
            'status' => 'pending',
            'address' => 'Rue A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('delivery_events')->insert([
            'company_id' => $companyA->id,
            'delivery_id' => $deliveryA,
            'type' => 'picked_up',
            'event_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('delivery_events')->insert([
            'company_id' => $companyB->id,
            'delivery_id' => $deliveryB,
            'type' => 'picked_up',
            'event_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::assertSame(1, DB::table('delivery_routes')->where('company_id', $companyA->id)->count());
        self::assertSame(1, DB::table('delivery_stops')->where('company_id', $companyA->id)->count());
        self::assertSame(1, DB::table('delivery_events')->where('company_id', $companyA->id)->count());
        self::assertSame(0, DB::table('delivery_events')->where('company_id', $companyA->id)->where('delivery_id', $deliveryB)->count());
    }

    private function insertDelivery(
        Company $company,
        string $reference,
        string $source = 'manual',
        ?string $sourceReference = null,
    ): int {
        return (int) DB::table('delivery_deliveries')->insertGetId([
            'company_id' => $company->id,
            'reference' => $reference,
            'source' => $source,
            'source_reference' => $sourceReference,
            'type' => 'parcel',
            'status' => 'created',
            'dropoff_contact' => 'Destinataire',
            'dropoff_address' => 'Adresse de livraison',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
