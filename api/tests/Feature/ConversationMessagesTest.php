<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Domain\Models\ConversationMessage;
use App\Modules\Notification\Domain\Models\ConversationThread;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #8747 [BOS-CHAT] — P2 : ordre total, anti-doublon, réponse, pagination par
 * curseur, édition et suppression.
 *
 * Ces tests portent sur des comportements observables par un client, pas sur
 * des détails d'implémentation : ils resteraient valides si l'allocation de
 * `seq` changeait de mécanisme.
 */
class ConversationMessagesTest extends TestCase
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

        $response = $this->postJson('/api/v1/conversations', [
            'title' => 'Suivi de dossier',
            'body' => 'Premier message.',
        ])->assertCreated();

        return [$company, $employee, $manager, (int) $response->json('data.id')];
    }

    public function test_seq_is_monotonic_within_the_thread(): void
    {
        [, $employee, , $threadId] = $this->seedThread();

        Sanctum::actingAs($employee);

        foreach (['Deuxieme.', 'Troisieme.', 'Quatrieme.'] as $body) {
            $this->postJson("/api/v1/conversations/{$threadId}/messages", ['body' => $body])
                ->assertCreated();
        }

        $seqs = ConversationMessage::query()
            ->where('conversation_thread_id', $threadId)
            ->orderBy('id')
            ->pluck('seq')
            ->map(static fn ($seq): int => (int) $seq)
            ->all();

        $this->assertSame([1, 2, 3, 4], $seqs);

        // Le curseur du fil suit l'allocation.
        $thread = ConversationThread::query()->findOrFail($threadId);
        $this->assertSame(4, $thread->lastSeq());
    }

    public function test_same_client_message_id_is_not_duplicated(): void
    {
        [, $employee, , $threadId] = $this->seedThread();

        Sanctum::actingAs($employee);

        $payload = [
            'body' => 'Message rejoue par le reseau.',
            'client_message_id' => 'a1b2c3d4-0000-4000-8000-000000000001',
        ];

        $first = $this->postJson("/api/v1/conversations/{$threadId}/messages", $payload)
            ->assertCreated();

        $second = $this->postJson("/api/v1/conversations/{$threadId}/messages", $payload)
            ->assertOk();

        // Même identifiant de message renvoyé : le client réconcilie son envoi.
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(
            $payload['client_message_id'],
            $second->json('data.client_message_id'),
        );

        // Un seul message portant cette clé, et un seul message en plus du seed.
        $this->assertSame(2, ConversationMessage::query()->where('conversation_thread_id', $threadId)->count());
        $this->assertSame(
            1,
            ConversationMessage::query()
                ->where('conversation_thread_id', $threadId)
                ->where('client_message_id', $payload['client_message_id'])
                ->count(),
        );
    }

    public function test_different_threads_do_not_share_client_message_ids(): void
    {
        [$company, $employee, $manager, $threadA] = $this->seedThread();

        // Second fil du même tenant : la même clé d'idempotence doit y être
        // acceptée, puisque l'unicité est PAR FIL.
        Sanctum::actingAs($manager);
        $threadB = (int) $this->postJson('/api/v1/conversations', [
            'title' => 'Autre dossier',
            'body' => 'Ouverture.',
            'recipient_id' => $employee->id,
        ])->assertCreated()->json('data.id');

        $key = 'shared-key-0001';

        $this->postJson("/api/v1/conversations/{$threadA}/messages", [
            'body' => 'Dans A',
            'client_message_id' => $key,
        ])->assertCreated();

        $this->postJson("/api/v1/conversations/{$threadB}/messages", [
            'body' => 'Dans B',
            'client_message_id' => $key,
        ])->assertCreated();

        $this->assertSame(1, ConversationMessage::query()->where('client_message_id', $key)->where('conversation_thread_id', $threadA)->count());
        $this->assertSame(1, ConversationMessage::query()->where('client_message_id', $key)->where('conversation_thread_id', $threadB)->count());
    }

    public function test_history_is_paginated_by_cursor_in_ascending_order(): void
    {
        [, $employee, , $threadId] = $this->seedThread();

        Sanctum::actingAs($employee);

        for ($i = 2; $i <= 10; $i++) {
            $this->postJson("/api/v1/conversations/{$threadId}/messages", ['body' => "Message {$i}."])
                ->assertCreated();
        }

        // Dernière page : les 3 derniers messages, en ordre croissant.
        $last = $this->getJson("/api/v1/conversations/{$threadId}/messages?per_page=3")
            ->assertOk();

        $this->assertSame([8, 9, 10], $this->seqsOf($last->json('data')));
        $this->assertTrue($last->json('meta.has_more'));
        $this->assertSame(8, $last->json('meta.next_before_seq'));
        $this->assertSame(10, $last->json('meta.last_seq'));

        // Page précédente : strictement avant le curseur, toujours croissante.
        $previous = $this->getJson("/api/v1/conversations/{$threadId}/messages?per_page=3&before_seq=8")
            ->assertOk();

        $this->assertSame([5, 6, 7], $this->seqsOf($previous->json('data')));

        // Première page : `has_more` faux quand on touche le début du fil.
        $first = $this->getJson("/api/v1/conversations/{$threadId}/messages?per_page=10&before_seq=5")
            ->assertOk();

        $this->assertFalse($first->json('meta.has_more'));
    }

    public function test_per_page_is_bounded_whatever_the_client_asks(): void
    {
        [, $employee, , $threadId] = $this->seedThread();

        Sanctum::actingAs($employee);

        // Une demande aberrante ne doit pas retourner le fil entier.
        $response = $this->getJson("/api/v1/conversations/{$threadId}/messages?per_page=100000")
            ->assertOk();

        $this->assertSame(100, $response->json('meta.per_page'));
    }

    public function test_catch_up_returns_only_messages_after_the_known_seq(): void
    {
        [, $employee, , $threadId] = $this->seedThread();

        Sanctum::actingAs($employee);

        // Le client a lu jusqu'à seq=1, puis a été déconnecté.
        $this->postJson("/api/v1/conversations/{$threadId}/messages", ['body' => 'Arrive 1.'])->assertCreated();
        $this->postJson("/api/v1/conversations/{$threadId}/messages", ['body' => 'Arrive 2.'])->assertCreated();

        $response = $this->getJson("/api/v1/conversations/{$threadId}/messages?after_seq=1")
            ->assertOk();

        $this->assertSame([2, 3], $this->seqsOf($response->json('data')));
        $this->assertTrue($response->json('meta.catch_up'));

        // Rejouer la même requête est idempotent côté lecture : rien de neuf.
        $again = $this->getJson("/api/v1/conversations/{$threadId}/messages?after_seq=3")
            ->assertOk();

        $this->assertSame([], $again->json('data'));
    }

    public function test_reply_must_target_a_message_of_the_same_thread(): void
    {
        [$company, $employee, $manager, $threadId] = $this->seedThread();

        Sanctum::actingAs($manager);
        $otherThreadId = (int) $this->postJson('/api/v1/conversations', [
            'title' => 'Autre fil',
            'body' => 'Ouverture.',
            'recipient_id' => $employee->id,
        ])->assertCreated()->json('data.id');

        $foreignMessage = ConversationMessage::query()
            ->where('conversation_thread_id', $otherThreadId)
            ->firstOrFail();

        Sanctum::actingAs($employee);

        $this->postJson("/api/v1/conversations/{$threadId}/messages", [
            'body' => 'Reponse croisee.',
            'reply_to_message_id' => $foreignMessage->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['reply_to_message_id']);

        // Une réponse valide dans le même fil est acceptée et prévisualisée.
        $ownMessage = ConversationMessage::query()->where('conversation_thread_id', $threadId)->firstOrFail();

        $reply = $this->postJson("/api/v1/conversations/{$threadId}/messages", [
            'body' => 'Reponse valide.',
            'reply_to_message_id' => $ownMessage->id,
        ])->assertCreated();

        $this->assertSame($ownMessage->id, $reply->json('data.reply_to.id'));
    }

    public function test_author_can_edit_their_own_message_only(): void
    {
        [, $employee, $manager, $threadId] = $this->seedThread();

        $message = ConversationMessage::query()->where('conversation_thread_id', $threadId)->firstOrFail();

        Sanctum::actingAs($employee);
        $edited = $this->patchJson("/api/v1/conversations/{$threadId}/messages/{$message->id}", [
            'body' => 'Premier message (corrige).',
        ])->assertOk();

        $this->assertSame('Premier message (corrige).', $edited->json('data.body'));
        $this->assertNotNull($edited->json('data.edited_at'));
        // L'édition ne crée pas de nouveau message.
        $this->assertSame(1, ConversationMessage::query()->where('conversation_thread_id', $threadId)->count());

        // Le manager est participant mais PAS l'auteur : il ne peut pas réécrire
        // les propos de quelqu'un d'autre.
        Sanctum::actingAs($manager);
        $this->patchJson("/api/v1/conversations/{$threadId}/messages/{$message->id}", [
            'body' => 'Reecriture par un tiers.',
        ])->assertNotFound();
    }

    public function test_deleted_message_keeps_its_seq_but_hides_its_content(): void
    {
        [, $employee, , $threadId] = $this->seedThread();

        $message = ConversationMessage::query()->where('conversation_thread_id', $threadId)->firstOrFail();
        $seq = (int) $message->seq;

        Sanctum::actingAs($employee);

        $this->deleteJson("/api/v1/conversations/{$threadId}/messages/{$message->id}")
            ->assertNoContent();

        $fresh = ConversationMessage::query()->findOrFail($message->id);
        $this->assertNotNull($fresh->deleted_at);
        $this->assertSame('', $fresh->body);
        // L'ordre est préservé : le trou de `seq` serait ambigu pour le client.
        $this->assertSame($seq, (int) $fresh->seq);

        $page = $this->getJson("/api/v1/conversations/{$threadId}/messages")->assertOk();
        $row = $this->rowWithId($page->json('data'), $message->id);

        $this->assertTrue($row['deleted']);
        $this->assertSame('', $row['body']);
        // La ligne reste dans la page, à sa place.
        $this->assertSame($seq, $row['seq']);

        // Un message supprimé n'est plus éditable.
        $this->patchJson("/api/v1/conversations/{$threadId}/messages/{$message->id}", [
            'body' => 'Tentative.',
        ])->assertUnprocessable();
    }

    public function test_deletion_is_idempotent(): void
    {
        [, $employee, , $threadId] = $this->seedThread();

        $message = ConversationMessage::query()->where('conversation_thread_id', $threadId)->firstOrFail();

        Sanctum::actingAs($employee);

        $this->deleteJson("/api/v1/conversations/{$threadId}/messages/{$message->id}")->assertNoContent();
        // Seconde suppression : même résultat, pas d'erreur 500 ni de double effet.
        $this->deleteJson("/api/v1/conversations/{$threadId}/messages/{$message->id}")->assertNoContent();
    }

    public function test_non_participant_gets_404_on_every_message_endpoint(): void
    {
        [$company, $employee, , $threadId] = $this->seedThread();

        $message = ConversationMessage::query()->where('conversation_thread_id', $threadId)->firstOrFail();

        $intruder = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => null]);
        Sanctum::actingAs($intruder);

        // 404 et non 403 : un 403 révélerait l'existence du fil à un tiers.
        $this->getJson("/api/v1/conversations/{$threadId}/messages")->assertNotFound();
        $this->patchJson("/api/v1/conversations/{$threadId}/messages/{$message->id}", ['body' => 'X'])->assertNotFound();
        $this->deleteJson("/api/v1/conversations/{$threadId}/messages/{$message->id}")->assertNotFound();
        $this->postJson("/api/v1/conversations/{$threadId}/messages", ['body' => 'X'])->assertNotFound();
    }

    public function test_thread_from_another_tenant_is_invisible(): void
    {
        [, , , $threadId] = $this->seedThread();

        $otherCompany = Company::factory()->create();
        $outsider = Employee::factory()->manager()->create(['company_id' => $otherCompany->id]);

        Sanctum::actingAs($outsider);

        $this->getJson("/api/v1/conversations/{$threadId}/messages")->assertNotFound();
        $this->postJson("/api/v1/conversations/{$threadId}/messages", ['body' => 'Fuite'])->assertNotFound();
    }

    public function test_message_without_body_nor_attachment_is_rejected(): void
    {
        [, $employee, , $threadId] = $this->seedThread();

        Sanctum::actingAs($employee);

        $this->postJson("/api/v1/conversations/{$threadId}/messages", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    public function test_attachment_only_message_is_accepted(): void
    {
        [, $employee, , $threadId] = $this->seedThread();

        Sanctum::actingAs($employee);

        $file = UploadedFile::fake()->createWithContent(
            'note.txt',
            "Contenu texte valide.\n",
        );

        $response = $this->postJson("/api/v1/conversations/{$threadId}/messages", [
            'attachment' => $file,
        ])->assertCreated();

        $this->assertSame('', $response->json('data.body'));
        $this->assertSame('note.txt', $response->json('data.attachment.original_name'));
        // #8748 — le type persisté est celui DÉTECTÉ, pas celui annoncé.
        $this->assertSame('text/plain', $response->json('data.attachment.mime_type'));
    }

    public function test_attachment_is_recorded_with_its_disk(): void
    {
        [, $employee, , $threadId] = $this->seedThread();

        Sanctum::actingAs($employee);

        $this->postJson("/api/v1/conversations/{$threadId}/messages", [
            'attachment' => UploadedFile::fake()->createWithContent('note.txt', "Texte.\n"),
        ])->assertCreated();

        $message = ConversationMessage::query()
            ->where('conversation_thread_id', $threadId)
            ->whereNotNull('attachment_path')
            ->firstOrFail();

        // Le disque est persisté : une bascule de configuration ne rend pas la
        // pièce jointe illisible rétroactivement.
        $this->assertSame(config('conversations.attachments.disk'), $message->attachment_disk);
        $this->assertSame($message->attachment_disk, $message->attachmentDisk());
    }

    /**
     * Extrait les `seq` d'une page JSON.
     *
     * Typé explicitement (`array<int, int>`) plutôt que `collect()->pluck()` :
     * PHPStan level 8 ne peut pas inférer les types de gabarit depuis un
     * `mixed` venu de `json()`, et le check requis analyse aussi `tests/`.
     *
     * @return list<int>
     */
    private function seqsOf(mixed $rows): array
    {
        $this->assertIsArray($rows);

        $seqs = [];

        foreach ($rows as $row) {
            $this->assertIsArray($row);
            $this->assertArrayHasKey('seq', $row);
            $seqs[] = (int) $row['seq'];
        }

        return $seqs;
    }

    /**
     * Ligne de la page dont l'`id` correspond, ou échec explicite.
     *
     * @return array<string, mixed>
     */
    private function rowWithId(mixed $rows, int $id): array
    {
        $this->assertIsArray($rows);

        foreach ($rows as $row) {
            $this->assertIsArray($row);

            if (isset($row['id']) && (int) $row['id'] === $id) {
                return $row;
            }
        }

        $this->fail("Le message {$id} est absent de la page.");
    }
}
