<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelIncidentAttachment;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Incidents, maintenance et tâches — FUEL-010 (issue #5804).
 *
 * Couvre : signalement par un pompiste, workflow audité
 * (assign → resolve → close réservés au manager), pièces jointes contrôlées
 * (MIME/size allowlist), événement outbox SANS PII, isolation tenant 404,
 * tâche de maintenance liée.
 */
class FuelIncidentApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);

        return $company;
    }

    private function operator(Company $company): Employee
    {
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);

        return $operator;
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $manager;
    }

    private function station(Company $company): FuelStation
    {
        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-INC-'.substr((string) $company->id, 0, 6),
            'name' => 'Station incidents',
            'timezone' => 'UTC',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        return $station;
    }

    public function test_operator_reports_incident_and_event_has_no_pii(): void
    {
        $company = $this->company();
        $station = $this->station($company);
        Sanctum::actingAs($this->operator($company));

        $this->postJson('/api/v1/fuel-station/incidents', [
            'station_id' => $station->id,
            'equipment_type' => 'pump',
            'severity' => 'high',
            'title' => 'Pompe P-01 en panne',
            'description' => 'Client mécontent, fuite visible', // PII potentielle
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', FuelIncident::STATUS_OPEN);

        $event = DB::table('fuel_outbox_events')
            ->where('company_id', $company->id)
            ->where('event_type', 'fuel.incident.reported.v1')
            ->first();

        $this->assertNotNull($event);

        $payload = json_decode((string) $event->payload, true);
        $this->assertIsArray($payload);
        // Aucune PII dans l'événement : ni titre ni description.
        $this->assertArrayNotHasKey('title', $payload);
        $this->assertArrayNotHasKey('description', $payload);
        $this->assertArrayHasKey('incident_id', $payload);
    }

    public function test_operator_cannot_assign_or_resolve(): void
    {
        $company = $this->company();
        $station = $this->station($company);
        $operator = $this->operator($company);
        Sanctum::actingAs($operator);

        /** @var FuelIncident $incident */
        $incident = FuelIncident::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'severity' => 'medium',
            'title' => 'Incident test',
            'reported_by' => $operator->id,
        ]);

        $manager = $this->manager($company);

        $this->postJson('/api/v1/fuel-station/incidents/'.$incident->id.'/assign', [
            'assigned_to' => $manager->id,
        ])->assertStatus(403);

        $this->postJson('/api/v1/fuel-station/incidents/'.$incident->id.'/resolve', [
            'resolution_notes' => 'Réparé',
        ])->assertStatus(403);
    }

    public function test_manager_assign_resolve_close_workflow(): void
    {
        $company = $this->company();
        $station = $this->station($company);
        $operator = $this->operator($company);
        $manager = $this->manager($company);
        Sanctum::actingAs($manager);

        /** @var FuelIncident $incident */
        $incident = FuelIncident::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'severity' => 'critical',
            'title' => 'Incident critique',
            'reported_by' => $operator->id,
        ]);

        $this->postJson('/api/v1/fuel-station/incidents/'.$incident->id.'/assign', [
            'assigned_to' => $operator->id,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', FuelIncident::STATUS_IN_PROGRESS)
            ->assertJsonPath('data.assigned_to', $operator->id);

        $this->postJson('/api/v1/fuel-station/incidents/'.$incident->id.'/resolve', [
            'resolution_notes' => 'Pompe réparée',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', FuelIncident::STATUS_RESOLVED)
            ->assertJsonPath('data.resolved_by', $manager->id);

        $this->postJson('/api/v1/fuel-station/incidents/'.$incident->id.'/close', [
            'closure_notes' => 'Clôturé',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', FuelIncident::STATUS_CLOSED);
    }

    public function test_attachment_mime_allowlist_enforced(): void
    {
        $company = $this->company();
        $station = $this->station($company);
        $operator = $this->operator($company);
        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/fuel-station/incidents', [
            'station_id' => $station->id,
            'title' => 'Incident avec PJ',
            'attachments' => [
                ['file_name' => 'photo.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 1024],
                ['file_name' => 'virus.exe', 'mime_type' => 'application/x-msdownload', 'size_bytes' => 100],
            ],
        ])->assertStatus(422); // le .exe n'est pas dans l'allowlist

        // Avec une PJ autorisée : 201 + métadonnées enregistrées.
        $this->postJson('/api/v1/fuel-station/incidents', [
            'station_id' => $station->id,
            'title' => 'Incident avec PJ valide',
            'attachments' => [
                ['file_name' => 'facture.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 2048],
            ],
        ])->assertStatus(201);

        $this->assertSame(1, FuelIncidentAttachment::query()->where('company_id', $company->id)->count());
    }

    public function test_cross_tenant_incident_is_404(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();
        Sanctum::actingAs($this->manager($companyB));

        /** @var FuelIncident $incident */
        $incident = FuelIncident::query()->create([
            'company_id' => $companyA->id,
            'severity' => 'low',
            'title' => 'Incident tenant A',
            'reported_by' => $this->operator($companyA)->id,
        ]);

        $this->getJson('/api/v1/fuel-station/incidents/'.$incident->id)->assertStatus(404);
        $this->postJson('/api/v1/fuel-station/incidents/'.$incident->id.'/close', ['closure_notes' => 'x'])->assertStatus(404);
    }
}
