<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ToolCall;
use App\AI\IntentEngine;
use App\AI\PendingActionStore;
use App\AI\ToolRegistry;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use Database\Seeders\AIToolRegistrySeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * A7 (#7362) / A8 (#7363) — les deux premiers outils d'écriture qui sortent du
 * strict périmètre « congés » : création d'un employé et pointage.
 *
 * Ces tests verrouillent les quatre propriétés qui font qu'un outil d'écriture
 * est sûr dans un assistant conversationnel (voix ou texte) :
 *  1. PROPOSER n'est pas EXÉCUTER — l'outil rend `confirmation_required` et
 *     n'écrit RIEN tant que l'humain n'a pas confirmé ;
 *  2. le rôle créé n'est jamais élevé (pas de création de manager par l'IA) ;
 *  3. un employé non-manager ne peut pas créer d'employé, ni pointer pour un
 *     collègue (parité #6533) ;
 *  4. les règles métier du module propriétaire s'appliquent telles quelles
 *     (unicité de l'e-mail, etc.) — l'assistant ne contourne aucune règle.
 */
class WriteEmployeeAndPunchToolsTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        config(['ai.enabled' => true]);
        $this->seed(AIToolRegistrySeeder::class);
        $this->app->forgetInstance(ToolRegistry::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function fixture(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        // `manager()` pose role=manager + manager_role=principal, la condition
        // exacte de EmployeePolicy::create.
        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);

        // AttendanceService lit currentCompany() : le middleware
        // AITenantInjector la lie en HTTP, on la lie ici de la même façon.
        app()->instance('current_company', $company);

        return [$company, $principal];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function propose(string $tool, array $arguments, Company $company, Employee $actor): array
    {
        $engine = app(IntentEngine::class);

        $results = $engine->executeToolCalls(
            new AIResponse(content: '', toolCalls: [new ToolCall('call_1', $tool, $arguments)]),
            (string) $company->id,
            (int) $actor->id,
        );

        return json_decode($results[0]->content, true) ?: [];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return TestResponse<JsonResponse>
     */
    private function confirm(string $tool, array $arguments, Company $company, Employee $actor): TestResponse
    {
        $pendingId = app(PendingActionStore::class)->store(
            (string) $company->id,
            (int) $actor->id,
            $tool,
            $arguments,
        );

        return $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm");
    }

    public function test_create_employee_requires_confirmation_and_writes_nothing(): void
    {
        [$company, $principal] = $this->fixture();

        $payload = $this->propose('create_employee', [
            'first_name' => 'Amina',
            'last_name' => 'Zerrouki',
            'email' => 'amina@example.test',
        ], $company, $principal);

        $this->assertSame('confirmation_required', $payload['status'] ?? null);
        $this->assertNotEmpty($payload['pending_action_id'] ?? null);
        // Le résumé doit annoncer l'effet externe (e-mail d'invitation).
        $this->assertStringContainsString('invitation', (string) ($payload['summary'] ?? ''));

        $this->assertDatabaseMissing('employees', ['email' => 'amina@example.test']);
    }

    public function test_confirm_creates_the_employee(): void
    {
        [$company, $principal] = $this->fixture();
        Sanctum::actingAs($principal);

        $this->confirm('create_employee', [
            'first_name' => 'Amina',
            'last_name' => 'Zerrouki',
            'email' => 'amina@example.test',
            'job_title' => 'Technicienne',
        ], $company, $principal)
            ->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.tool', 'create_employee');

        $this->assertDatabaseHas('employees', [
            'company_id' => $company->id,
            'email' => 'amina@example.test',
        ]);

        // Le rôle créé n'est JAMAIS élevé par une conversation.
        $created = Employee::query()->where('email', 'amina@example.test')->firstOrFail();
        $this->assertSame('employee', $created->role);
        $this->assertNull($created->manager_role);
        $this->assertSame('Technicienne', $created->extra_data['job_title'] ?? null);
    }

    public function test_create_employee_is_rejected_for_a_non_manager(): void
    {
        [$company, $principal] = $this->fixture();

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($employee);

        $this->confirm('create_employee', [
            'first_name' => 'Amina',
            'last_name' => 'Zerrouki',
            'email' => 'amina@example.test',
        ], $company, $employee)
            ->assertStatus(422)
            ->assertJsonPath('error', 'AI_TOOL_PERMISSION_DENIED');

        $this->assertDatabaseMissing('employees', ['email' => 'amina@example.test']);
    }

    public function test_create_employee_rejects_a_duplicate_email(): void
    {
        [$company, $principal] = $this->fixture();

        Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'amina@example.test',
        ]);

        Sanctum::actingAs($principal);

        $this->confirm('create_employee', [
            'first_name' => 'Amina',
            'last_name' => 'Zerrouki',
            'email' => 'amina@example.test',
        ], $company, $principal)
            ->assertStatus(422)
            ->assertJsonPath('error', 'EMPLOYEE_EMAIL_TAKEN');
    }

    public function test_check_in_requires_confirmation_then_records_the_punch(): void
    {
        [$company, $principal] = $this->fixture();
        Sanctum::actingAs($principal);

        $payload = $this->propose('check_in_employee', [], $company, $principal);

        $this->assertSame('confirmation_required', $payload['status'] ?? null);
        $this->assertSame(0, AttendanceLog::query()->count(), 'aucun pointage avant confirmation');

        $this->confirm('check_in_employee', [], $company, $principal)
            ->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.result.action', 'check_in');

        $this->assertSame(1, AttendanceLog::query()
            ->where('employee_id', $principal->id)
            ->where('company_id', $company->id)
            ->count());
    }

    public function test_employee_can_only_punch_for_themselves(): void
    {
        [$company, $principal] = $this->fixture();

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        /** @var Employee $colleague */
        $colleague = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        // L'employé demande à pointer pour un collègue : l'employee_id du LLM
        // est ignoré, le pointage retombe sur lui-même (#6533).
        $this->confirm('check_in_employee', ['employee_id' => $colleague->id], $company, $employee)
            ->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.result.employee_id', $employee->id);

        $this->assertSame(0, AttendanceLog::query()->where('employee_id', $colleague->id)->count());
        $this->assertSame(1, AttendanceLog::query()->where('employee_id', $employee->id)->count());
    }
}
