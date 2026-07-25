<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Domain\Models\ConversationMessage;
use App\Modules\Notification\Domain\Models\ConversationThread;
use App\Modules\Notification\Domain\Models\Notification;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class ConversationControllerTest extends TestCase
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

    public function test_employee_can_start_a_free_standing_thread_with_their_manager(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => $manager->id]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/conversations', [
            'title' => 'Question sur mon planning',
            'body' => 'Bonjour, puis-je changer mon horaire de vendredi ?',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Question sur mon planning')
            ->assertJsonPath('data.employee.id', $employee->id)
            ->assertJsonPath('data.manager.id', $manager->id);

        $this->assertDatabaseHas('conversation_threads', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'manager_id' => $manager->id,
        ]);

        $this->assertSame(1, ConversationMessage::query()->where('author_id', $employee->id)->count());
        $this->assertSame(1, Notification::query()->where('employee_id', $manager->id)->count());
    }

    public function test_thread_can_be_anchored_to_a_salary_advance_subject(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => $manager->id]);

        $advance = SalaryAdvance::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 100,
            'currency' => 'DZD',
            'reason' => 'Urgence familiale',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/conversations', [
            'title' => 'A propos de mon avance',
            'body' => 'Pouvez-vous valider ma demande rapidement ?',
            'subject_type' => 'salary_advance',
            'subject_id' => $advance->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.subject_type', 'salary_advance')
            ->assertJsonPath('data.subject_id', $advance->id);
    }

    public function test_employee_cannot_anchor_a_thread_to_someone_elses_subject(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => $manager->id]);
        $otherEmployee = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => $manager->id]);

        $advance = SalaryAdvance::query()->create([
            'company_id' => $company->id,
            'employee_id' => $otherEmployee->id,
            'amount' => 50,
            'currency' => 'DZD',
            'reason' => 'Autre employe',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/conversations', [
            'title' => 'Tentative',
            'body' => 'Ceci ne doit pas fonctionner.',
            'subject_type' => 'salary_advance',
            'subject_id' => $advance->id,
        ])->assertUnprocessable();
    }

    public function test_manager_can_reply_and_thread_marks_unread_for_employee(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => $manager->id]);

        $thread = ConversationThread::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'manager_id' => $manager->id,
            'title' => 'Discussion en cours',
            'status' => 'open',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/conversations/{$thread->id}/messages", [
            'body' => 'Je regarde ca aujourd\'hui.',
        ]);

        $response->assertCreated()->assertJsonPath('data.author_id', $manager->id);

        $thread->refresh();
        $this->assertTrue($thread->isUnreadFor($employee));
        $this->assertFalse($thread->isUnreadFor($manager));

        $this->assertSame(1, Notification::query()->where('employee_id', $employee->id)->count());
    }

    public function test_manager_cannot_see_a_thread_belonging_to_another_managers_report(): void
    {
        $company = Company::factory()->create();
        $managerA = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $managerB = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => $managerA->id]);

        $thread = ConversationThread::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'manager_id' => $managerA->id,
            'title' => 'Prive',
            'status' => 'open',
        ]);

        Sanctum::actingAs($managerB);

        $this->getJson("/api/v1/conversations/{$thread->id}")->assertNotFound();
    }

    public function test_employee_cannot_see_another_companys_thread(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        $employeeA = Employee::factory()->create(['company_id' => $companyA->id, 'manager_id' => $managerA->id]);
        $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);

        $thread = ConversationThread::query()->create([
            'company_id' => $companyA->id,
            'employee_id' => $employeeA->id,
            'manager_id' => $managerA->id,
            'title' => 'Cross-tenant leak attempt',
            'status' => 'open',
        ]);

        Sanctum::actingAs($employeeB);

        $this->getJson("/api/v1/conversations/{$thread->id}")->assertNotFound();
    }

    public function test_message_can_carry_a_single_small_attachment_and_is_downloadable_by_participants(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => $manager->id]);

        Sanctum::actingAs($employee);

        $file = UploadedFile::fake()->create('justificatif.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/v1/conversations', [
            'title' => 'Justificatif absence',
            'body' => 'Voici mon justificatif.',
            'attachment' => $file,
        ]);

        $response->assertCreated();
        $threadId = $response->json('data.id');

        $thread = ConversationThread::query()->find($threadId);
        $message = $thread->messages()->first();

        $this->assertNotNull($message->attachment_path);
        $this->assertSame('justificatif.pdf', $message->attachment_original_name);

        Sanctum::actingAs($manager);
        $this->get("/api/v1/conversations/{$threadId}/messages/{$message->id}/attachment")
            ->assertOk();
    }

    public function test_attachment_over_size_limit_is_rejected(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => $manager->id]);

        Sanctum::actingAs($employee);

        $file = UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf');

        $this->postJson('/api/v1/conversations', [
            'title' => 'Trop gros',
            'body' => 'Ceci devrait echouer.',
            'attachment' => $file,
        ])->assertUnprocessable();
    }

    public function test_manager_lists_only_threads_of_their_own_reports(): void
    {
        $company = Company::factory()->create();
        $managerA = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $managerB = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employeeA = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => $managerA->id]);
        $employeeB = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => $managerB->id]);

        ConversationThread::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeA->id,
            'manager_id' => $managerA->id,
            'title' => 'Thread A',
            'status' => 'open',
        ]);
        ConversationThread::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeB->id,
            'manager_id' => $managerB->id,
            'title' => 'Thread B',
            'status' => 'open',
        ]);

        Sanctum::actingAs($managerA);

        $response = $this->getJson('/api/v1/conversations');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->all();

        $this->assertContains('Thread A', $titles);
        $this->assertNotContains('Thread B', $titles);
    }
}
