<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Domain\Models\ConversationMessage;
use App\Modules\Notification\Domain\Models\ConversationParticipant;
use App\Modules\Notification\Domain\Models\ConversationThread;
use App\Modules\Notification\Domain\Models\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #8746 [BOS-CHAT] — Groupes de discussion : rôles, ajout/retrait, sortie,
 * isolation multi-tenant, et non-régression du contrat binaire existant.
 *
 * Chaque test d'accès refusé vérifie un **404** et non un 403 : un 403
 * révélerait à un tiers l'existence d'un fil auquel il n'appartient pas.
 */
class ConversationGroupTest extends TestCase
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

    public function test_group_is_created_with_creator_role_and_membership_rows(): void
    {
        [$company, $author, $alice, $bob] = $this->seedCompanyWithThreeEmployees();

        Sanctum::actingAs($author);

        $response = $this->postJson('/api/v1/conversations/groups', [
            'title' => 'Préparation inventaire',
            'body' => 'On se répartit les rayons ?',
            'participant_ids' => [$alice->id, $bob->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.kind', ConversationThread::KIND_GROUP)
            ->assertJsonPath('data.created_by', $author->id);

        $threadId = (int) $response->json('data.id');

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_thread_id' => $threadId,
            'employee_id' => $author->id,
            'role' => ConversationParticipant::ROLE_CREATOR,
        ]);
        $this->assertSame(
            2,
            ConversationParticipant::query()
                ->where('conversation_thread_id', $threadId)
                ->where('role', ConversationParticipant::ROLE_MEMBER)
                ->count(),
        );

        // Une seule notification par membre ajouté, et aucune pour l'auteur.
        $this->assertSame(0, Notification::query()->where('employee_id', $author->id)->count());
        $this->assertSame(2, Notification::query()->whereIn('employee_id', [$alice->id, $bob->id])->count());
    }

    public function test_group_creation_rejects_a_participant_from_another_tenant(): void
    {
        [$company, $author, $alice] = $this->seedCompanyWithThreeEmployees();
        $otherCompany = Company::factory()->create();
        $foreigner = Employee::factory()->create(['company_id' => $otherCompany->id]);

        Sanctum::actingAs($author);

        $this->postJson('/api/v1/conversations/groups', [
            'title' => 'Tentative inter-tenant',
            'body' => 'Ne doit pas passer.',
            'participant_ids' => [$alice->id, $foreigner->id],
        ])->assertStatus(422);

        $this->assertSame(0, ConversationThread::query()->where('kind', ConversationThread::KIND_GROUP)->count());
    }

    public function test_member_cannot_add_or_remove_participants(): void
    {
        [$company, $author, $alice, $bob] = $this->seedCompanyWithThreeEmployees();
        $thread = $this->createGroup($author, [$alice->id]);

        Sanctum::actingAs($alice);

        // Un simple membre ne peut ni ajouter…
        $this->postJson("/api/v1/conversations/{$thread->id}/participants", [
            'employee_id' => $bob->id,
        ])->assertNotFound();

        // …ni retirer le créateur.
        $this->deleteJson("/api/v1/conversations/{$thread->id}/participants/{$author->id}")
            ->assertNotFound();

        $this->assertSame(2, ConversationParticipant::query()->where('conversation_thread_id', $thread->id)->count());
    }

    public function test_creator_can_add_and_remove_a_member_but_never_remove_creator(): void
    {
        [$company, $author, $alice, $bob] = $this->seedCompanyWithThreeEmployees();
        $thread = $this->createGroup($author, [$alice->id]);

        Sanctum::actingAs($author);

        $this->postJson("/api/v1/conversations/{$thread->id}/participants", [
            'employee_id' => $bob->id,
        ])->assertCreated()
            ->assertJsonPath('data.role', ConversationParticipant::ROLE_MEMBER);

        $this->assertSame(3, ConversationParticipant::query()->where('conversation_thread_id', $thread->id)->count());

        // Re-ajout : idempotent (pas de doublon, pas de 500 sur l'unicite).
        // Valeur capturee avant l'assertion : un second `count()` inline
        // identique est vu comme deja narrowé par `assertSame`
        // (`@phpstan-assert =ExpectedType`, phpstan#15328).
        $this->postJson("/api/v1/conversations/{$thread->id}/participants", [
            'employee_id' => $bob->id,
        ])->assertCreated();

        $participantsAfterReAdd = ConversationParticipant::query()->where('conversation_thread_id', $thread->id)->count();
        $this->assertSame(3, $participantsAfterReAdd);

        // Retrait effectif.
        $this->deleteJson("/api/v1/conversations/{$thread->id}/participants/{$bob->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('conversation_participants', [
            'conversation_thread_id' => $thread->id,
            'employee_id' => $bob->id,
        ]);

        // Le créateur est indéboulonnable, même pour lui-même via DELETE.
        $this->deleteJson("/api/v1/conversations/{$thread->id}/participants/{$author->id}")
            ->assertStatus(422);
    }

    public function test_member_can_leave_and_then_loses_access(): void
    {
        [$company, $author, $alice] = $this->seedCompanyWithThreeEmployees();
        $thread = $this->createGroup($author, [$alice->id]);

        Sanctum::actingAs($alice);

        $this->postJson("/api/v1/conversations/{$thread->id}/leave")->assertNoContent();

        $this->assertDatabaseMissing('conversation_participants', [
            'conversation_thread_id' => $thread->id,
            'employee_id' => $alice->id,
        ]);

        // Après départ, le fil disparaît complètement de son périmètre.
        $this->getJson("/api/v1/conversations/{$thread->id}")->assertNotFound();
    }

    public function test_creator_cannot_leave_without_transferring_the_role(): void
    {
        [$company, $author, $alice] = $this->seedCompanyWithThreeEmployees();
        $thread = $this->createGroup($author, [$alice->id]);

        Sanctum::actingAs($author);

        $this->postJson("/api/v1/conversations/{$thread->id}/leave")->assertStatus(422);

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_thread_id' => $thread->id,
            'employee_id' => $author->id,
            'role' => ConversationParticipant::ROLE_CREATOR,
        ]);
    }

    public function test_non_participant_gets_404_on_every_group_endpoint(): void
    {
        [$company, $author, $alice, $bob] = $this->seedCompanyWithThreeEmployees();
        $thread = $this->createGroup($author, [$alice->id]);

        Sanctum::actingAs($bob);

        $this->getJson("/api/v1/conversations/{$thread->id}")->assertNotFound();
        $this->getJson("/api/v1/conversations/{$thread->id}/participants")->assertNotFound();
        $this->postJson("/api/v1/conversations/{$thread->id}/messages", ['body' => 'intrusion'])->assertNotFound();
        $this->postJson("/api/v1/conversations/{$thread->id}/participants", ['employee_id' => $bob->id])->assertNotFound();
        $this->postJson("/api/v1/conversations/{$thread->id}/leave")->assertNotFound();
    }

    public function test_group_thread_does_not_leak_into_another_tenant_listing(): void
    {
        [$company, $author, $alice] = $this->seedCompanyWithThreeEmployees();
        $thread = $this->createGroup($author, [$alice->id]);

        $otherCompany = Company::factory()->create();
        $outsider = Employee::factory()->create(['company_id' => $otherCompany->id]);

        Sanctum::actingAs($outsider);

        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_per_participant_read_state_is_tracked_independently(): void
    {
        [$company, $author, $alice, $bob] = $this->seedCompanyWithThreeEmployees();
        $thread = $this->createGroup($author, [$alice->id, $bob->id]);

        // Alice lit le fil : seul son curseur avance.
        Sanctum::actingAs($alice);
        $this->getJson("/api/v1/conversations/{$thread->id}")->assertOk();

        $aliceRow = ConversationParticipant::query()
            ->where('conversation_thread_id', $thread->id)
            ->where('employee_id', $alice->id)
            ->firstOrFail();

        $bobRow = ConversationParticipant::query()
            ->where('conversation_thread_id', $thread->id)
            ->where('employee_id', $bob->id)
            ->firstOrFail();

        $this->assertNotNull($aliceRow->last_read_message_id);
        $this->assertNull($bobRow->last_read_message_id);

        // Un nouveau message rend le fil non lu pour Bob, pas pour l'auteur.
        $thread->refresh();
        $thread->isUnreadFor($author);

        $this->assertTrue($thread->isUnreadFor($bob));
    }

    public function test_existing_direct_thread_contract_is_unchanged(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => $manager->id]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/conversations', [
            'title' => 'Question RH',
            'body' => 'Bonjour, une question sur mon contrat.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.kind', ConversationThread::KIND_DIRECT);

        $threadId = (int) $response->json('data.id');

        // Aucune ligne de participation pour un fil direct : le contrat
        // binaire historique reste la seule source d'appartenance.
        $this->assertSame(0, ConversationParticipant::query()->where('conversation_thread_id', $threadId)->count());

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/conversations/{$threadId}/messages", [
            'body' => 'Bonjour, je vous écoute.',
        ])->assertCreated();

        $this->assertSame(2, ConversationMessage::query()->where('conversation_thread_id', $threadId)->count());
    }

    /**
     * @return array{0: Company, 1: Employee, 2: Employee, 3: Employee}
     */
    private function seedCompanyWithThreeEmployees(): array
    {
        $company = Company::factory()->create();

        $author = Employee::factory()->create(['company_id' => $company->id]);
        $alice = Employee::factory()->create(['company_id' => $company->id]);
        $bob = Employee::factory()->create(['company_id' => $company->id]);

        return [$company, $author, $alice, $bob];
    }

    /**
     * @param  array<int, int>  $memberIds
     */
    private function createGroup(Employee $author, array $memberIds): ConversationThread
    {
        Sanctum::actingAs($author);

        $response = $this->postJson('/api/v1/conversations/groups', [
            'title' => 'Groupe de test',
            'body' => 'Message d\'ouverture.',
            'participant_ids' => $memberIds,
        ])->assertCreated();

        return ConversationThread::query()->findOrFail((int) $response->json('data.id'));
    }
}
