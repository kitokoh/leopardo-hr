<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Domain\Models\ConversationMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * R1 / #8754 [BOS-CHAT] — la pièce jointe survit à un changement de disque.
 *
 * **Le risque R1 de l'audit** : sur Render, le disque `local` vit dans le
 * conteneur et disparaît à chaque redéploiement. La parade livrée en #8748 est
 * de persister **le nom du disque sur chaque message** (`attachment_disk`),
 * afin qu'un changement de configuration ne rende pas les pièces déjà
 * stockées illisibles.
 *
 * ⚠️ Ce que ces tests prouvent, et ce qu'ils ne prouvent PAS :
 *
 * - ils prouvent que la **résolution du disque** est correcte : un message
 *   écrit sur le disque A reste servable après que la configuration pointe
 *   vers B. C'est exactement le scénario « on active le disque persistant » ;
 * - ils ne prouvent **pas** que le disque configuré en production est
 *   persistant. Aucun test ne peut le prouver depuis le code : cela dépend de
 *   l'infrastructure (volume monté ou bucket S3). La procédure d'activation
 *   est décrite dans `docs/mission-business-os/17_chat_attachments_runbook.md`.
 */
class ConversationAttachmentPersistenceTest extends TestCase
{
    use CreatesMvpSchema;

    /** Disque éphémère par défaut du dépôt. */
    private const DISK_EPHEMERAL = 'local';

    /** Disque « persistant » simulé (représente `private` sur volume ou `s3`). */
    private const DISK_PERSISTENT = 'chat_persistent_test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        // Deux disques indépendants : c'est cette séparation qui rend le test
        // probant. Si le code relisait le disque depuis `config()` au moment du
        // téléchargement, le fichier serait introuvable sur le disque B.
        Storage::fake(self::DISK_EPHEMERAL);
        Storage::fake(self::DISK_PERSISTENT);

        config(['conversations.attachments.disk' => self::DISK_EPHEMERAL]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /**
     * @return array{0: Employee, 1: int}
     */
    private function seedThread(): array
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'manager_id' => $manager->id,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/conversations', [
            'title' => 'Dossier avec pièce jointe',
            'body' => 'Voir le document joint.',
        ])->assertCreated();

        return [$employee, (int) $response->json('data.id')];
    }

    /**
     * Scénario central : envoi sur le disque A, bascule de configuration vers
     * le disque B, puis téléchargement. Le fichier DOIT rester accessible.
     */
    public function test_attachment_remains_downloadable_after_the_configured_disk_is_switched(): void
    {
        [, $threadId] = $this->seedThread();

        $response = $this->postJson("/api/v1/conversations/{$threadId}/messages", [
            'body' => 'Le document en pièce jointe.',
            'attachment' => UploadedFile::fake()->createWithContent(
                'contrat.txt',
                "Contenu du contrat.\n",
            ),
        ])->assertCreated();

        $messageId = (int) $response->json('data.id');

        $message = ConversationMessage::query()->findOrFail($messageId);

        // Pré-conditions : la pièce est bien écrite sur le disque A, et son
        // nom est persisté sur le message.
        $this->assertSame(self::DISK_EPHEMERAL, $message->attachment_disk);
        $this->assertNotNull($message->attachment_path);
        Storage::disk(self::DISK_EPHEMERAL)->assertExists((string) $message->attachment_path);

        // Bascule d'exploitation : on active (enfin) un disque persistant.
        config(['conversations.attachments.disk' => self::DISK_PERSISTENT]);

        // Le fichier n'existe PAS sur le nouveau disque : c'est tout l'enjeu.
        Storage::disk(self::DISK_PERSISTENT)->assertMissing((string) $message->attachment_path);

        $download = $this->get("/api/v1/conversations/{$threadId}/messages/{$messageId}/attachment");

        $download->assertOk();
        $this->assertSame(
            "Contenu du contrat.\n",
            (string) $download->streamedContent(),
            'La pièce jointe doit être servie depuis le disque enregistré sur le message, pas depuis la configuration courante.',
        );
    }

    /**
     * Le corollaire : les NOUVELLES pièces partent bien sur le disque
     * nouvellement configuré. Sans ce test, on pourrait « corriger » le premier
     * cas en ignorant simplement la configuration.
     */
    public function test_new_attachments_follow_the_newly_configured_disk(): void
    {
        [, $threadId] = $this->seedThread();

        config(['conversations.attachments.disk' => self::DISK_PERSISTENT]);

        $response = $this->postJson("/api/v1/conversations/{$threadId}/messages", [
            'body' => 'Nouvelle pièce après bascule.',
            'attachment' => UploadedFile::fake()->createWithContent(
                'apres-bascule.txt',
                "Écrit après la bascule.\n",
            ),
        ])->assertCreated();

        $message = ConversationMessage::query()->findOrFail((int) $response->json('data.id'));

        $this->assertSame(self::DISK_PERSISTENT, $message->attachment_disk);
        Storage::disk(self::DISK_PERSISTENT)->assertExists((string) $message->attachment_path);
        Storage::disk(self::DISK_EPHEMERAL)->assertMissing((string) $message->attachment_path);
    }

    /**
     * Fichier réellement perdu (redéploiement sans disque persistant) : le
     * serveur doit répondre **410 Gone**, pas 500. Le message existe, le
     * fichier non — un 404 laisserait croire que le message a disparu.
     */
    public function test_missing_file_returns_410_not_500(): void
    {
        [, $threadId] = $this->seedThread();

        $response = $this->postJson("/api/v1/conversations/{$threadId}/messages", [
            'body' => 'Pièce qui va être perdue.',
            'attachment' => UploadedFile::fake()->createWithContent('perdu.txt', "Perdu.\n"),
        ])->assertCreated();

        $message = ConversationMessage::query()->findOrFail((int) $response->json('data.id'));

        // Simule exactement ce que fait un redéploiement : le conteneur est
        // reconstruit, le fichier n'est plus là, la ligne en base subsiste.
        Storage::disk((string) $message->attachment_disk)->delete((string) $message->attachment_path);

        $this->get("/api/v1/conversations/{$threadId}/messages/{$message->id}/attachment")
            ->assertStatus(410);
    }

    /**
     * Le contrôle d'accès est rejoué à CHAQUE téléchargement : un tiers ne
     * récupère pas la pièce, même en connaissant les identifiants. 404 et non
     * 403 — un 403 confirmerait l'existence du fil.
     */
    public function test_a_non_participant_cannot_download_the_attachment(): void
    {
        [, $threadId] = $this->seedThread();

        $response = $this->postJson("/api/v1/conversations/{$threadId}/messages", [
            'body' => 'Document confidentiel.',
            'attachment' => UploadedFile::fake()->createWithContent('confidentiel.txt', "Secret.\n"),
        ])->assertCreated();

        $messageId = (int) $response->json('data.id');

        // Un employé du MÊME tenant mais étranger au fil.
        $outsider = Employee::factory()->create([
            'company_id' => Company::query()->value('id'),
        ]);

        Sanctum::actingAs($outsider);

        $this->get("/api/v1/conversations/{$threadId}/messages/{$messageId}/attachment")
            ->assertNotFound();
    }
}
