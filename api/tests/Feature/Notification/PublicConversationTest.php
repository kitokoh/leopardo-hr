<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Notification\Domain\Models\ConversationMessage;
use App\Modules\Notification\Domain\Models\ConversationThread;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8760 [BOS-CHAT] — Messagerie client public ↔ professionnel.
 *
 * Couvre les critères d'acceptation 3, 4, 5, 6 et 7 de la mission
 * « Leopardo Public » :
 *
 *   3. le client démarre une conversation, le professionnel autorisé la reçoit ;
 *   4. le pro répond, le client retrouve la réponse ;
 *   5. un utilisateur d'une entreprise ne peut pas lire les conversations d'une autre ;
 *   6. un client ne peut pas exécuter une action réservée à un manager ;
 *   7. un profil désactivé n'est plus joignable.
 *
 * ⚠️ Ces tests s'exécutent sur les VRAIES migrations (`RefreshTenantDatabase`),
 * pas sur une fixture partielle : les colonnes `public_client_id`,
 * `author_type` et `public_author_id` sont donc réellement vérifiées.
 */
class PublicConversationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $otherCompany;

    private Employee $manager;

    private Employee $otherManager;

    private Employee $plainEmployee;

    private User $client;

    private User $otherClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'slug' => 'resto-leopardo',
            'name' => 'Restaurant Leopardo',
        ]);

        $this->otherCompany = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'slug' => 'pharmacie-rivale',
            'name' => 'Pharmacie Rivale',
        ]);

        $this->manager = $this->employeeFor($this->company, 'manager');
        $this->otherManager = $this->employeeFor($this->otherCompany, 'manager');
        $this->plainEmployee = $this->employeeFor($this->company, 'employee');

        $this->client = User::factory()->create([
            'first_name' => 'Amina',
            'last_name' => 'Client',
        ]);

        $this->otherClient = User::factory()->create([
            'first_name' => 'Karim',
            'last_name' => 'Autre',
        ]);
    }

    private function employeeFor(Company $company, string $role): Employee
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $role): Employee {
            /** @var Employee $employee */
            $employee = Employee::factory()->create([
                'company_id' => $company->id,
                'role' => $role,
                'status' => 'active',
            ]);

            return $employee;
        });
    }

    /**
     * Crée un fil public directement en base, dans le contexte du tenant.
     *
     * On ne passe PAS par le service ici : ces tests vérifient les CONTRÔLES
     * D'ACCÈS, pas la résolution du profil public (couverte par
     * `PublicContactRequestTest` et les tests de découverte).
     */
    private function seedPublicThread(Company $company, User $client, string $slug = 'resto-test'): ConversationThread
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $client, $slug): ConversationThread {
            /** @var ConversationThread $thread */
            $thread = ConversationThread::query()->create([
                'company_id' => $company->id,
                'employee_id' => null,
                'manager_id' => null,
                'kind' => ConversationThread::KIND_PUBLIC,
                'public_client_id' => $client->id,
                'public_target_slug' => $slug,
                'created_by' => null,
                'title' => 'Conversation avec '.$company->name,
                'status' => 'open',
            ]);

            /** @var ConversationMessage $message */
            $message = ConversationMessage::query()->create([
                'company_id' => $company->id,
                'conversation_thread_id' => $thread->id,
                'author_id' => null,
                'author_type' => 'public_client',
                'public_author_id' => $client->id,
                'body' => 'Bonjour, avez-vous une table pour 4 ce soir ?',
                'seq' => 1,
            ]);

            $thread->forceFill([
                'last_message_id' => $message->id,
                'last_message_seq' => 1,
                'last_message_at' => $message->created_at,
            ])->save();

            return $thread;
        });
    }

    // ── Critère 3 : le client démarre, le pro reçoit ─────────────────────────

    public function test_manager_sees_the_public_thread_in_their_inbox(): void
    {
        $thread = $this->seedPublicThread($this->company, $this->client);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/conversations/public');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $thread->id);
        $response->assertJsonPath('data.0.target_slug', 'resto-test');
    }

    public function test_manager_can_read_the_public_thread_messages(): void
    {
        $thread = $this->seedPublicThread($this->company, $this->client);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson("/api/v1/conversations/public/{$thread->id}");

        $response->assertOk();
        $response->assertJsonPath('data.messages.0.from', 'client');
        $response->assertJsonPath('data.messages.0.body', 'Bonjour, avez-vous une table pour 4 ce soir ?');
    }

    // ── Critère 4 : le pro répond, le client retrouve la réponse ─────────────

    public function test_manager_can_reply_and_client_sees_the_reply(): void
    {
        $thread = $this->seedPublicThread($this->company, $this->client);

        Sanctum::actingAs($this->manager);

        $reply = $this->postJson("/api/v1/conversations/public/{$thread->id}/messages", [
            'body' => 'Oui, une table de 4 est disponible à 20h.',
        ]);

        $reply->assertCreated();
        $reply->assertJsonPath('data.from', 'professional');

        // Le client relit SON fil : il doit voir la réponse du professionnel.
        Sanctum::actingAs($this->client, ['*'], 'user_api');

        $clientView = $this->getJson("/api/v1/public/conversations/{$this->company->id}/{$thread->id}");

        $clientView->assertOk();

        $bodies = array_column($clientView->json('data.messages'), 'body');
        $this->assertContains('Oui, une table de 4 est disponible à 20h.', $bodies);

        $froms = array_column($clientView->json('data.messages'), 'from');
        $this->assertContains('professional', $froms);
    }

    public function test_client_can_reply_in_their_own_thread(): void
    {
        $thread = $this->seedPublicThread($this->company, $this->client);

        Sanctum::actingAs($this->client, ['*'], 'user_api');

        $response = $this->postJson("/api/v1/public/conversations/{$this->company->id}/{$thread->id}/messages", [
            'body' => 'Parfait, je confirme pour 20h.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.from', 'client');
    }

    // ── Critère 5 : isolation multi-tenant ───────────────────────────────────

    public function test_manager_of_another_tenant_cannot_read_the_thread(): void
    {
        $thread = $this->seedPublicThread($this->company, $this->client);

        // Le manager de l'AUTRE entreprise tente d'ouvrir le fil : 404, jamais
        // 403 (un 403 révélerait l'existence du fil).
        Sanctum::actingAs($this->otherManager);

        $this->getJson("/api/v1/conversations/public/{$thread->id}")
            ->assertNotFound();
    }

    public function test_manager_of_another_tenant_cannot_reply_in_the_thread(): void
    {
        $thread = $this->seedPublicThread($this->company, $this->client);

        Sanctum::actingAs($this->otherManager);

        $this->postJson("/api/v1/conversations/public/{$thread->id}/messages", [
            'body' => 'Message intrusif depuis un autre tenant.',
        ])->assertNotFound();

        // Le message n'a PAS été écrit.
        app(TenantManager::class)->withinTenant($this->company, function () use ($thread): void {
            $this->assertSame(
                1,
                ConversationMessage::query()->where('conversation_thread_id', $thread->id)->count(),
            );
        });
    }

    public function test_client_cannot_read_another_clients_thread(): void
    {
        $thread = $this->seedPublicThread($this->company, $this->client);

        // Un AUTRE client change l'identifiant dans l'URL : 404.
        Sanctum::actingAs($this->otherClient, ['*'], 'user_api');

        $this->getJson("/api/v1/public/conversations/{$this->company->id}/{$thread->id}")
            ->assertNotFound();
    }

    public function test_client_cannot_reply_in_another_clients_thread(): void
    {
        $thread = $this->seedPublicThread($this->company, $this->client);

        Sanctum::actingAs($this->otherClient, ['*'], 'user_api');

        $this->postJson("/api/v1/public/conversations/{$this->company->id}/{$thread->id}/messages", [
            'body' => 'Je ne suis pas le propriétaire de ce fil.',
        ])->assertNotFound();
    }

    public function test_client_index_only_returns_their_own_threads(): void
    {
        $mine = $this->seedPublicThread($this->company, $this->client, 'resto-mien');
        $theirs = $this->seedPublicThread($this->company, $this->otherClient, 'resto-autre');

        // Index de routage plateforme (aucun contenu de message).
        DB::table('public_conversation_index')->insert([
            ['user_id' => $this->client->id, 'company_id' => $this->company->id, 'thread_id' => $mine->id, 'target_slug' => 'resto-mien', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $this->otherClient->id, 'company_id' => $this->company->id, 'thread_id' => $theirs->id, 'target_slug' => 'resto-autre', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Sanctum::actingAs($this->client, ['*'], 'user_api');

        $response = $this->getJson('/api/v1/public/conversations');

        $response->assertOk();

        $ids = array_column($response->json('data'), 'thread_id');
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    // ── Critère 6 : rôles ────────────────────────────────────────────────────

    public function test_plain_employee_cannot_read_public_threads(): void
    {
        $thread = $this->seedPublicThread($this->company, $this->client);

        // Même tenant, mais rôle `employee` : les échanges commerciaux avec les
        // clients ne sont pas dans son périmètre.
        Sanctum::actingAs($this->plainEmployee);

        $this->getJson("/api/v1/conversations/public/{$thread->id}")
            ->assertNotFound();
    }

    public function test_plain_employee_inbox_is_empty(): void
    {
        $this->seedPublicThread($this->company, $this->client);

        Sanctum::actingAs($this->plainEmployee);

        $response = $this->getJson('/api/v1/conversations/public');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_client_cannot_access_the_professional_inbox(): void
    {
        // Le client n'a pas de token Sanctum employé : la route interne doit
        // refuser l'accès (401), pas servir des données.
        Sanctum::actingAs($this->client, ['*'], 'user_api');

        $this->getJson('/api/v1/conversations/public')
            ->assertUnauthorized();
    }

    // ── Critère 7 : profil désactivé / inexistant ────────────────────────────

    public function test_starting_a_conversation_on_an_unknown_profile_returns_404(): void
    {
        Sanctum::actingAs($this->client, ['*'], 'user_api');

        // Aucun résolveur ne connaît ce slug : fail-closed, 404 uniforme.
        $this->postJson('/api/v1/public/conversations', [
            'target_type' => 'showcase',
            'target_slug' => 'profil-inexistant-ou-desactive',
            'body' => 'Bonjour, êtes-vous disponible ?',
        ])->assertNotFound();
    }

    public function test_starting_a_conversation_with_an_unknown_target_type_is_rejected(): void
    {
        Sanctum::actingAs($this->client, ['*'], 'user_api');

        $this->postJson('/api/v1/public/conversations', [
            'target_type' => 'verticale-inconnue',
            'target_slug' => 'peu-importe',
            'body' => 'Bonjour.',
        ])->assertStatus(422);
    }

    // ── Validation et robustesse ─────────────────────────────────────────────

    public function test_empty_message_is_rejected(): void
    {
        $thread = $this->seedPublicThread($this->company, $this->client);

        Sanctum::actingAs($this->client, ['*'], 'user_api');

        $this->postJson("/api/v1/public/conversations/{$this->company->id}/{$thread->id}/messages", [
            'body' => '   ',
        ])->assertStatus(422);
    }

    public function test_oversized_message_is_rejected(): void
    {
        $thread = $this->seedPublicThread($this->company, $this->client);

        Sanctum::actingAs($this->client, ['*'], 'user_api');

        $this->postJson("/api/v1/public/conversations/{$this->company->id}/{$thread->id}/messages", [
            'body' => str_repeat('a', 2001),
        ])->assertStatus(422);
    }

    public function test_unauthenticated_client_cannot_read_conversations(): void
    {
        $thread = $this->seedPublicThread($this->company, $this->client);

        $this->getJson("/api/v1/public/conversations/{$this->company->id}/{$thread->id}")
            ->assertUnauthorized();
    }

    // ── Pagination par curseur (pas de faux temps réel) ──────────────────────

    public function test_messages_are_paginated_by_seq_cursor(): void
    {
        $thread = $this->seedPublicThread($this->company, $this->client);

        app(TenantManager::class)->withinTenant($this->company, function () use ($thread): void {
            foreach ([2, 3, 4] as $seq) {
                ConversationMessage::query()->create([
                    'company_id' => $this->company->id,
                    'conversation_thread_id' => $thread->id,
                    'author_id' => $this->manager->id,
                    'author_type' => 'employee',
                    'public_author_id' => null,
                    'body' => "Réponse {$seq}",
                    'seq' => $seq,
                ]);
            }

            $thread->forceFill(['last_message_seq' => 4])->save();
        });

        Sanctum::actingAs($this->client, ['*'], 'user_api');

        // Rattrapage : tout ce qui est arrivé après seq=1.
        $response = $this->getJson("/api/v1/public/conversations/{$this->company->id}/{$thread->id}/messages?after_seq=1");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonPath('data.0.seq', 2);
        $response->assertJsonPath('meta.last_seq', 4);
    }
}
