<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FarmOs;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Enums\FarmEquipmentKind;
use App\Modules\FarmOs\Domain\Enums\FarmEquipmentMeterType;
use App\Modules\FarmOs\Domain\Enums\FarmEquipmentStatus;
use App\Modules\FarmOs\Domain\Enums\FarmMaintenanceStatus;
use App\Modules\FarmOs\Domain\Enums\FarmMaintenanceType;
use App\Modules\FarmOs\Domain\Enums\FarmMeterSource;
use App\Modules\FarmOs\Domain\Exceptions\FarmEquipmentException;
use App\Modules\FarmOs\Domain\Models\FarmCapabilitySetting;
use App\Modules\FarmOs\Domain\Models\FarmEquipment;
use App\Modules\FarmOs\Domain\Models\FarmEquipmentBreakdown;
use App\Modules\FarmOs\Domain\Models\FarmEquipmentMaintenance;
use App\Modules\FarmOs\Domain\Services\FarmEquipmentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Farm OS — T2.2 : comportement REEL du registre de materiel agricole.
 *
 * Ces tests exercent le service metier sur un schema tenant (verrouillage,
 * refus de compteur qui recule, idempotence, isolation multi-tenant). Ils
 * exigent PostgreSQL et ne tournent donc qu'en CI.
 *
 * Exigence d'honnetete verifiee ici : AUCUNE valeur de compteur n'est presentee
 * comme automatiquement mesuree — `meter_source` ne peut valoir que `manual` ou
 * `imported`, et un materiel sans compteur ne stocke aucune valeur.
 */
