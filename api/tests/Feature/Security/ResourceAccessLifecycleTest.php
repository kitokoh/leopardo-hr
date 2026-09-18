<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use App\Modules\HR\Infrastructure\Services\DepartureService;
use App\Modules\HR\Infrastructure\Services\UserInvitationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\AssignsResourceAccess;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #7601 (R4 de l'épique #7597) — cycle de vie des accès ressource :
 *
 *  - invitation PRÉ-ASSIGNÉE : rôle + ressources + niveaux en un geste, les
 *    assignations sont créées à l'ACTIVATION (pas avant) ;
 *  - révocation en CASCADE au départ du collaborateur, audit conservé ;
 *  - vue inverse « qui a accès à CETTE ressource ? » ;
 *  - rapport d'audit des accès (JSON + export CSV).
 */
class ResourceAccessLifecycleTest extends TestCase
{
    use AssignsResourceAccess;
    use CreatesMvpSchema;

    private Company $company;

    private Employee $principal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $migration = require database_path('migrations/tenant/2026_09_17_000001_7598_create_employee_resource_assignments_table.php');
        $migration->up();

        // Le départ (offboarding) vit dans `employee_departures`, hors schéma
        // MVP : même convention que ci-dessus, la vraie migration est jouée.
        $departures = require database_path('migrations/tenant/2026_08_23_000007_create_employee_departures_table.php');
        $departures->up();

        $this->company = Company::query()->create([
            'name' => 'Restaurant Exemple',
            'slug' => 'restaurant-exemple-r4',
            'sector' => 'restaurant',
            'country' => 'SN',
            'city' => 'Dakar',
            'email' => 'contact@exemple-r4.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $this->principal = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function makeCamera(string $name): int
    {
        return (int) DB::table('cameras')->insertGetId([
            'company_id' => $this->company->id,
            'name' => $name,
            'rtsp_url' => 'rtsp://camera.test/'.$name,
            'created_by' => $this->principal->id,
        ]);
    }

    public function test_pre_assigned_invitation_creates_assignments_at_activation_only(): void
    {
        Mail::fake();

        $cameraA = $this->makeCamera('Almadies');
        $cameraB = $this->makeCamera('Plateau');

        /** @var Employee $invitee */
        $invitee = Employee::factory()->create(['company_id' => $this->company->id]);

        /** @var UserInvitationService $service */
        $service = app(UserInvitationService::class);

        $token = $service->createAndSend(
            company: $this->company,
            employee: $invitee,
            invitedByType: 'manager',
            invitedByEmail: $this->principal->email,
            resourceAssignments: [
                ['resource_type' => 'camera', 'resource_id' => $cameraA, 'access_level' => 'manage'],
                ['resource_type' => 'camera', 'resource_id' => $cameraB, 'access_level' => 'view'],
            ],
        );

        // Avant activation : AUCUNE assignation (l'invité n'a pas de session).
        $this->assertSame(0, $invitee->resourceAssignments()->count());

        $service->accept($token, 'S3cret!Passw0rd');

        $levels = $invitee->resourceAssignments()
            ->orderBy('resource_id')
            ->pluck('access_level', 'resource_id')
            ->all();

        $this->assertSame(['manage', 'view'], array_values(array_map(
            static fn (int $id): string => (string) $levels[$id],
            [$cameraA, $cameraB],
        )));
    }

    public function test_departure_revokes_assignments_in_cascade_and_keeps_audit(): void
    {
        $camera = $this->makeCamera('Almadies');

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->assignResourceAccess($employee, 'camera', $camera, 'manage', $this->principal);

        /** @var DepartureService $service */
        $service = app(DepartureService::class);
        $service->registerDeparture($this->principal, $employee, [
            'departure_type' => 'resignation',
            'last_work_day' => now()->toDateString(),
        ]);

        // Tout est révoqué…
        $this->assertSame(0, $employee->resourceAssignments()->count());

        // …et l'audit de la révocation reste lisible.
        $this->assertTrue(
            AuditLog::query()
                ->where('company_id', $this->company->id)
                ->where('auditable_type', (new EmployeeResourceAssignment)->getMorphClass())
                ->where('action', 'deleted')
                ->exists(),
            'La révocation en cascade doit laisser une ligne d\'audit.'
        );
    }

    public function test_reverse_view_lists_who_has_access_to_a_resource(): void
    {
        $camera = $this->makeCamera('Almadies');

        /** @var Employee $collaborator */
        $collaborator = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->assignResourceAccess($collaborator, 'camera', $camera, 'operate', $this->principal);

        Sanctum::actingAs($this->principal);

        $this->getJson("/api/v1/resources/camera/{$camera}/access")
            ->assertStatus(200)
            ->assertJsonPath('data.0.employee_id', $collaborator->id)
            ->assertJsonPath('data.0.access_level', 'operate')
            ->assertJsonPath('data.0.granted_by', $this->principal->id);

        // Réservé au principal.
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        Sanctum::actingAs($employee);
        $this->getJson("/api/v1/resources/camera/{$camera}/access")->assertStatus(403);
    }

    public function test_access_audit_report_is_readable_and_exportable(): void
    {
        $camera = $this->makeCamera('Almadies');

        /** @var Employee $collaborator */
        $collaborator = Employee::factory()->create(['company_id' => $this->company->id]);

        Sanctum::actingAs($this->principal);

        // La pose passe par l'API R1 pour générer l'audit « created ».
        $this->putJson("/api/v1/employees/{$collaborator->id}/resource-assignments", [
            'assignments' => [
                ['resource_type' => 'camera', 'resource_id' => $camera, 'access_level' => 'view'],
            ],
        ])->assertStatus(200);

        $this->getJson('/api/v1/resource-access/audit')
            ->assertStatus(200)
            ->assertJsonPath('data.0.action', 'created')
            ->assertJsonPath('data.0.resource_type', 'camera')
            ->assertJsonPath('data.0.access_level', 'view');

        $csv = $this->get('/api/v1/resource-access/audit?format=csv');
        $csv->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('content-type'));

        // Réservé au principal.
        Sanctum::actingAs($collaborator);
        $this->getJson('/api/v1/resource-access/audit')->assertStatus(403);
    }
}
