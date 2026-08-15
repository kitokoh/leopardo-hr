<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2311 — envoi d'un message chat depuis la console super-admin :
 * POST /api/v1/admin/ai/chat n'existait pas (404 silencieux dans ChatView).
 *
 * L'assistant IA n'est pas câblé pour la plateforme : le message est persisté
 * dans shared_tenants.ai_conversations et la réponse est une structure
 * honnête « assistant non configuré » (plus de 404).
 */
class PlatformAdminAiChatTest extends TestCase
{
    use RefreshTenantDatabase;

    private SuperAdmin $superAdmin;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var SuperAdmin $superAdmin */
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Super Admin Chat Test',
            'email' => 'sa-chat-test@leopardo-rh.com',
            'password_hash' => bcrypt('secret123'),
            'role' => 'super_admin',
        ]);
        $this->superAdmin = $superAdmin;

        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;
    }

    private function actingAsSuperAdmin(): void
    {
        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');
    }

    public function test_chat_requires_super_admin_auth(): void
    {
        $this->postJson('/api/v1/admin/ai/chat', ['message' => 'Bonjour'])
            ->assertStatus(401);

        Sanctum::actingAs($this->manager);
        // Un manager tenant n'authentifie pas la garde super_admin_api → 401.
        $this->postJson('/api/v1/admin/ai/chat', ['message' => 'Bonjour'])
            ->assertStatus(401);
    }

    public function test_chat_creates_conversation_and_returns_structured_response(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/v1/admin/ai/chat', [
            'message' => 'Bonjour, comment se porte la plateforme ?',
        ])->assertOk();

        $response->assertJsonStructure(['conversation_id', 'response']);
        $this->assertSame(
            __('platform.ai_assistant_not_configured'),
            $response->json('response')
        );

        $conversationId = $response->json('conversation_id');
        $this->assertNotNull($conversationId);

        $row = DB::table('shared_tenants.ai_conversations')->where('id', $conversationId)->first();
        $this->assertNotNull($row);
        $messages = json_decode((string) $row->messages, true);
        $this->assertCount(1, $messages);
        $this->assertSame('user', $messages[0]['role']);
        $this->assertSame('Bonjour, comment se porte la plateforme ?', $messages[0]['content']);
    }

    public function test_chat_appends_message_to_existing_conversation(): void
    {
        $this->actingAsSuperAdmin();

        $first = $this->postJson('/api/v1/admin/ai/chat', ['message' => 'Premier message'])->assertOk();
        $conversationId = $first->json('conversation_id');

        $second = $this->postJson('/api/v1/admin/ai/chat', [
            'message' => 'Deuxième message',
            'conversation_id' => $conversationId,
        ])->assertOk();

        $this->assertSame($conversationId, $second->json('conversation_id'));

        $row = DB::table('shared_tenants.ai_conversations')->where('id', $conversationId)->first();
        $messages = json_decode((string) $row->messages, true);
        $this->assertCount(2, $messages);
        $this->assertSame('Deuxième message', $messages[1]['content']);
    }

    public function test_chat_rejects_unknown_conversation(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/v1/admin/ai/chat', [
            'message' => 'Bonjour',
            'conversation_id' => 999999,
        ])->assertStatus(404)
            ->assertJsonPath('error', 'CONVERSATION_NOT_FOUND');
    }

    public function test_chat_validates_message(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/v1/admin/ai/chat', ['message' => ''])
            ->assertStatus(422);
    }
}
