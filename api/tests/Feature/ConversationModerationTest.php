<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Domain\Models\ConversationBlock;
use App\Modules\Notification\Domain\Models\ConversationMessage;
use App\Modules\Notification\Domain\Models\ConversationReport;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #8750 [BOS-CHAT] — P5 : modération de base.
 *
 * Chaque test vérifie un comportement OBSERVABLE (code HTTP, effet en base),
 * et les tests de sécurité vérifient surtout ce qui est REFUSÉ.
 */
class ConversationModerationTest extends TestCase
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

    /**
     * @return array{0: Company, 1: Employee, 2: Employee, 3: int}
     */
    private function seedThread(): array
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => $manager->id]);

        Sanctum::actingAs($employee);

        $threadId = (int) $this->postJson('/api/v1/conversations', [
            'title' => 'Suivi',
            'body' => 'Premier message.',
        ])->assertCreated()->json('data.id');

        return [$company, $employee, $manager, $threadId];
    }

    public function test_employee_can_block_and_unblock_another_employee(): void
    {
        [$company, $employee] = $this->seedThread();

        $colleague = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => null]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/conversations/blocks', [
            'employee_id' => $colleague->id,
            'reason' => 'Propos repetes',
        ])->assertCreated();

        $this->assertDatabaseHas($this->moduleTable('conversation_blocks'), [
            'blocker_id' => $employee->id,
            'blocked_id' => $colleague->id,
        ]);

        $list = $this->getJson('/api/v1/conversations/blocks')->assertOk();
        $this->assertSame([$colleague->id], collect($list->json('data'))->pluck('employee_id')->all());

        $this->deleteJson("/api/v1/conversations/blocks/{$colleague->id}")->assertNoContent();
        $this->assertSame(0, ConversationBlock::query()->count());
    }

    public function test_blocking_is_idempotent(): void
    {
        [$company, $employee] = $this->seedThread();
        $colleague = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => null]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/conversations/blocks', ['employee_id' => $colleague->id])->assertCreated();
        // Re-bloquer : pas d'erreur, pas de doublon (l'unicité est en base).
        $this->postJson('/api/v1/conversations/blocks', ['employee_id' => $colleague->id])->assertCreated();

        $this->assertSame(1, ConversationBlock::query()->count());
    }

    public function test_employee_cannot_block_themselves(): void
    {
        [, $employee] = $this->seedThread();

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/conversations/blocks', ['employee_id' => $employee->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);
    }

    public function test_blocking_an_employee_of_another_tenant_is_refused(): void
    {
        [, $employee] = $this->seedThread();

        $otherCompany = Company::factory()->create();
        $foreigner = Employee::factory()->create(['company_id' => $otherCompany->id, 'manager_id' => null]);

        Sanctum::actingAs($employee);

        // Aucun blocage cross-tenant ne doit pouvoir être enregistré : la
        // résolution de la cible est tenant-scopée.
        $this->postJson('/api/v1/conversations/blocks', ['employee_id' => $foreigner->id])
            ->assertUnprocessable();

        $this->assertSame(0, ConversationBlock::query()->count());
    }

    public function test_block_prevents_new_direct_thread_in_both_directions(): void
    {
        $company = Company::factory()->create();
        $alice = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $bob = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($alice);
        $this->postJson('/api/v1/conversations/blocks', ['employee_id' => $bob->id])->assertCreated();

        // Le bloqueur ne peut plus ouvrir de fil vers la personne bloquée.
        $this->postJson('/api/v1/conversations', [
            'title' => 'Nouveau sujet',
            'body' => 'Bonjour.',
            'recipient_id' => $bob->id,
        ])->assertUnprocessable();

        // La personne bloquée non plus : le blocage est bilatéral dans son
        // effet, même s'il n'est écrit que dans un sens.
        Sanctum::actingAs($bob);
        $this->postJson('/api/v1/conversations', [
            'title' => 'Nouveau sujet',
            'body' => 'Bonjour.',
            'recipient_id' => $alice->id,
        ])->assertUnprocessable();

        // Le blocage ne ferme QUE ce couple : un tiers reste joignable.
        $carol = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($alice);
        $this->postJson('/api/v1/conversations', [
            'title' => 'Avec Carol',
            'body' => 'Bonjour Carol.',
            'recipient_id' => $carol->id,
        ])->assertCreated();
    }

    public function test_blocked_employee_cannot_be_added_to_a_group(): void
    {
        [$company, $employee] = $this->seedThread();

        $colleague = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => null]);
        $third = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => null]);

        Sanctum::actingAs($employee);
        $this->postJson('/api/v1/conversations/blocks', ['employee_id' => $colleague->id])->assertCreated();

        $groupId = (int) $this->postJson('/api/v1/conversations/groups', [
            'title' => 'Projet',
            'body' => 'Demarrage.',
            'participant_ids' => [$third->id],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/conversations/{$groupId}/participants", [
            'employee_id' => $colleague->id,
        ])->assertUnprocessable();
    }

    public function test_participant_can_report_a_message_and_author_is_recorded(): void
    {
        [$company, $employee, $manager, $threadId] = $this->seedThread();

        $message = ConversationMessage::query()->where('conversation_thread_id', $threadId)->firstOrFail();

        // Le manager est participant du fil : il peut signaler.
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/conversations/{$threadId}/reports", [
            'message_id' => $message->id,
            'reason' => 'inappropriate_content',
            'details' => 'Ton condescendant.',
        ])->assertCreated();

        $this->assertSame(ConversationReport::STATUS_OPEN, $response->json('data.status'));
        $this->assertSame($message->author_id, $response->json('data.reported_employee_id'));
        // Le corps du message n'est PAS renvoyé : la modération ne duplique pas
        // le contenu dans une seconde surface.
        $this->assertArrayNotHasKey('body', $response->json('data'));
    }

    public function test_unknown_reason_is_rejected(): void
    {
        [, , $manager, $threadId] = $this->seedThread();
        $message = ConversationMessage::query()->where('conversation_thread_id', $threadId)->firstOrFail();

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/conversations/{$threadId}/reports", [
            'message_id' => $message->id,
            'reason' => 'je_n_aime_pas',
        ])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
    }

    public function test_report_on_a_message_of_another_thread_is_rejected(): void
    {
        [$company, $employee, $manager, $threadId] = $this->seedThread();

        Sanctum::actingAs($manager);
        $otherThreadId = (int) $this->postJson('/api/v1/conversations', [
            'title' => 'Autre',
            'body' => 'Ouverture.',
            'recipient_id' => $employee->id,
        ])->assertCreated()->json('data.id');

        $foreign = ConversationMessage::query()->where('conversation_thread_id', $otherThreadId)->firstOrFail();

        // Signaler depuis un fil auquel on appartient un message d'un AUTRE fil
        // est rejeté : sinon un signalement servirait à sonder son existence.
        $this->postJson("/api/v1/conversations/{$threadId}/reports", [
            'message_id' => $foreign->id,
            'reason' => 'spam',
        ])->assertUnprocessable()->assertJsonValidationErrors(['message_id']);
    }

    public function test_non_participant_cannot_report_in_a_thread(): void
    {
        [$company, , , $threadId] = $this->seedThread();
        $message = ConversationMessage::query()->where('conversation_thread_id', $threadId)->firstOrFail();

        $intruder = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => null]);
        Sanctum::actingAs($intruder);

        $this->postJson("/api/v1/conversations/{$threadId}/reports", [
            'message_id' => $message->id,
            'reason' => 'spam',
        ])->assertNotFound();
    }

    public function test_account_report_can_target_an_employee(): void
    {
        [$company, $employee] = $this->seedThread();
        $colleague = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => null]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/conversations/reports', [
            'employee_id' => $colleague->id,
            'reason' => 'harassment',
            'details' => 'Messages insistants hors application.',
        ])->assertCreated();

        $this->assertDatabaseHas($this->moduleTable('conversation_reports'), [
            'reporter_id' => $employee->id,
            'reported_employee_id' => $colleague->id,
            'status' => ConversationReport::STATUS_OPEN,
        ]);
    }

    public function test_employee_sees_only_their_own_reports(): void
    {
        [$company, $employee] = $this->seedThread();
        $colleague = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => null]);
        $other = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => null]);

        Sanctum::actingAs($employee);
        $this->postJson('/api/v1/conversations/reports', [
            'employee_id' => $colleague->id,
            'reason' => 'spam',
        ])->assertCreated();

        Sanctum::actingAs($other);
        $mine = $this->getJson('/api/v1/conversations/reports')->assertOk();

        // Un employé ne voit jamais les signalements d'un autre.
        $this->assertSame([], $mine->json('data'));
    }

    public function test_only_manager_can_read_the_moderation_queue(): void
    {
        [$company, $employee, $manager] = $this->seedThread();
        $colleague = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => null]);

        Sanctum::actingAs($employee);
        $this->postJson('/api/v1/conversations/reports', [
            'employee_id' => $colleague->id,
            'reason' => 'threat',
        ])->assertCreated();

        // Employé non manager : refusé.
        $this->getJson('/api/v1/conversations/moderation/reports')->assertUnprocessable();

        // Manager du tenant : autorisé.
        Sanctum::actingAs($manager);
        $queue = $this->getJson('/api/v1/conversations/moderation/reports')->assertOk();

        $this->assertCount(1, $queue->json('data'));
        // Le signalant est identifié pour le modérateur, le contenu ne l'est pas.
        $this->assertSame($employee->id, $queue->json('data.0.reporter.id'));
    }

    public function test_moderation_queue_is_tenant_scoped(): void
    {
        [$company, $employee] = $this->seedThread();
        $colleague = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => null]);

        Sanctum::actingAs($employee);
        $this->postJson('/api/v1/conversations/reports', [
            'employee_id' => $colleague->id,
            'reason' => 'spam',
        ])->assertCreated();

        // Un manager d'un autre tenant ne voit RIEN de ce qui précède.
        $otherCompany = Company::factory()->create();
        $foreignManager = Employee::factory()->manager()->create(['company_id' => $otherCompany->id]);

        Sanctum::actingAs($foreignManager);
        $queue = $this->getJson('/api/v1/conversations/moderation/reports')->assertOk();

        $this->assertSame([], $queue->json('data'));
    }

    public function test_manager_can_resolve_a_report_and_resolution_is_idempotent(): void
    {
        [$company, $employee, $manager] = $this->seedThread();
        $colleague = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => null]);

        Sanctum::actingAs($employee);
        $reportId = (int) $this->postJson('/api/v1/conversations/reports', [
            'employee_id' => $colleague->id,
            'reason' => 'harassment',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($manager);

        $resolved = $this->postJson("/api/v1/conversations/moderation/reports/{$reportId}/resolve", [
            'status' => 'reviewed',
            'resolution' => 'Entretien realise.',
        ])->assertOk();

        $this->assertSame('reviewed', $resolved->json('data.status'));
        $this->assertNotNull($resolved->json('data.reviewed_at'));

        // Seconde clôture : même état renvoyé, première décision préservée.
        $again = $this->postJson("/api/v1/conversations/moderation/reports/{$reportId}/resolve", [
            'status' => 'dismissed',
            'resolution' => 'Ecrasee ?',
        ])->assertOk();

        $this->assertSame('reviewed', $again->json('data.status'));
        $this->assertSame('Entretien realise.', $again->json('data.resolution'));
    }

    public function test_non_manager_cannot_resolve_a_report(): void
    {
        [$company, $employee] = $this->seedThread();
        $colleague = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => null]);

        Sanctum::actingAs($employee);
        $reportId = (int) $this->postJson('/api/v1/conversations/reports', [
            'employee_id' => $colleague->id,
            'reason' => 'spam',
        ])->assertCreated()->json('data.id');

        // Même l'auteur du signalement ne peut pas décider de sa clôture.
        $this->postJson("/api/v1/conversations/moderation/reports/{$reportId}/resolve", [
            'status' => 'dismissed',
        ])->assertUnprocessable();

        $this->assertSame(
            ConversationReport::STATUS_OPEN,
            ConversationReport::query()->findOrFail($reportId)->status,
        );
    }

    public function test_security_events_are_logged_without_message_content(): void
    {
        [, , $manager, $threadId] = $this->seedThread();
        $message = ConversationMessage::query()->where('conversation_thread_id', $threadId)->firstOrFail();

        // On écoute l'événement de log plutôt que de mocker le facade : un
        // `Log::spy()` global remplacerait AUSSI les canaux des autres services
        // (`Log::channel(...)` renverrait null) et casserait la requête testée.
        $captured = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$captured): void {
            $captured[] = ['message' => $event->message, 'context' => $event->context];
        });

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/conversations/{$threadId}/reports", [
            'message_id' => $message->id,
            'reason' => 'spam',
        ])->assertCreated();

        $entry = collect($captured)->firstWhere('message', 'chat.message_reported');

        $this->assertNotNull($entry, 'Le signalement doit produire un événement de sécurité.');
        $this->assertSame($message->id, $entry['context']['message_id']);
        // Le corps du message n'est JAMAIS journalisé : un log est lisible par
        // plus de monde qu'une table tenant.
        $this->assertArrayNotHasKey('body', $entry['context']);
        $this->assertArrayNotHasKey('details', $entry['context']);
    }

    public function test_send_endpoint_is_rate_limited(): void
    {
        [, $employee, , $threadId] = $this->seedThread();

        Sanctum::actingAs($employee);

        // Quota de test volontairement bas pour prouver que le limiteur est
        // réellement branché sur la route (et non simplement configuré).
        config(['security.rate_limits.chat_send_per_minute' => 3]);
        config(['security.rate_limits.chat_send_thread_per_minute' => 3]);

        // En test, le cache de rate limiting est propre à chaque exécution :
        // on purge pour isoler ce test des précédents.
        app('cache')->flush();

        $statuses = [];

        for ($i = 0; $i < 5; $i++) {
            $statuses[] = $this->postJson("/api/v1/conversations/{$threadId}/messages", [
                'body' => "Message {$i}.",
            ])->getStatusCode();
        }

        $this->assertContains(201, $statuses);
        $this->assertContains(429, $statuses, 'Le limiteur d\'envoi doit renvoyer 429 au-delà du quota.');
    }
}
