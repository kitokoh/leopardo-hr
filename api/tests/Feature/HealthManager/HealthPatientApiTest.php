<?php

declare(strict_types=1);

namespace Tests\Feature\HealthManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HealthManager\Domain\Models\HealthPatient;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API registre patients — HC-003 (#7787, BC-30).
 *
 * Couvre : 401, solution inactive 403 (fail-closed), RBAC strict (direction
 * et accueil gèrent, praticien actif lit, employé lambda 403), MRN
 * `PAT-YYYY-NNNN` unique par tenant généré côté serveur (jamais accepté de
 * la requête), recherche nom/MRN/téléphone paginée, isolation cross-tenant
 * 404, ARCHIVAGE au lieu de suppression physique.
 */
class HealthPatientApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $receptionA;

    private Employee $lambdaA;

    private function baseUrl(): string
    {
        return '/api/v1/health-manager/patients';
    }

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => ['healthmanager' => true],
        ]);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create([
            'country' => 'MA',
            'currency' => 'MAD',
            'features' => ['healthmanager' => true],
        ]);
        $this->companyB = $companyB;

        /** @var Employee $principalA */
        $principalA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principalA = $principalA;

        /** @var Employee $receptionA */
        $receptionA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'superviseur',
        ]);
        $this->receptionA = $receptionA;

        /** @var Employee $lambdaA */
        $lambdaA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->lambdaA = $lambdaA;
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson($this->baseUrl())->assertStatus(401);
        $this->postJson($this->baseUrl(), [])->assertStatus(401);
    }

    public function test_inactive_solution_gets_403_fail_closed(): void
    {
        /** @var Company $inactive */
        $inactive = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'features' => []]);
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $inactive->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($manager);

        $this->getJson($this->baseUrl())
            ->assertStatus(403)
            ->assertJsonPath('error', 'HEALTH_SOLUTION_INACTIVE');
    }

    public function test_plain_employee_gets_403_on_everything(): void
    {
        /** @var HealthPatient $patient */
        $patient = $this->makePatient($this->companyA->id, 'PAT-2026-0001', 'Amine', 'Kaci');

        Sanctum::actingAs($this->lambdaA);

        $this->getJson($this->baseUrl())->assertStatus(403);
        $this->postJson($this->baseUrl(), ['first_name' => 'X', 'last_name' => 'Y'])->assertStatus(403);
        $this->getJson($this->baseUrl().'/'.$patient->id)->assertStatus(403);
        $this->putJson($this->baseUrl().'/'.$patient->id, ['first_name' => 'Z'])->assertStatus(403);
        $this->deleteJson($this->baseUrl().'/'.$patient->id)->assertStatus(403);
    }

    public function test_mrn_is_generated_server_side_and_unique_per_tenant(): void
    {
        Sanctum::actingAs($this->principalA);

        $year = now()->format('Y');

        // Le MRN fourni par le client est IGNORÉ (généré côté serveur).
        $first = (string) $this->postJson($this->baseUrl(), [
            'first_name' => 'Amine',
            'last_name' => 'Kaci',
            'mrn' => 'PAT-1999-9999',
        ])->assertStatus(201)->json('data.mrn');
        $this->assertSame("PAT-{$year}-0001", $first);

        $second = (string) $this->postJson($this->baseUrl(), [
            'first_name' => 'Lina',
            'last_name' => 'Benali',
        ])->assertStatus(201)->json('data.mrn');
        $this->assertSame("PAT-{$year}-0002", $second);

        // La séquence est PAR TENANT : le tenant B repart à 0001.
        /** @var Employee $principalB */
        $principalB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        Sanctum::actingAs($principalB);
        $firstB = (string) $this->postJson($this->baseUrl(), [
            'first_name' => 'Sara',
            'last_name' => 'Alami',
        ])->assertStatus(201)->json('data.mrn');
        $this->assertSame("PAT-{$year}-0001", $firstB);
    }

    public function test_reception_manages_and_practitioner_reads_only(): void
    {
        // Accueil : crée et modifie.
        Sanctum::actingAs($this->receptionA);
        $patientId = (int) $this->postJson($this->baseUrl(), [
            'first_name' => 'Yanis',
            'last_name' => 'Meddah',
            'sex' => 'male',
            'blood_group' => 'O+',
            'phone' => '+213555000111',
            'allergies' => 'Pénicilline',
        ])->assertStatus(201)->assertJsonPath('data.blood_group', 'O+')->json('data.id');

        $this->putJson($this->baseUrl().'/'.$patientId, ['status' => 'deceased'])
            ->assertStatus(200)->assertJsonPath('data.status', 'deceased');

        // Praticien actif : lit, ne gère pas.
        /** @var Employee $doctor */
        $doctor = Employee::factory()->create(['company_id' => $this->companyA->id]);
        HealthPractitioner::query()->forceCreate([
            'company_id' => $this->companyA->id,
            'employee_id' => $doctor->id,
            'display_name' => 'Dr K.',
            'status' => HealthPractitioner::STATUS_ACTIVE,
        ]);
        Sanctum::actingAs($doctor);
        $this->getJson($this->baseUrl().'/'.$patientId)
            ->assertStatus(200)->assertJsonPath('data.allergies', 'Pénicilline');
        $this->postJson($this->baseUrl(), ['first_name' => 'X', 'last_name' => 'Y'])->assertStatus(403);
        $this->putJson($this->baseUrl().'/'.$patientId, ['first_name' => 'Z'])->assertStatus(403);
        $this->deleteJson($this->baseUrl().'/'.$patientId)->assertStatus(403);
    }

    public function test_search_by_name_mrn_and_phone_is_paginated(): void
    {
        $this->makePatient($this->companyA->id, 'PAT-2026-0001', 'Amine', 'Kaci', '+213661234567');
        $this->makePatient($this->companyA->id, 'PAT-2026-0002', 'Lina', 'Benali', '+213770000000');
        // Patient d'un AUTRE tenant, homonyme — jamais dans les résultats.
        $this->makePatient($this->companyB->id, 'PAT-2026-0001', 'Amine', 'Kaci', '+213661234567');

        Sanctum::actingAs($this->principalA);

        $this->getJson($this->baseUrl().'?q=kaci')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.last_name', 'Kaci');

        $this->getJson($this->baseUrl().'?q=PAT-2026-0002')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.first_name', 'Lina');

        $this->getJson($this->baseUrl().'?q=661234567')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.mrn', 'PAT-2026-0001');

        $this->getJson($this->baseUrl().'?q=introuvable')
            ->assertStatus(200)->assertJsonPath('meta.total', 0);
    }

    public function test_cross_tenant_patient_returns_404(): void
    {
        /** @var HealthPatient $patientB */
        $patientB = $this->makePatient($this->companyB->id, 'PAT-2026-0001', 'Sara', 'Alami');

        Sanctum::actingAs($this->principalA);

        $this->getJson($this->baseUrl().'/'.$patientB->id)->assertStatus(404);
        $this->putJson($this->baseUrl().'/'.$patientB->id, ['first_name' => 'Piraté'])->assertStatus(404);
        $this->deleteJson($this->baseUrl().'/'.$patientB->id)->assertStatus(404);
    }

    public function test_delete_archives_instead_of_destroying(): void
    {
        /** @var HealthPatient $patient */
        $patient = $this->makePatient($this->companyA->id, 'PAT-2026-0001', 'Amine', 'Kaci');

        Sanctum::actingAs($this->principalA);

        $this->deleteJson($this->baseUrl().'/'.$patient->id)->assertStatus(204);

        // Le dossier RESTE en base : statut archivé + soft delete.
        $table = DB::table('health_patients')->where('id', $patient->id);
        $this->assertTrue($table->exists());
        $this->assertSame('archived', DB::table('health_patients')->where('id', $patient->id)->value('status'));
        $this->assertNotNull(DB::table('health_patients')->where('id', $patient->id)->value('deleted_at'));

        // Hors des listes par défaut, visible avec with_archived.
        $this->getJson($this->baseUrl())->assertStatus(200)->assertJsonPath('meta.total', 0);
        $this->getJson($this->baseUrl().'?with_archived=1')
            ->assertStatus(200)->assertJsonPath('meta.total', 1);
    }

    private function makePatient(
        string $companyId,
        string $mrn,
        string $firstName,
        string $lastName,
        ?string $phone = null,
    ): HealthPatient {
        /** @var HealthPatient $patient */
        $patient = HealthPatient::query()->forceCreate([
            'company_id' => $companyId,
            'mrn' => $mrn,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'status' => HealthPatient::STATUS_ACTIVE,
        ]);

        return $patient;
    }
}
