<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Jobs\DispatchWebhook;
use App\Modules\Billing\Domain\Models\WebhookEndpoint;
use App\Modules\Fleet\Domain\Models\Vehicle;
use App\Modules\HR\Domain\Models\TrainingCourse;
use App\Modules\HR\Domain\Models\TrainingEnrollment;
use App\Modules\HR\Domain\Models\TrainingSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Vague QA Hardening 2026-08-14 (issues #2175/#2176/#2182/#2183/#2184).
 *
 * Endpoints ajoutés pour brancher les frontends mobiles et le cockpit admin
 * sur des données réelles :
 *   - GET /me/training-enrollments (alias mobile, shape enrichie)
 *   - GET /me/vehicles (véhicules assignés à l'employé courant)
 *   - GET /training/sessions + GET /training/enrollments (cockpit tenant)
 *   - POST /webhooks/{webhookEndpoint}/test (bouton « Tester »)
 *   - GET /admin/users (liste réelle des utilisateurs plateforme)
 */
class QaHardeningEndpointsTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /** @return array{Company, Employee} */
    private function tenantFixture(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return [$company, $manager];
    }

    private function createTrainingEnrollment(Employee $employee, string $status = 'enrolled'): TrainingEnrollment
    {
        $course = TrainingCourse::query()->create([
            'company_id' => $employee->company_id,
            'title' => 'Secourisme au travail',
            'category' => 'securite',
            'type' => 'presentiel',
            'duration_hours' => 8,
        ]);

        $session = TrainingSession::query()->create([
            'training_course_id' => $course->id,
            'company_id' => $employee->company_id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'status' => 'scheduled',
        ]);

        return TrainingEnrollment::query()->create([
            'training_session_id' => $session->id,
            'employee_id' => $employee->id,
            'company_id' => $employee->company_id,
            'status' => $status,
            'score' => 42.0,
        ]);
    }

    // ── US1 : /me/training-enrollments ─────────────────────────────────

    public function test_employee_lists_own_training_enrollments_with_mobile_shape(): void
    {
        [$company, $manager] = $this->tenantFixture();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $enrollment = $this->createTrainingEnrollment($employee);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/me/training-enrollments')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'course_title', 'session_date', 'progress', 'status']]])
            ->assertJsonPath('data.0.id', $enrollment->id)
            ->assertJsonPath('data.0.course_title', 'Secourisme au travail')
            ->assertJsonPath('data.0.progress', 42);
    }

    public function test_training_enrollments_are_tenant_isolated(): void
    {
        [$company, $manager] = $this->tenantFixture();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $this->createTrainingEnrollment($employee);

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create();
        /** @var Employee $otherEmployee */
        $otherEmployee = Employee::factory()->create(['company_id' => $otherCompany->id, 'status' => 'active']);
        $this->createTrainingEnrollment($otherEmployee);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/me/training-enrollments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_id', $employee->id);
    }

    public function test_me_trainings_alias_remains_available(): void
    {
        [$company, $manager] = $this->tenantFixture();
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/me/trainings')->assertOk();
    }

    // ── US1 : /me/vehicles ─────────────────────────────────────────────

    public function test_employee_lists_own_vehicles(): void
    {
        [$company, $manager] = $this->tenantFixture();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);

        Vehicle::query()->create([
            'company_id' => $company->id,
            'plate_number' => '1234-TN-567',
            'brand' => 'Toyota',
            'model' => 'Hilux',
            'status' => 'active',
            'assigned_driver_id' => $employee->id,
        ]);
        // Véhicule non assigné à l'employé — ne doit pas apparaître.
        Vehicle::query()->create([
            'company_id' => $company->id,
            'plate_number' => '9999-XX-000',
            'brand' => 'Renault',
            'model' => 'Kangoo',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/me/vehicles')
            ->assertOk()
            ->assertJsonStructure(['data' => [['vehicle_id', 'plate_number', 'brand', 'model', 'latitude', 'longitude', 'speed', 'updated_at']]])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.plate_number', '1234-TN-567')
            ->assertJsonPath('data.0.latitude', null);
    }

    public function test_me_vehicles_returns_empty_list_for_employee_without_vehicle(): void
    {
        [$company, $manager] = $this->tenantFixture();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/me/vehicles')->assertOk()->assertJsonPath('data', []);
    }

    public function test_me_vehicles_are_tenant_isolated(): void
    {
        [$company, $manager] = $this->tenantFixture();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create();
        /** @var Employee $otherEmployee */
        $otherEmployee = Employee::factory()->create(['company_id' => $otherCompany->id, 'status' => 'active']);
        Vehicle::query()->create([
            'company_id' => $otherCompany->id,
            'plate_number' => '1111-AA-222',
            'brand' => 'Mercedes',
            'model' => 'Sprinter',
            'status' => 'active',
            'assigned_driver_id' => $otherEmployee->id,
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/me/vehicles')->assertOk()->assertJsonPath('data', []);
    }

    // ── US2 : /training/sessions + /training/enrollments ───────────────

    public function test_manager_lists_all_tenant_sessions(): void
    {
        [$company, $manager] = $this->tenantFixture();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $this->createTrainingEnrollment($employee);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/training/sessions')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'training_course_id', 'start_date', 'status']]])
            ->assertJsonCount(1, 'data');
    }

    public function test_manager_lists_all_tenant_enrollments(): void
    {
        [$company, $manager] = $this->tenantFixture();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $this->createTrainingEnrollment($employee);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/training/enrollments')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'status', 'course_title']]])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.course_title', 'Secourisme au travail');
    }

    public function test_training_lists_are_tenant_isolated(): void
    {
        [$company, $manager] = $this->tenantFixture();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $this->createTrainingEnrollment($employee);

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create();
        /** @var Employee $otherEmployee */
        $otherEmployee = Employee::factory()->create(['company_id' => $otherCompany->id, 'status' => 'active']);
        $this->createTrainingEnrollment($otherEmployee);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/training/enrollments')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/training/sessions')->assertOk()->assertJsonCount(1, 'data');
    }

    // ── US2 : POST /webhooks/{webhookEndpoint}/test ─────────────────────

    public function test_principal_can_test_a_webhook_endpoint(): void
    {
        // Issue #2548/#2572 : le bouton « Tester » envoie un payload SYNCHRONE
        // (Http::fake) et trace une webhook_deliveries — plus un dispatch async.
        [$company, $manager] = $this->tenantFixture();

        Http::fake([
            'example.com/*' => Http::response('{"ok":true}', 200, ['Content-Type' => 'application/json']),
        ]);

        $endpoint = WebhookEndpoint::query()->create([
            'company_id' => $company->id,
            'name' => 'ERP',
            // #4549 : la garde anti-SSRF (NotPrivateUrl::isPublicHost) rejette les
            // TLD réservés non résolvables (.example) en fail-closed — le test
            // utilise un hôte public résolvable comme les autres tests webhook.
            'url' => 'https://example.com/hook',
            'secret' => 'test-secret',
            'events' => ['employee.created'],
            'active' => true,
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/webhooks/{$endpoint->id}/test")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('http_status', 200)
            ->assertJsonStructure(['message', 'status', 'http_status', 'duration_ms', 'delivery']);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'example.com/hook')
                && $request->hasHeader('X-Leopardo-Event', 'test')
                && $request->hasHeader('Webhook-Signature');
        });

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'test',
            'response_code' => 200,
        ]);
    }

    public function test_webhook_test_is_tenant_isolated(): void
    {
        [$company, $manager] = $this->tenantFixture();
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create();
        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->create(['company_id' => $otherCompany->id, 'status' => 'active', 'manager_role' => 'principal']);

        $endpoint = WebhookEndpoint::query()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Autre ERP',
            'url' => 'https://other.example/hook',
            'secret' => 'other-secret',
            'events' => [],
            'active' => true,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/webhooks/{$endpoint->id}/test")->assertNotFound();
    }

    // ── US3 : GET /admin/users ──────────────────────────────────────────

    public function test_super_admin_lists_real_platform_users(): void
    {
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();
        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        /** @var Company $company */
        $company = Company::factory()->create(['name' => 'TECHCORP ALGERIE']);

        // Le contrôleur lit le schéma PUBLIC (public.users + user_employee_links,
        // cf. PlatformUsersApiTest) : un simple Employee (schéma tenant) ne suffit
        // pas — il faut une vraie ligne users + le lien employé.
        $userId = DB::table('users')->insertGetId([
            'first_name' => 'Amina',
            'last_name' => 'Benali',
            'email' => 'amina@techcorp.example',
            'password_hash' => Hash::make('password123'),
            'provider' => 'local',
            'preferred_language' => 'fr',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Amina',
            'last_name' => 'Benali',
            'email' => 'amina@techcorp.example',
            'status' => 'active',
        ]);

        DB::table('user_employee_links')->insert([
            'user_id' => $userId,
            'employee_id' => $employee->id,
            'company_id' => $company->id,
            'status' => 'active',
            'linked_at' => now(),
            'created_at' => now(),
        ]);

        $this->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonPath('data.0.email', 'amina@techcorp.example')
            ->assertJsonPath('data.0.company.name', 'TECHCORP ALGERIE')
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'status', 'is_active', 'company']]]);
    }

    public function test_admin_users_requires_super_admin_auth(): void
    {
        [$company, $manager] = $this->tenantFixture();
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/admin/users')->assertUnauthorized();
    }
}
