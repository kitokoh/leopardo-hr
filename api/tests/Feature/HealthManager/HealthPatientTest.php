<?php

declare(strict_types=1);

namespace Tests\Feature\HealthManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HealthManager\Domain\Models\HealthPatient;
use App\Modules\HealthManager\Domain\Models\HealthPractitioner;
use App\Modules\HealthManager\Domain\Models\HealthStaffRole;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * API du registre patients — HC-003 (#7787, BC-30).
 *
 * Matrice : 401 non authentifié / 403 solution inactive / 403 employé
 * lambda / CRUD réception / MRN serveur (format + séquence + unicité) /
 * praticien lecture seule / 404 cross-tenant / archivage logique.
 */
class HealthPatientTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $receptionA;

    private Employee $lambdaA;

    private function baseUrl(): string
    {
        return '/api/v1/health-manager';
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

        /** @var Employee $receptionA */
        $receptionA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->receptionA = $receptionA;
        HealthStaffRole::query()->create([
            'company_id' => $companyA->id,
            'employee_id' => (int) $receptionA->getAttribute('id'),
            'role' => HealthStaffRole::ROLE_RECEPTION,
        ]);

        /** @var Employee $lambdaA */
        $lambdaA = Employee::factory()->create(['company_id' => $companyA->id]);
        $this->lambdaA = $lambdaA;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePatient(Company $company, array $overrides = []): HealthPatient
    {
        /** @var HealthPatient $patient */
        $patient = HealthPatient::query()->create(array_merge([
            'company_id' => $company->id,
            'mrn' => 'PAT-'.now()->format('Y').'-'.str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT),
            'full_name' => 'Patient Test',
            'sex' => HealthPatient::SEX_FEMALE,
            'status' => HealthPatient::STATUS_ACTIVE,
        ], $overrides));

        return $patient;
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson($this->baseUrl().'/patients')->assertStatus(401);
        $this->postJson($this->baseUrl().'/patients', [])->assertStatus(401);
    }

    public function test_inactive_solution_gets_403(): void
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

        $this->getJson($this->baseUrl().'/patients')
            ->assertStatus(403)
            ->assertJsonPath('error', 'HEALTH_SOLUTION_INACTIVE');
    }

    public function test_plain_employee_gets_403(): void
    {
        Sanctum::actingAs($this->lambdaA);

        $this->getJson($this->baseUrl().'/patients')->assertStatus(403);
        $this->postJson($this->baseUrl().'/patients', [
            'full_name' => 'Jane Doe',
            'sex' => 'female',
        ])->assertStatus(403);
    }

    public function test_reception_full_crud_and_search(): void
    {
        Sanctum::actingAs($this->receptionA);
        $url = $this->baseUrl();
        $year = now()->format('Y');

        // Création — MRN généré serveur (jamais accepté du client).
        $created = $this->postJson($url.'/patients', [
            'full_name' => 'Amina Benali',
            'sex' => 'female',
            'birth_date' => '1990-05-12',
            'blood_group' => 'O+',
            'phone' => '+213661234567',
            'email' => 'amina@example.test',
            'address' => '12 rue des Oliviers, Alger',
            'emergency_contact_name' => 'Karim Benali',
            'emergency_contact_phone' => '+213770000000',
            'insurance_provider' => 'CNAS',
            'insurance_number' => 'INS-42',
            'allergies' => 'Pénicilline',
            'medical_history' => 'Asthme léger',
        ])->assertStatus(201);

        $patientId = $created->json('data.id');
        $created->assertJsonPath('data.mrn', 'PAT-'.$year.'-0001')
            ->assertJsonPath('data.full_name', 'Amina Benali')
            ->assertJsonPath('data.phone', '+213661234567')
            ->assertJsonPath('data.status', 'active');

        // PII chiffrée AU REPOS : la colonne ne contient jamais le clair.
        /** @var HealthPatient $stored */
        $stored = HealthPatient::query()->findOrFail($patientId);
        $this->assertNotSame('+213661234567', $stored->getRawOriginal('phone_encrypted'));
        $this->assertSame('+213661234567', $stored->phone_encrypted);

        // Lecture
        $this->getJson($url.'/patients/'.$patientId)
            ->assertStatus(200)
            ->assertJsonPath('data.allergies', 'Pénicilline');

        // Mise à jour
        $this->putJson($url.'/patients/'.$patientId, [
            'full_name' => 'Amina Benali-Cherif',
            'phone' => '+213662222222',
        ])->assertStatus(200)
            ->assertJsonPath('data.full_name', 'Amina Benali-Cherif')
            ->assertJsonPath('data.phone', '+213662222222')
            ->assertJsonPath('data.mrn', 'PAT-'.$year.'-0001');

        // Recherche : full_name ILIKE + mrn ILIKE (téléphone chiffré au
        // repos, non déterministe → volontairement exclu de la recherche).
        $this->makePatient($this->companyA, ['full_name' => 'Omar Ziani', 'mrn' => 'PAT-'.$year.'-9999']);

        $byName = $this->getJson($url.'/patients?q=benali')->assertStatus(200);
        $this->assertCount(1, (array) $byName->json('data'));
        $byName->assertJsonPath('data.0.full_name', 'Amina Benali-Cherif');

        $byMrn = $this->getJson($url.'/patients?q=PAT-'.$year.'-9999')->assertStatus(200);
        $this->assertCount(1, (array) $byMrn->json('data'));
        $byMrn->assertJsonPath('data.0.full_name', 'Omar Ziani');
    }

    public function test_mrn_is_sequential_per_tenant_and_year(): void
    {
        Sanctum::actingAs($this->receptionA);
        $url = $this->baseUrl();
        $year = now()->format('Y');

        $first = $this->postJson($url.'/patients', ['full_name' => 'Premier Patient', 'sex' => 'male'])
            ->assertStatus(201)->json('data.mrn');
        $second = $this->postJson($url.'/patients', ['full_name' => 'Deuxième Patient', 'sex' => 'other'])
            ->assertStatus(201)->json('data.mrn');

        $this->assertSame('PAT-'.$year.'-0001', $first);
        $this->assertSame('PAT-'.$year.'-0002', $second);
        $this->assertMatchesRegularExpression('/^PAT-\d{4}-\d{4}$/', (string) $second);

        // Unicité par tenant (contrainte + séquence).
        $this->assertSame(
            2,
            HealthPatient::query()->where('company_id', $this->companyA->id)->distinct('mrn')->count('mrn')
        );

        // Séquence PAR TENANT : le tenant B repart à 0001.
        /** @var Employee $receptionB */
        $receptionB = Employee::factory()->create(['company_id' => $this->companyB->id]);
        HealthStaffRole::query()->create([
            'company_id' => $this->companyB->id,
            'employee_id' => (int) $receptionB->getAttribute('id'),
            'role' => HealthStaffRole::ROLE_RECEPTION,
        ]);
        Sanctum::actingAs($receptionB);

        $this->postJson($url.'/patients', ['full_name' => 'Patient B', 'sex' => 'female'])
            ->assertStatus(201)
            ->assertJsonPath('data.mrn', 'PAT-'.$year.'-0001');
    }

    public function test_practitioner_is_read_only(): void
    {
        /** @var Employee $practitionerEmployee */
        $practitionerEmployee = Employee::factory()->create(['company_id' => $this->companyA->id]);
        HealthPractitioner::query()->create([
            'company_id' => $this->companyA->id,
            'employee_id' => (int) $practitionerEmployee->getAttribute('id'),
            'title' => HealthPractitioner::TITLE_DR,
            'status' => HealthPractitioner::STATUS_ACTIVE,
        ]);

        $patient = $this->makePatient($this->companyA);
        Sanctum::actingAs($practitionerEmployee);
        $url = $this->baseUrl();

        // Lecture : OK (liste + fiche).
        $this->getJson($url.'/patients')->assertStatus(200);
        $this->getJson($url.'/patients/'.$patient->getAttribute('id'))->assertStatus(200);

        // Écriture : 403 (création, mise à jour, archivage).
        $this->postJson($url.'/patients', ['full_name' => 'Refusé', 'sex' => 'male'])->assertStatus(403);
        $this->putJson($url.'/patients/'.$patient->getAttribute('id'), ['full_name' => 'Refusé'])->assertStatus(403);
        $this->postJson($url.'/patients/'.$patient->getAttribute('id').'/archive')->assertStatus(403);
    }

    public function test_cross_tenant_patient_is_404(): void
    {
        $foreign = $this->makePatient($this->companyB);
        Sanctum::actingAs($this->receptionA);
        $url = $this->baseUrl();

        $this->getJson($url.'/patients/'.$foreign->getAttribute('id'))->assertStatus(404);
        $this->putJson($url.'/patients/'.$foreign->getAttribute('id'), ['full_name' => 'X'])->assertStatus(404);
        $this->postJson($url.'/patients/'.$foreign->getAttribute('id').'/archive')->assertStatus(404);

        // Et le patient étranger n'apparaît jamais dans la liste du tenant A.
        $list = $this->getJson($url.'/patients')->assertStatus(200);
        $this->assertSame(0, $list->json('meta.total'));
    }

    public function test_archive_sets_status_and_never_deletes(): void
    {
        Sanctum::actingAs($this->receptionA);
        $patient = $this->makePatient($this->companyA);
        $patientId = (int) $patient->getAttribute('id');

        $this->postJson($this->baseUrl().'/patients/'.$patientId.'/archive')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'archived');

        // Jamais de suppression physique — la ligne existe toujours.
        $this->assertDatabaseHas('health_patients', [
            'id' => $patientId,
            'status' => HealthPatient::STATUS_ARCHIVED,
        ]);
    }

    public function test_validation_errors_are_422(): void
    {
        Sanctum::actingAs($this->receptionA);
        $url = $this->baseUrl();

        $this->postJson($url.'/patients', [])->assertStatus(422); // full_name + sex requis
        $this->postJson($url.'/patients', ['full_name' => 'X', 'sex' => 'invalid'])->assertStatus(422);
        $this->postJson($url.'/patients', ['full_name' => 'X', 'sex' => 'male', 'blood_group' => 'Z+'])->assertStatus(422);
    }
}
