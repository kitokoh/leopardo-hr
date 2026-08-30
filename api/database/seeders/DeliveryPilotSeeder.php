<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Delivery\Domain\Models\Delivery;
use App\Modules\Delivery\Domain\Models\DeliveryCodSettlement;
use App\Modules\Delivery\Domain\Models\DeliveryEvent;
use App\Modules\Delivery\Domain\Models\DeliveryRoute;
use App\Modules\Delivery\Domain\Models\DeliveryStop;
use Illuminate\Database\Seeder;

/**
 * BC-26-D12 (#6297) — Seed pilote synthétique DeliveryAgency.
 *
 * Environnement reproductible, **100 % synthétique** (zéro donnée réelle,
 * zéro secret — garde MAT-012) pour agents, reviewers et pilotes : le
 * parcours complet de la spec §7.1 est pré-rempli sur le tenant
 * `delivery-pilot-alpha` :
 *
 *  - 1 principal (delivery.admin/dispatcher) + 1 livreur (delivery.rider) ;
 *  - 3 colis (dont 1 COD 15 000 DZD), 1 tournée affectée livreur + véhicule,
 *    2 livrés avec POD, 1 échec → retour, tournée **close** (totaux) ;
 *  - 1 règlement COD (expected = collected = 15 000, en attente de
 *    réconciliation BC-08 — DELIVERY-205).
 *
 * Réentrant : si le tenant pilote existe déjà, skip (ne plante jamais).
 * Usage : php artisan db:seed --class=DeliveryPilotSeeder
 * (environnements pilote/demo uniquement — jamais en production).
 */
class DeliveryPilotSeeder extends Seeder
{
    private const SHARED_SCHEMA = 'shared_tenants';

    private const PILOT = [
        'slug' => 'delivery-pilot-alpha',
        'name' => 'Delivery Pilot Alpha',
        'domain' => 'alpha.delivery-pilot.leopardo.test',
    ];

    public function run(): void
    {
        $existing = Company::query()->where('slug', self::PILOT['slug'])->first();

        if ($existing instanceof Company) {
            $this->command?->warn('Pilote Delivery déjà présent — skip (réentrant).');

            return;
        }

        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => self::PILOT['name'],
            'slug' => self::PILOT['slug'],
            'schema_name' => self::SHARED_SCHEMA,
            'tenancy_type' => 'shared',
            'country' => 'DZ',
            'currency' => 'DZD',
            'status' => 'active',
        ]);

        $company->setFeature('delivery', true);
        $company->save();

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            $this->seedEmployee($company, 'manager', 'principal', 'Nadir', 'Benali');
            $rider = $this->seedEmployee($company, 'employee', null, 'Yacine', 'Hamidi');

            $this->seedJourney($company, $rider->id);
        });

        $this->command?->info('Pilote Delivery créé : '.self::PILOT['slug']);
    }

    private function seedEmployee(Company $company, string $role, ?string $managerRole, string $first, string $last): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => 'active',
            'first_name' => $first,
            'last_name' => $last,
            'email' => strtolower($first).'.'.strtolower($last).'@'.self::PILOT['domain'],
            'matricule' => 'EMP-DLV-'.($role === 'manager' ? '001' : '002'),
        ]);

        return $employee;
    }

    /**
     * Parcours agence complet (spec §7.1) : colis → tournée → événements →
     * clôture → règlement COD.
     */
    private function seedJourney(Company $company, int $riderId): void
    {
        $now = now();

        // 3 colis déterministes (dont 1 COD).
        $parcels = [
            ['DLV-2026-900001', 0, 'Client Alpha', '12 Rue Didouche, Alger'],
            ['DLV-2026-900002', 15000, 'Client Bêta', '45 Bd Zighout Youcef, Alger'],
            ['DLV-2026-900003', 0, 'Client Gamma', '7 Cité Universitaire, Alger'],
        ];

        $deliveries = [];
        foreach ($parcels as [$reference, $cod, $contact, $address]) {
            /** @var Delivery $delivery */
            $delivery = Delivery::query()->create([
                'company_id' => $company->id,
                'reference' => $reference,
                'source' => 'manual',
                'type' => 'parcel',
                'status' => 'delivered',
                'cod_amount_minor' => $cod,
                'dropoff_contact' => $contact,
                'dropoff_address' => $address,
                'delivered_at' => $now,
            ]);
            $deliveries[] = $delivery;
        }

        // Le 3e colis est en échec → retour.
        $deliveries[2]->forceFill(['status' => 'returned', 'returned_at' => $now])->save();

        // Tournée close (2 livrés, 1 retour, COD 15 000).
        /** @var DeliveryRoute $route */
        $route = DeliveryRoute::query()->create([
            'company_id' => $company->id,
            'route_date' => $now->toDateString(),
            'driver_id' => $riderId,
            'vehicle_code' => 'VH-ALG-042',
            'zone' => 'Alger Centre',
            'status' => 'completed',
            'deliveries_count' => 3,
            'delivered_count' => 2,
            'failed_count' => 1,
            'cod_collected_minor' => 15000,
            'closed_at' => $now,
        ]);

        foreach ($deliveries as $i => $delivery) {
            DeliveryStop::query()->create([
                'company_id' => $company->id,
                'route_id' => $route->id,
                'delivery_id' => $delivery->id,
                'sort_order' => $i + 1,
                'status' => $i === 2 ? 'failed' : 'delivered',
                'address' => $delivery->dropoff_address,
                'contact' => $delivery->dropoff_contact,
                'proof_id' => $i === 2 ? null : 900000 + $i,
                'delivered_at' => $i === 2 ? null : $now,
            ]);
        }

        // Événements de tracking (idempotents, géolocalisés).
        $events = [
            [1, 'picked_up', 36.7538, 3.0588],
            [1, 'out_for_delivery', 36.7558, 3.0540],
            [1, 'arrived', 36.7520, 3.0500],
            [1, 'delivered', 36.7520, 3.0500],
            [2, 'picked_up', 36.7620, 3.0600],
            [2, 'delivered', 36.7620, 3.0600],
        ];
        foreach ($events as [$num, $type, $lat, $lng]) {
            DeliveryEvent::query()->create([
                'company_id' => $company->id,
                'delivery_id' => $deliveries[$num - 1]->id,
                'type' => $type,
                'event_at' => $now,
                'latitude' => $lat,
                'longitude' => $lng,
                'origin' => 'mobile',
                'payload' => $type === 'delivered' ? ['proof_document_id' => 900000 + $num] : null,
            ]);
        }

        // Règlement COD (expected = collected ; réconciliation BC-08 = DELIVERY-205).
        DeliveryCodSettlement::query()->create([
            'company_id' => $company->id,
            'route_id' => $route->id,
            'driver_id' => $riderId,
            'expected_minor' => 15000,
            'collected_minor' => 15000,
            'commission_minor' => 0,
            'status' => 'collected',
        ]);
    }
}
