<?php

declare(strict_types=1);

namespace Tests\Feature\FuelStation;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelImport;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use App\Modules\FuelStation\Domain\Models\FuelMeterRegister;
use App\Modules\FuelStation\Domain\Models\FuelReportSnapshot;
use App\Modules\FuelStation\Domain\Models\FuelSite;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use App\Modules\FuelStation\Domain\Policies\FuelEquipmentPolicy;
use App\Modules\FuelStation\Domain\Policies\FuelImportPolicy;
use App\Modules\FuelStation\Domain\Policies\FuelIncidentPolicy;
use App\Modules\FuelStation\Domain\Policies\FuelMaintenanceTaskPolicy;
use App\Modules\FuelStation\Domain\Policies\FuelReferencePolicy;
use App\Modules\FuelStation\Domain\Policies\FuelSitePolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #8177 — un modèle Fuel inscrit plusieurs fois à `Gate::policy()`
 * rendait la policy effective dépendante de l'ordre des lignes (écrasement
 * silencieux : la dernière ligne gagnait). Ce test épingle le mapping
 * canonique « 1 modèle → 1 policy » et couvre deux impacts mesurés :
 *
 *  - GET /fuel-station/sites/{id} : l'inscription effective historique
 *    (FuelStationPolicy, typée FuelStation) levait une TypeError sur un
 *    FuelSite → 500 latent ;
 *  - les inscriptions contradictoires sont désormais impossibles à
 *    réintroduire sans casser ce test ET le garde CI
 *    dev-hub/tools/check-policies-single-point.sh.
 */
class FuelGatePolicyRegistrationTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_each_fuel_model_is_bound_to_its_canonical_policy(): void
    {
        $expected = [
            // FUEL-010 : signalement par tout employé (la policy maintenance
            // historique, manager-only, rendait opérateurs et workflow 403).
            FuelIncident::class => FuelIncidentPolicy::class,
            // Policy dédiée, typée FuelMaintenanceTask (couvre viewAny/view/
            // create/update des routes enregistrées).
            FuelMaintenanceTask::class => FuelMaintenanceTaskPolicy::class,
            // Policy dédiée, typée FuelSite — FuelStationPolicy (typée
            // FuelStation) portait une TypeError latente sur /sites/{id}.
            FuelSite::class => FuelSitePolicy::class,
            // Cuves et compteurs = équipements (policy typée dédiée).
            FuelTank::class => FuelEquipmentPolicy::class,
            FuelMeterRegister::class => FuelEquipmentPolicy::class,
            // Les contrôleurs autorisent `viewReports` sur FuelReportSnapshot :
            // seule FuelReferencePolicy porte cette habilité.
            FuelReportSnapshot::class => FuelReferencePolicy::class,
            // Policy dédiée imports (FUEL-018).
            FuelImport::class => FuelImportPolicy::class,
        ];

        foreach ($expected as $model => $policy) {
            self::assertSame(
                $policy,
                Gate::getPolicyFor($model)::class,
                sprintf('%s doit être inscrit UNE SEULE fois, sur %s.', $model, $policy),
            );
        }
    }

    public function test_manager_can_view_a_site_without_type_error(): void
    {
        /** @var Company $company */
        $company = Company::factory()->withFeature('fuel_station')->create([
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'country' => 'DZ',
            'currency' => 'DZD',
            'timezone' => 'Africa/Algiers',
        ]);

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        DB::statement('SET search_path TO shared_tenants,public');

        $stationId = DB::table('fuel_stations')->insertGetId([
            'company_id' => $company->id,
            'code' => 'ST-8177',
            'name' => 'Station Garde-fou',
            'timezone' => 'Africa/Algiers',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $siteId = DB::table('fuel_sites')->insertGetId([
            'company_id' => $company->id,
            'station_id' => $stationId,
            'code' => 'SITE-01',
            'name' => 'Site principal',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Avant #8177 : FuelStationPolicy::view(Employee, FuelStation) recevait
        // un FuelSite → TypeError → 500. Attendu désormais : 200.
        $this->actingAs($manager)
            ->getJson('/api/v1/fuel-station/sites/'.$siteId)
            ->assertOk()
            ->assertJsonPath('data.id', $siteId)
            ->assertJsonPath('data.code', 'SITE-01');
    }
}