final class FarmEquipmentTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        app()->instance('current_company', $this->company);
    }

    public function test_equipment_is_created_with_manual_meter_only(): void
    {
        $equipment = $this->makeEquipment([
            'kind' => FarmEquipmentKind::Tractor->value,
            'meter_type' => FarmEquipmentMeterType::Hours->value,
            'meter_value' => '120.00',
        ]);

        $this->assertSame(FarmEquipmentMeterType::Hours->value, $equipment->meter_type);
        $this->assertSame('120.00', $equipment->meter_value);
        // La valeur vient d'une SAISIE : jamais d'une telemetrie.
        $this->assertSame(FarmMeterSource::Manual->value, $equipment->meter_source);
        $this->assertTrue($equipment->tracksMeter());
    }

    public function test_equipment_without_meter_stores_no_value(): void
    {
        // Un outil sans compteur ne doit PAS porter un zero trompeur.
        $equipment = $this->makeEquipment([
            'kind' => FarmEquipmentKind::Tool->value,
            'meter_type' => FarmEquipmentMeterType::None->value,
            'meter_value' => null,
        ]);

        $this->assertNull($equipment->meter_value);
        $this->assertNull($equipment->meter_source);
        $this->assertFalse($equipment->tracksMeter());
    }

    public function test_recording_usage_updates_meter_and_writes_a_journal_line(): void
    {
        $equipment = $this->makeEquipment([
            'meter_type' => FarmEquipmentMeterType::Hours->value,
            'meter_value' => '100.00',
        ]);

        $log = app(FarmEquipmentService::class)->recordUsage(
            $this->company,
            $equipment,
            '150.00',
            FarmMeterSource::Manual,
        );

        $this->assertSame('150.00', $log->meter_value);
        // Premier ecart calcule depuis le compteur initial connu.
        $this->assertSame('50.00', $log->meter_delta);
        $this->assertSame('150.00', $equipment->refresh()->meter_value);
        $this->assertSame(1, DB::table('farm_equipment_usage_logs')->where('company_id', $this->company->id)->count());
    }

    public function test_first_usage_log_has_no_delta_when_meter_was_unknown(): void
    {
        // Compteur inconnu au depart : on ne suppose PAS un compteur a zero.
        $equipment = $this->makeEquipment([
            'meter_type' => FarmEquipmentMeterType::Hours->value,
            'meter_value' => null,
        ]);

        $log = app(FarmEquipmentService::class)->recordUsage($this->company, $equipment, '42.00');

        $this->assertNull($log->meter_delta);
        $this->assertSame('42.00', $log->meter_value);
    }

    public function test_meter_cannot_go_backwards(): void
    {
        $equipment = $this->makeEquipment([
            'meter_type' => FarmEquipmentMeterType::Hours->value,
            'meter_value' => '200.00',
        ]);

        $this->expectException(FarmEquipmentException::class);

        app(FarmEquipmentService::class)->recordUsage($this->company, $equipment, '150.00');
    }

    public function test_meter_reset_is_the_only_way_to_lower_the_counter(): void
    {
        $equipment = $this->makeEquipment([
            'meter_type' => FarmEquipmentMeterType::Hours->value,
            'meter_value' => '200.00',
        ]);

        $log = app(FarmEquipmentService::class)->resetMeter(
            $this->company,
            $equipment,
            '0.00',
            'replacement of the counter',
        );

        $this->assertSame('0.00', $log->meter_value);
        // Un remplacement de compteur n'est PAS un ecart d'utilisation.
        $this->assertNull($log->meter_delta);
        $this->assertSame('0.00', $equipment->refresh()->meter_value);
    }

    public function test_equipment_without_meter_refuses_any_reading(): void
    {
        $equipment = $this->makeEquipment([
            'kind' => FarmEquipmentKind::Tool->value,
            'meter_type' => FarmEquipmentMeterType::None->value,
        ]);

        $this->expectException(FarmEquipmentException::class);

        app(FarmEquipmentService::class)->recordUsage($this->company, $equipment, '10.00');
    }

    public function test_retired_equipment_refuses_new_usage(): void
    {
        $equipment = $this->makeEquipment([
            'meter_type' => FarmEquipmentMeterType::Hours->value,
            'meter_value' => '10.00',
            'status' => FarmEquipmentStatus::Retired->value,
        ]);

        $this->expectException(FarmEquipmentException::class);

        app(FarmEquipmentService::class)->recordUsage($this->company, $equipment, '20.00');
    }

    public function test_usage_recording_is_idempotent_on_client_id(): void
    {
        $equipment = $this->makeEquipment([
            'meter_type' => FarmEquipmentMeterType::Hours->value,
            'meter_value' => '10.00',
        ]);

        $service = app(FarmEquipmentService::class);

        $first = $service->recordUsage($this->company, $equipment, '20.00', null, null, null, null, null, null, 'offline-usage-1');
        $second = $service->recordUsage($this->company, $equipment, '20.00', null, null, null, null, null, null, 'offline-usage-1');

        // Rejeu hors ligne : meme ligne, aucune ecriture supplementaire.
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, DB::table('farm_equipment_usage_logs')->where('company_id', $this->company->id)->count());
    }

    public function test_maintenance_can_be_scheduled_then_completed_once(): void
    {
        $equipment = $this->makeEquipment();

        $maintenance = new FarmEquipmentMaintenance([
            'farm_equipment_id' => $equipment->id,
            'maintenance_type' => FarmMaintenanceType::Preventive->value,
            'title' => 'oil change',
            'planned_at' => Carbon::now()->addDays(3),
        ]);
        $maintenance->status = FarmMaintenanceStatus::Planned->value;
        $maintenance->company_id = $this->company->id;
        $maintenance->save();

        $service = app(FarmEquipmentService::class);
        $done = $service->completeMaintenance($this->company, $maintenance, '250.00', 'EUR');

        $this->assertSame(FarmMaintenanceStatus::Done->value, $done->status);
        $this->assertSame('250.00', $done->cost_amount);
        $this->assertNotNull($done->performed_at);
        $this->assertFalse($done->isOpen());

        // Un entretien clos n'est JAMAIS rouvert.
        $this->expectException(FarmEquipmentException::class);
        $service->completeMaintenance($this->company, $done, '300.00');
    }

    public function test_breakdown_resolution_is_idempotent(): void
    {
        $equipment = $this->makeEquipment();

        $breakdown = new FarmEquipmentBreakdown([
            'farm_equipment_id' => $equipment->id,
            'severity' => 'major',
            'title' => 'hydraulic leak',
            'occurred_at' => Carbon::now()->subDay(),
        ]);
        $breakdown->company_id = $this->company->id;
        $breakdown->save();

        $service = app(FarmEquipmentService::class);
        $resolved = $service->resolveBreakdown($this->company, $breakdown, '480.00', 'EUR', '6.00');
        $firstResolvedAt = $resolved->resolved_at;

        $again = $service->resolveBreakdown($this->company, $resolved, '999.00');

        // La date de resolution n'est jamais reecrite, et le cout n'est pas
        // ecrase par un second appel.
        $this->assertSame($firstResolvedAt?->toIso8601String(), $again->resolved_at?->toIso8601String());
        $this->assertSame('480.00', $again->cost_amount);
    }

    public function test_known_costs_separate_known_from_missing(): void
    {
        $equipment = $this->makeEquipment();

        $withCost = new FarmEquipmentMaintenance([
            'farm_equipment_id' => $equipment->id,
            'maintenance_type' => FarmMaintenanceType::Preventive->value,
            'title' => 'service with invoice',
            'cost_amount' => '100.00',
        ]);
        $withCost->status = FarmMaintenanceStatus::Done->value;
        $withCost->company_id = $this->company->id;
        $withCost->save();

        $withoutCost = new FarmEquipmentMaintenance([
            'farm_equipment_id' => $equipment->id,
            'maintenance_type' => FarmMaintenanceType::Corrective->value,
            'title' => 'service without invoice',
        ]);
        $withoutCost->status = FarmMaintenanceStatus::Done->value;
        $withoutCost->company_id = $this->company->id;
        $withoutCost->save();

        $costs = app(FarmEquipmentService::class)->knownCosts($this->company, $equipment);

        $this->assertSame('100.00', $costs['maintenance_known']);
        $this->assertSame(1, $costs['maintenance_unknown_count']);
        // Un total partiel n'est JAMAIS presente comme un cout complet.
        $this->assertFalse($costs['is_complete']);
    }

    public function test_maintenance_due_lists_overdue_and_unresolved_only(): void
    {
        $equipment = $this->makeEquipment();

        $overdue = new FarmEquipmentMaintenance([
            'farm_equipment_id' => $equipment->id,
            'maintenance_type' => FarmMaintenanceType::Preventive->value,
            'title' => 'overdue service',
            'planned_at' => Carbon::now()->subDays(5),
        ]);
        $overdue->status = FarmMaintenanceStatus::Planned->value;
        $overdue->company_id = $this->company->id;
        $overdue->save();

        $future = new FarmEquipmentMaintenance([
            'farm_equipment_id' => $equipment->id,
            'maintenance_type' => FarmMaintenanceType::Preventive->value,
            'title' => 'future service',
            'planned_at' => Carbon::now()->addDays(5),
        ]);
        $future->status = FarmMaintenanceStatus::Planned->value;
        $future->company_id = $this->company->id;
        $future->save();

        $breakdown = new FarmEquipmentBreakdown([
            'farm_equipment_id' => $equipment->id,
            'severity' => 'critical',
            'title' => 'engine failure',
            'occurred_at' => Carbon::now()->subHours(4),
        ]);
        $breakdown->company_id = $this->company->id;
        $breakdown->save();

        $rows = app(FarmEquipmentService::class)->maintenanceDue($this->company);
        $reasons = array_column($rows, 'reason');

        $this->assertContains('overdue_maintenance', $reasons);
        $this->assertContains('unresolved_breakdown', $reasons);
        // L'entretien a venir n'est pas « a prevoir » : aucune prediction.
        $this->assertNotContains('future service', array_column($rows, 'title'));
    }

    public function test_equipment_of_another_tenant_is_refused(): void
    {
        $other = Company::factory()->create();

        // BelongsToCompany ECASE `company_id` avec le tenant actif (#7646) : un
        // materiel ne peut pas naitre chez un autre tenant via Eloquent.
        // Insertion SQL brute + relecture cross-tenant pour eprouver le vrai
        // controle d'appartenance du service.
        DB::table('farm_equipment')->insert([
            'id' => 900004,
            'company_id' => $other->id,
            'name' => 'foreign tractor',
            'kind' => FarmEquipmentKind::Tractor->value,
            'meter_type' => FarmEquipmentMeterType::Hours->value,
            'meter_value' => '10.00',
            'meter_source' => FarmMeterSource::Manual->value,
            'status' => FarmEquipmentStatus::Active->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var FarmEquipment $foreign */
        $foreign = FarmEquipment::crossTenantForPlatformAdmin()->findOrFail(900004);

        $this->assertSame($other->id, $foreign->company_id);

        $this->expectException(FarmEquipmentException::class);

        app(FarmEquipmentService::class)->recordUsage($this->company, $foreign, '20.00');
    }

    public function test_equipment_data_is_isolated_between_tenants(): void
    {
        $other = Company::factory()->create();

        $this->makeEquipment(['name' => 'mine']);

        $this->assertSame(1, DB::table('farm_equipment')->where('company_id', $this->company->id)->count());
        $this->assertSame(0, DB::table('farm_equipment')->where('company_id', $other->id)->count());
    }

    public function test_equipment_capability_is_required(): void
    {
        // Fail-closed : sans decision explicite, seules les capacites « coeur »
        // sont ouvertes — `equipment` n'en fait pas partie.
        FarmCapabilitySetting::query()->create([
            'company_id' => $this->company->id,
            'capability' => FarmCapability::ParcelsCrops->value,
            'enabled' => true,
            'source' => 'onboarding',
            'decided_at' => Carbon::now(),
        ]);

        $gate = app(\App\Modules\FarmOs\Domain\Services\FarmCapabilityGate::class);

        $this->assertFalse($gate->allows($this->company, FarmCapability::Equipment));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeEquipment(array $attributes = []): FarmEquipment
    {
        $equipment = new FarmEquipment(array_merge([
            'name' => 'tractor 1',
            'kind' => FarmEquipmentKind::Tractor->value,
            'meter_type' => FarmEquipmentMeterType::Hours->value,
            'meter_value' => '0.00',
            'status' => FarmEquipmentStatus::Active->value,
        ], $attributes));

        $equipment->company_id = $this->company->id;
        $equipment->save();

        return $equipment;
    }
}
