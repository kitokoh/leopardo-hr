<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\Jobs\ExportAiConversationJob;
use App\AI\Models\AiDeadLetterEntry;
use App\AI\Models\AiExport;
use App\AI\Models\AIConversation;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * BC-23-D07 (issue #6239) — asynchronisme IA : file idempotente + DLQ dédiée.
 *
 * Couvre l'idempotence (dédup par tenant+conversation+format), le cycle du
 * job, l'isolation cross-tenant/par propriétaire, la consignation en
 * dead-letter queue après échec et le replay contrôlé.
 */
class ConversationExportTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        config(['ai.enabled' => true]);
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_export_request_creates_pending_export_and_dispatches_job(): void
    {
        [$company, $employee] = $this->aiFixture();
        $conversation = $this->conversation($company->id, $employee->id);

        Bus::fake([ExportAiConversationJob::class]);
        Sanctum::actingAs($employee);

        $this->postJson("/api/v1/ai/conversations/{$conversation->id}/export")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.conversation_id', $conversation->id);

        $this->assertDatabaseHas('ai_exports', [
            'company_id' => $company->id,
            'conversation_id' => $conversation->id,
            'status' => 'pending',
        ]);

        Bus::assertDispatched(ExportAiConversationJob::class);
    }

    public function test_export_request_is_idempotent(): void
    {
        [$company, $employee] = $this->aiFixture();
        $conversation = $this->conversation($company->id, $employee->id);

        Bus::fake([ExportAiConversationJob::class]);
        Sanctum::actingAs($employee);

        $first = $this->postJson("/api/v1/ai/conversations/{$conversation->id}/export")->json('data');
        $second = $this->postJson("/api/v1/ai/conversations/{$conversation->id}/export")->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertDatabaseCount('ai_exports', 1);
        Bus::assertDispatchedTimes(ExportAiConversationJob::class, 1);
    }

    public function test_export_job_generates_file_and_marks_done_with_audit(): void
    {
        [$company, $employee] = $this->aiFixture();
        $conversation = $this->conversation($company->id, $employee->id);

        Sanctum::actingAs($employee);

        $export = $this->postJson("/api/v1/ai/conversations/{$conversation->id}/export")
            ->assertOk()
            ->json('data');

        // QUEUE_CONNECTION=sync : le job s'exécute en ligne.
        $this->assertDatabaseHas('ai_exports', ['id' => $export['id'], 'status' => 'done']);

        $row = AiExport::query()->find($export['id']);
        $this->assertNotNull($row->file_path);
        Storage::disk('local')->assertExists($row->file_path);

        // Traçabilité : audit workflow conversation_export, corrélable.
        $this->assertDatabaseHas('ai_audit_logs', [
            'company_id' => $company->id,
            'conversation_id' => $conversation->id,
            'workflow' => 'conversation_export',
        ]);
    }

    public function test_export_is_scoped_to_conversation_owner_and_company(): void
    {
        [$company, $employee] = $this->aiFixture();
        $otherCompany = Company::factory()->create();
        $otherUser = Employee::factory()->create(['company_id' => $company->id]);
        $foreignConversation = $this->conversation($otherCompany->id, $employee->id);

        Sanctum::actingAs($employee);
        $this->postJson("/api/v1/ai/conversations/{$foreignConversation->id}/export")
            ->assertNotFound();

        Sanctum::actingAs($otherUser);
        $ownConversation = $this->conversation($company->id, $employee->id);
        $this->postJson("/api/v1/ai/conversations/{$ownConversation->id}/export")
            ->assertNotFound();
    }

    public function test_export_status_is_scoped_to_owner(): void
    {
        [$company, $employee] = $this->aiFixture();
        $otherUser = Employee::factory()->create(['company_id' => $company->id]);
        $conversation = $this->conversation($company->id, $employee->id);
        $export = AiExport::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'conversation_id' => $conversation->id,
            'format' => 'json',
            'dedup_key' => AiExport::dedupKey($company->id, $conversation->id, 'json'),
            'status' => 'pending',
        ]);

        Sanctum::actingAs($otherUser);
        $this->getJson("/api/v1/ai/exports/{$export->id}")->assertNotFound();

        Sanctum::actingAs($employee);
        $this->getJson("/api/v1/ai/exports/{$export->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_failed_job_moves_export_to_failed_and_records_dlq(): void
    {
        [$company, $employee] = $this->aiFixture();
        $conversation = $this->conversation($company->id, $employee->id);
        $export = AiExport::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'conversation_id' => $conversation->id,
            'format' => 'json',
            'dedup_key' => AiExport::dedupKey($company->id, $conversation->id, 'json'),
            'status' => 'processing',
        ]);

        $job = new ExportAiConversationJob($export->id);
        $job->failed(new \RuntimeException('Provider down'));

        $this->assertDatabaseHas('ai_exports', [
            'id' => $export->id,
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('ai_dead_letter_queue', [
            'dedup_key' => $export->dedup_key,
            'status' => 'open',
        ]);
    }

    public function test_dlq_replay_redispatch_and_resolve_on_success(): void
    {
        [$company, $employee] = $this->aiFixture();
        $conversation = $this->conversation($company->id, $employee->id);
        $export = AiExport::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'conversation_id' => $conversation->id,
            'format' => 'json',
            'dedup_key' => AiExport::dedupKey($company->id, $conversation->id, 'json'),
            'status' => 'failed',
            'error_message' => 'Provider down',
        ]);
        AiDeadLetterEntry::create([
            'company_id' => $company->id,
            'job_class' => ExportAiConversationJob::class,
            'job_id' => $export->id,
            'dedup_key' => $export->dedup_key,
            'payload' => ['ai_export_id' => $export->id],
            'error' => 'Provider down',
            'status' => 'open',
        ]);

        // QUEUE_CONNECTION=sync : le re-dispatch exécute le job en ligne →
        // succès → DLQ résolue.
        $this->artisan('ai:dlq:replay', ['--company-id' => $company->id])
            ->assertSuccessful();

        $this->assertDatabaseHas('ai_exports', [
            'id' => $export->id,
            'status' => 'done',
        ]);
        $this->assertDatabaseHas('ai_dead_letter_queue', [
            'dedup_key' => $export->dedup_key,
            'status' => 'resolved',
        ]);
    }

    public function test_unsupported_format_is_rejected(): void
    {
        [$company, $employee] = $this->aiFixture();
        $conversation = $this->conversation($company->id, $employee->id);

        Sanctum::actingAs($employee);

        $this->postJson("/api/v1/ai/conversations/{$conversation->id}/export", ['format' => 'csv'])
            ->assertStatus(422);
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function aiFixture(): array
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return [$company, $employee];
    }

    private function conversation(string $companyId, int $userId): AIConversation
    {
        return AIConversation::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'title' => 'Conversation de test',
            'messages' => [
                ['role' => 'user', 'content' => 'Bonjour'],
                ['role' => 'assistant', 'content' => 'Bonjour, comment puis-je aider ?'],
            ],
            'context' => [],
            'token_count' => 12,
        ]);
    }
}
