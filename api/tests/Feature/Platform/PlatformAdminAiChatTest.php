<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2311 — POST /admin/ai/chat : l'envoi d'un message depuis la
 * console super-admin doit répondre (plus de 404 silencieux), sans écrire
 * dans une conversation d'un tenant (isolation cross-tenant).
 */
class PlatformAdminAiChatTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var SuperAdmin $superAdmin */
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin-ai-chat@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');
    }

    public function test_chat_requires_message(): void
    {
        $this->postJson('/api/v1/admin/ai/chat', [])->assertUnprocessable();
        $this->postJson('/api/v1/admin/ai/chat', ['message' => '   '])->assertUnprocessable();
    }

    public function test_chat_without_conversation_returns_structured_reply(): void
    {
        // Issue #2311 : la console plateforme est cross-tenant en lecture
        // seule — pas d'assistant IA plateforme. Le contrôleur renvoie un 501
        // explicite (ADMIN_CHAT_UNAVAILABLE), jamais un 200 factice.
        $response = $this->postJson('/api/v1/admin/ai/chat', [
            'message' => 'Bonjour, qui sont mes employés ?',
        ])->assertStatus(501);

        $response->assertJsonPath('error', 'ADMIN_CHAT_UNAVAILABLE');
        $response->assertJsonPath('message', __('platform.admin_chat_unavailable'));
    }

    public function test_chat_unknown_conversation_returns_404(): void
    {
        $this->postJson('/api/v1/admin/ai/chat', [
            'message' => 'Bonjour',
            'conversation_id' => 999_999,
        ])->assertNotFound();
    }

    public function test_chat_with_existing_conversation_does_not_write_to_tenant(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        // La table ai_conversations vit dans le schéma tenant partagé.
        $conversationId = DB::table('shared_tenants.ai_conversations')->insertGetId([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'title' => 'Conversation existante',
            'messages' => json_encode([['role' => 'user', 'content' => 'Bonjour', 'created_at' => now()->toISOString()]]),
            'token_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $before = DB::table('shared_tenants.ai_conversations')->where('id', $conversationId)->value('messages');

        $this->postJson('/api/v1/admin/ai/chat', [
            'message' => 'Réponse de la console',
            'conversation_id' => $conversationId,
        ])->assertStatus(501);

        // Aucune écriture dans la conversation du tenant (isolation).
        $after = DB::table('shared_tenants.ai_conversations')->where('id', $conversationId)->value('messages');
        $this->assertSame($before, $after);
    }
    // ── #6690 — IA non provisionnée : état métier attendu (404), pas un 500 ──

    public function test_conversations_index_returns_404_when_ai_table_missing(): void
    {
        // L'IA n'a jamais été provisionnée : la table tenant n'existe pas.
        DB::statement('DROP TABLE IF EXISTS shared_tenants.ai_conversations CASCADE');

        $this->getJson('/api/v1/admin/ai/conversations')
            ->assertStatus(404)
            ->assertJsonPath('error', 'AI_CONVERSATIONS_UNAVAILABLE');
    }

    public function test_conversation_messages_returns_404_when_ai_table_missing(): void
    {
        DB::statement('DROP TABLE IF EXISTS shared_tenants.ai_conversations CASCADE');

        $this->getJson('/api/v1/admin/ai/conversations/1/messages')
            ->assertStatus(404)
            ->assertJsonPath('error', 'AI_MESSAGES_UNAVAILABLE');
    }

    public function test_conversations_index_returns_200_with_message_count_when_table_exists(): void
    {
        // #6690 (cause racine n°2) : `json_array_length` est invalide sur une
        // colonne jsonb → 500 à CHAQUE requête, même table présente. Le
        // correctif `jsonb_array_length` doit rendre l'endpoint fonctionnel.
        // FK ai_conversations.user_id → employees : créer un employé minimal.
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        DB::table('ai_conversations')->insert([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'title' => 'Conversation test',
            'messages' => json_encode([['role' => 'user', 'content' => 'Bonjour']]),
            'context' => json_encode([]),
            'token_count' => 3,
        ]);

        $this->getJson('/api/v1/admin/ai/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.message_count', 1)
            ->assertJsonPath('data.0.title', 'Conversation test');
    }
}
