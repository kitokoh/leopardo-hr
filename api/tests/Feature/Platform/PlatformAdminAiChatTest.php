<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\AI\DTOs\AIResponse;
use App\AI\LLMClient;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * QA #2311 — POST /admin/ai/chat : le SPA admin peut envoyer un message dans
 * une conversation IA tenant existante (la route tenant /api/v1/ai/chat exige
 * un Employee → 401 pour le super-admin).
 */
class PlatformAdminAiChatTest extends TestCase
{
    use CreatesMvpSchema;

    protected SuperAdmin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        /** @var SuperAdmin $superAdmin */
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Super Admin Test',
            'email' => 'sa-chat-test@leopardo-rh.com',
            'password_hash' => bcrypt('secret123'),
        ]);
        $this->superAdmin = $superAdmin;

        // Le trait MVP ne crée pas ai_conversations : schéma minimal local.
        if (! Schema::hasTable('ai_conversations')) {
            Schema::create('ai_conversations', function ($table): void {
                $table->bigIncrements('id');
                $table->uuid('company_id')->nullable()->index();
                $table->unsignedInteger('user_id');
                $table->string('title', 200)->default('Nouvelle conversation');
                $table->jsonb('messages')->default('[]');
                $table->jsonb('context')->default('{}');
                $table->unsignedInteger('token_count')->default(0);
                $table->timestampsTz();
            });
        }
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function makeConversation(): int
    {
        return (int) DB::table('ai_conversations')->insertGetId([
            'company_id' => '00000000-0000-0000-0000-000000000001',
            'user_id' => 1,
            'title' => 'Conversation test',
            'messages' => json_encode([
                ['role' => 'user', 'content' => 'Bonjour', 'created_at' => now()->toIso8601String()],
                ['role' => 'assistant', 'content' => 'Bonjour !', 'created_at' => now()->toIso8601String()],
            ]),
            'context' => '{}',
            'token_count' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_send_message_appends_user_and_assistant_messages(): void
    {
        $llm = $this->createMock(LLMClient::class);
        $llm->method('chat')->willReturn(new AIResponse(content: 'Réponse LLM'));

        $this->app->instance(LLMClient::class, $llm);

        $conversationId = $this->makeConversation();

        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $response = $this->postJson('/api/v1/admin/ai/chat', [
            'message' => 'Nouveau message',
            'conversation_id' => $conversationId,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.conversation_id', $conversationId);
        $response->assertJsonPath('data.response', 'Réponse LLM');

        $row = DB::table('ai_conversations')->where('id', $conversationId)->first();
        $messages = json_decode((string) $row->messages, true);
        $this->assertCount(4, $messages);
        $this->assertSame('user', $messages[2]['role']);
        $this->assertSame('Nouveau message', $messages[2]['content']);
        $this->assertSame('assistant', $messages[3]['role']);
        $this->assertSame('Réponse LLM', $messages[3]['content']);
    }

    public function test_send_message_requires_conversation_id(): void
    {
        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $this->postJson('/api/v1/admin/ai/chat', ['message' => 'Hello'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['conversation_id']);
    }

    public function test_send_message_404_for_unknown_conversation(): void
    {
        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $this->postJson('/api/v1/admin/ai/chat', [
            'message' => 'Hello',
            'conversation_id' => 999999,
        ])->assertNotFound();
    }

    public function test_send_message_falls_back_when_llm_fails(): void
    {
        $llm = $this->createMock(LLMClient::class);
        $llm->method('chat')->willReturn(new AIResponse(content: '', error: 'LLM down'));

        $this->app->instance(LLMClient::class, $llm);

        $conversationId = $this->makeConversation();

        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $response = $this->postJson('/api/v1/admin/ai/chat', [
            'message' => 'Hello',
            'conversation_id' => $conversationId,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Assistant indisponible', (string) $response->json('data.response'));
    }

    public function test_requires_super_admin_authentication(): void
    {
        $this->postJson('/api/v1/admin/ai/chat', [
            'message' => 'Hello',
            'conversation_id' => 1,
        ])->assertUnauthorized();
    }
}
