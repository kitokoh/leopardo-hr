<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceCorrectionRequest;
use App\Modules\Notification\Application\DTOs\CreateGroupThreadDTO;
use App\Modules\Notification\Application\DTOs\CreateThreadDTO;
use App\Modules\Notification\Application\DTOs\MessageCursor;
use App\Modules\Notification\Domain\Exceptions\ConversationThreadNotFoundException;
use App\Modules\Notification\Domain\Models\ConversationMessage;
use App\Modules\Notification\Domain\Models\ConversationParticipant;
use App\Modules\Notification\Domain\Models\ConversationThread;
use App\Modules\Notification\Domain\Policies\ConversationParticipantPolicy;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use App\Modules\Planning\Domain\Models\Absence;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;

/**
 * PA2-COMM-002 — Employee/manager discussion threads.
 *
 * Owns the full lifecycle of a {@see ConversationThread}: creation (with
 * automatic manager resolution and, optionally, anchoring to a concrete
 * payroll/attendance/absence subject the two parties are discussing),
 * posting messages with at most one small attachment, and tenant + RBAC
 * scoped listing.
 */
class ConversationService
{
    /** @var array<string, class-string> Allowed subject types and their model class. */
    private const SUBJECT_MODELS = [
        'salary_advance' => SalaryAdvance::class,
        'attendance_correction' => AttendanceCorrectionRequest::class,
        'absence' => Absence::class,
    ];

    /** Max attachment size in kilobytes, matching the "pieces jointes limitees" acceptance criterion. */
    private const MAX_ATTACHMENT_KB = 5120;

    /**
     * #8748 [BOS-CHAT] — types MIME **réels** acceptés pour une pièce jointe.
     *
     * La whitelist porte sur le type détecté par `finfo` sur le fichier
     * stocké, jamais sur `$attachment->getMimeType()` (déclaré par le
     * navigateur). Un `.pdf` qui contient un exécutable échoue donc ici.
     *
     * @var list<string>
     */
    private const ALLOWED_ATTACHMENT_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/heic',
        'text/plain',
        'text/csv',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    /** Maximum number of members in a group thread, attachments and notifications inclusive. */
    private const MAX_GROUP_PARTICIPANTS = 50;

    /** #8747 — taille maximale d'une page de messages. */
    private const MAX_MESSAGE_PAGE_SIZE = 100;

    public function __construct(
        private readonly CommunicationService $communicationService,
        private readonly ConversationParticipantPolicy $participantPolicy = new ConversationParticipantPolicy,
        private readonly ConversationModerationService $moderationService = new ConversationModerationService,
    ) {}

    /**
     * Creates a new thread initiated by $author. When $author is an
     * employee, the thread's manager is resolved from their reporting
     * line; when $author is a manager creating a thread with one of their
     * reports, $dto->recipientId identifies that report.
     */
    public function createThread(Employee $author, CreateThreadDTO $dto, ?UploadedFile $attachment = null): ConversationThread
    {
        [$employee, $manager] = $this->resolveParticipants($author, $dto);

        if ($dto->subjectType !== null) {
            $this->assertSubjectBelongsToEmployee($dto->subjectType, $dto->subjectId, $employee);
        }

        $thread = ConversationThread::query()->create([
            'company_id' => $author->company_id,
            'employee_id' => $employee->id,
            'manager_id' => $manager?->id,
            'subject_type' => $dto->subjectType,
            'subject_id' => $dto->subjectId,
            'title' => $dto->title,
            'status' => 'open',
        ]);

        $this->postMessage($thread, $author, $dto->body, $attachment);

        return $thread->fresh(['employee', 'manager']);
    }

    /**
     * Appends a message (with at most one optional attachment) to the
     * thread. Updates the thread's `last_message_*` bookkeeping and
     * notifies the other participant.
     *
     * #8747 — délègue à {@see self::postMessageDeduplicated()} sans clé
     * d'idempotence : les appelants historiques (`createThread`,
     * `createGroup`) gardent exactement le même contrat.
     */
    public function postMessage(ConversationThread $thread, Employee $author, string $body, ?UploadedFile $attachment): ConversationMessage
    {
        [$message] = $this->postMessageDeduplicated($thread, $author, $body, $attachment);

        return $message;
    }

    /**
     * #8747 [BOS-CHAT] — écriture idempotente d'un message.
     *
     * Trois garanties, toutes côté serveur :
     *
     *  1. **Ordre** — `seq` est alloué par un compteur atomique porté par le
     *     fil (`UPDATE ... SET last_message_seq = last_message_seq + 1
     *     RETURNING last_message_seq`). Deux écritures concurrentes
     *     obtiennent deux valeurs distinctes et croissantes, sans verrou
     *     applicatif ni `max() + 1` sujet à une course.
     *  2. **Anti-doublon** — `client_message_id` est unique par fil (index
     *     partiel). Un renvoi produit par un retry réseau renvoie le message
     *     DÉJÀ écrit (`$created = false`) au lieu d'en créer un second ; en cas
     *     de course entre deux requêtes simultanées, la violation d'unicité est
     *     rattrapée et la ligne gagnante est relue.
     *  3. **Réponse** — `reply_to_message_id` doit désigner un message du MÊME
     *     fil : un identifiant d'un autre fil (ou d'un autre tenant) est
     *     rejeté en 422, jamais rattaché silencieusement.
     *
     * @return array{0: ConversationMessage, 1: bool} Le message et `true` s'il vient d'être créé.
     */
    public function postMessageDeduplicated(
        ConversationThread $thread,
        Employee $author,
        string $body,
        ?UploadedFile $attachment,
        ?string $clientMessageId = null,
        ?int $replyToMessageId = null,
    ): array {
        if ($clientMessageId !== null) {
            $existing = ConversationMessage::query()
                ->where('conversation_thread_id', $thread->id)
                ->where('client_message_id', $clientMessageId)
                ->first();

            if ($existing instanceof ConversationMessage) {
                // Rejeu : même réponse qu'à l'origine, sans second message et
                // sans seconde notification.
                return [$existing, false];
            }
        }

        if ($replyToMessageId !== null) {
            $parentExists = ConversationMessage::query()
                ->where('conversation_thread_id', $thread->id)
                ->whereKey($replyToMessageId)
                ->exists();

            if (! $parentExists) {
                throw ValidationException::withMessages([
                    'reply_to_message_id' => ['Le message auquel vous répondez n\'appartient pas à cette conversation.'],
                ]);
            }
        }

        [$attachmentPath, $attachmentOriginalName, $attachmentMimeType, $attachmentSize, $attachmentDisk]
            = $this->storeAttachment($thread, $attachment);

        try {
            $message = DB::transaction(function () use (
                $thread,
                $author,
                $body,
                $attachmentPath,
                $attachmentOriginalName,
                $attachmentMimeType,
                $attachmentSize,
                $attachmentDisk,
                $clientMessageId,
                $replyToMessageId,
            ): ConversationMessage {
                $seq = $this->allocateSeq($thread);

                $message = ConversationMessage::query()->create([
                    'company_id' => $thread->company_id,
                    'conversation_thread_id' => $thread->id,
                    'author_id' => $author->id,
                    'body' => $body,
                    'seq' => $seq,
                    'client_message_id' => $clientMessageId,
                    'reply_to_message_id' => $replyToMessageId,
                    'attachment_path' => $attachmentPath,
                    'attachment_original_name' => $attachmentOriginalName,
                    'attachment_mime_type' => $attachmentMimeType,
                    'attachment_size' => $attachmentSize,
                    'attachment_disk' => $attachmentDisk,
                ]);

                $thread->forceFill([
                    'last_message_id' => $message->id,
                    'last_message_seq' => $seq,
                    'last_message_at' => $message->created_at,
                ]);
                $thread->markReadFor($author);
                $thread->save();

                return $message;
            });
        } catch (QueryException $exception) {
            // Course entre deux requêtes portant la même clé d'idempotence :
            // l'autre transaction a gagné, on renvoie SA ligne.
            if ($clientMessageId !== null && $this->isUniqueViolation($exception)) {
                /** @var ConversationMessage|null $winner */
                $winner = ConversationMessage::query()
                    ->where('conversation_thread_id', $thread->id)
                    ->where('client_message_id', $clientMessageId)
                    ->first();

                if ($winner instanceof ConversationMessage) {
                    return [$winner, false];
                }
            }

            throw $exception;
        }

        $this->notifyOtherParticipant($thread, $author, $message);

        return [$message, true];
    }

    /**
     * #8747 — alloue le prochain `seq` du fil de façon atomique.
     *
     * Le compteur vit sur `conversation_threads.last_message_seq` : un seul
     * verrou de ligne, une seule valeur retournée, aucune lecture suivie d'une
     * écriture. Le `RETURNING` est spécifique à PostgreSQL, déjà imposé par la
     * stack (`DB_CONNECTION=pgsql`).
     */
    private function allocateSeq(ConversationThread $thread): int
    {
        /** @var object{last_message_seq: int|string}|null $row */
        $row = DB::selectOne(
            'UPDATE conversation_threads SET last_message_seq = last_message_seq + 1 WHERE id = ? RETURNING last_message_seq',
            [$thread->id],
        );

        if ($row === null) {
            throw new ConversationThreadNotFoundException($thread->id);
        }

        return (int) $row->last_message_seq;
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        // 23505 = unique_violation (SQLSTATE PostgreSQL).
        return $exception->getCode() === '23505'
            || str_contains($exception->getMessage(), 'conversation_messages_client_id_unique');
    }

    /**
     * #8748 [BOS-CHAT] — stocke une pièce jointe et retourne ses métadonnées.
     *
     * Le type MIME enregistré est celui **détecté sur le fichier** (`finfo`),
     * pas celui déclaré par le client : un `.pdf` contenant un exécutable est
     * rejeté ici, quelle que soit l'extension envoyée. Le nom du disque utilisé
     * est persisté, ce qui rend lisible une pièce jointe écrite hier sur
     * `local` même après bascule de `CHAT_ATTACHMENT_DISK` vers `s3`.
     *
     * @return array{0: string|null, 1: string|null, 2: string|null, 3: int|null, 4: string|null}
     */
    private function storeAttachment(ConversationThread $thread, ?UploadedFile $attachment): array
    {
        if ($attachment === null) {
            return [null, null, null, null, null];
        }

        $this->ensureAttachmentAllowed($attachment);

        $disk = $this->attachmentDiskName();

        // #8748 — le type est détecté sur le fichier TEMPORAIRE, avant
        // l'écriture : `Storage::disk(...)->path()` n'existe pas pour un disque
        // distant (S3), donc on ne peut pas dépendre du chemin après stockage.
        $detectedMime = $this->detectMimeType($this->localPathOf($attachment));

        $path = $attachment->store(
            sprintf('conversations/%s/%d', $thread->company_id, $thread->id),
            $disk,
        );

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'attachment' => ['La pièce jointe n\'a pas pu être stockée.'],
            ]);
        }

        return [
            $path,
            $attachment->getClientOriginalName(),
            $detectedMime,
            (int) $attachment->getSize(),
            $disk,
        ];
    }

    /**
     * Chemin local réel du fichier téléversé (le temporaire PHP existe toujours,
     * que le disque de destination soit local ou distant).
     */
    private function localPathOf(UploadedFile $attachment): string
    {
        $real = $attachment->getRealPath();

        return is_string($real) && $real !== '' ? $real : $attachment->getPathname();
    }

    /**
     * #8748 — disque configuré pour les pièces jointes du chat.
     */
    public function attachmentDiskName(): string
    {
        $configured = config('conversations.attachments.disk');

        return is_string($configured) && $configured !== '' ? $configured : 'local';
    }

    /**
     * #8748 — type MIME réel du fichier stocké (finfo), indépendant du client.
     */
    private function detectMimeType(string $absolutePath): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($absolutePath);

        return is_string($detected) && $detected !== '' ? $detected : 'application/octet-stream';
    }

    /**
     * Lists threads visible to $actor: an employee sees their own threads;
     * a manager sees the threads where they are the assigned manager.
     * Always tenant-scoped.
     *
     * @return LengthAwarePaginator<int, ConversationThread>
     */
    public function threadsFor(Employee $actor, int $perPage = 20)
    {
        $query = ConversationThread::query()
            ->where('company_id', $actor->company_id)
            ->with(['employee:id,first_name,last_name,matricule', 'manager:id,first_name,last_name,matricule']);

        if ($actor->isManager()) {
            $query->where(function ($q) use ($actor): void {
                $q->where('manager_id', $actor->id)
                    // #8746 — un manager peut aussi être simple membre d'un groupe.
                    ->orWhereIn('id', ConversationParticipant::query()
                        ->select('conversation_thread_id')
                        ->where('employee_id', $actor->id));
            });
        } else {
            $query->where(function ($q) use ($actor): void {
                $q->where('employee_id', $actor->id)
                    ->orWhereIn('id', ConversationParticipant::query()
                        ->select('conversation_thread_id')
                        ->where('employee_id', $actor->id));
            });
        }

        return $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * #8747 — retourne une page de l'historique d'un fil, par curseur.
     *
     * Aucun contrôle d'accès ici : l'appelant a déjà passé `findForActor()`.
     * Cette méthode ne fait que trier et borner — mais elle borne TOUJOURS
     * (`per_page` est plafonné dans {@see MessageCursor}), donc un client ne
     * peut pas faire charger un fil entier.
     *
     * Les messages supprimés sont inclus : leur `seq` doit rester visible pour
     * que le client ne perce pas un trou d'ordre entre deux messages vivants.
     * Leur contenu est masqué par la resource (`visibleBody()`).
     *
     * @return array{messages: Collection<int, ConversationMessage>, has_more: bool, next_before_seq: int|null, last_seq: int}
     */
    public function messagesFor(ConversationThread $thread, MessageCursor $cursor): array
    {
        $query = ConversationMessage::query()
            ->where('conversation_thread_id', $thread->id)
            ->with(['author:id,first_name,last_name,matricule', 'replyTo:id,author_id,body,deleted_at']);

        if ($cursor->isCatchUp()) {
            // Rattrapage : tout ce qui est strictement après le curseur connu.
            $messages = $query
                ->where('seq', '>', (int) $cursor->afterSeq)
                ->orderBy('seq')
                ->limit($cursor->perPage + 1)
                ->get();

            $hasMore = $messages->count() > $cursor->perPage;
            $messages = $messages->take($cursor->perPage)->values();

            return [
                'messages' => $messages,
                'has_more' => $hasMore,
                'next_before_seq' => null,
                'last_seq' => $thread->lastSeq(),
            ];
        }

        // Remontée dans l'historique : les N derniers, puis tri croissant pour
        // l'affichage. On lit une ligne de plus que la page pour savoir s'il
        // reste quelque chose au-delà, sans `COUNT(*)` sur tout le fil.
        $descending = $query
            ->when($cursor->beforeSeq !== null, fn ($q) => $q->where('seq', '<', (int) $cursor->beforeSeq))
            ->orderByDesc('seq')
            ->limit($cursor->perPage + 1)
            ->get();

        $hasMore = $descending->count() > $cursor->perPage;
        $page = $descending->take($cursor->perPage)->values();

        return [
            'messages' => $page->reverse()->values(),
            'has_more' => $hasMore,
            'next_before_seq' => $page->isNotEmpty() ? (int) $page->last()->seq : null,
            'last_seq' => $thread->lastSeq(),
        ];
    }

    /**
     * #8747 — édition d'un message.
     *
     * Règles serveur : l'auteur seul peut éditer son propre message (un admin
     * de groupe peut le supprimer pour modération, mais jamais réécrire les
     * propos de quelqu'un d'autre). Un message supprimé n'est plus éditable.
     * L'édition d'un message déjà à jour est idempotente et ne renotifie pas.
     */
    public function editMessage(ConversationThread $thread, Employee $actor, int $messageId, string $body): ConversationMessage
    {
        $message = $this->requireMessage($thread, $messageId);

        if ($message->author_id !== $actor->id) {
            // 404 et non 403 : un non-auteur n'a pas à apprendre qu'un message
            // existe s'il n'est pas participant ; s'il l'est, il le sait déjà,
            // mais l'API reste cohérente sur une seule réponse.
            throw new ConversationThreadNotFoundException($thread->id);
        }

        if ($message->isDeleted()) {
            throw ValidationException::withMessages([
                'body' => ['Un message supprimé ne peut plus être modifié.'],
            ]);
        }

        $trimmed = trim($body);

        if ($trimmed === '') {
            throw ValidationException::withMessages([
                'body' => ['Le message ne peut pas être vide.'],
            ]);
        }

        if ($trimmed === $message->body) {
            return $message;
        }

        $message->forceFill([
            'body' => $trimmed,
            'edited_at' => now(),
        ]);
        $message->save();

        // Une édition n'est PAS un nouveau message : pas de notification
        // « message reçu » (risque R5 de l'audit), sinon chaque correction
        // produit une seconde alerte push.
        return $message;
    }

    /**
     * #8747 — suppression logique d'un message.
     *
     * L'auteur peut supprimer son message ; un `creator`/`admin` du groupe peut
     * supprimer n'importe quel message (modération de base, #8750). La ligne
     * survit (`deleted_at`) pour préserver `seq` et les réponses ; le corps et
     * la pièce jointe sont effacés, et l'objet est retiré du disque.
     */
    public function deleteMessage(ConversationThread $thread, Employee $actor, int $messageId): ConversationMessage
    {
        $message = $this->requireMessage($thread, $messageId);

        $isAuthor = $message->author_id === $actor->id;
        $isModerator = $thread->isGroup()
            && ($thread->participantFor($actor->id)?->canAdminister() ?? false);

        if (! $isAuthor && ! $isModerator) {
            throw new ConversationThreadNotFoundException($thread->id);
        }

        if ($message->isDeleted()) {
            return $message;
        }

        $this->purgeAttachment($message);

        $message->forceFill([
            'body' => '',
            'deleted_at' => now(),
            'deleted_by_id' => $actor->id,
            'attachment_path' => null,
            'attachment_original_name' => null,
            'attachment_mime_type' => null,
            'attachment_size' => null,
        ]);
        $message->save();

        return $message;
    }

    /**
     * #8747 — message du fil courant, ou 404 (jamais 403 : cf. §8 de l'audit).
     */
    private function requireMessage(ConversationThread $thread, int $messageId): ConversationMessage
    {
        /** @var ConversationMessage|null $message */
        $message = ConversationMessage::query()
            ->where('conversation_thread_id', $thread->id)
            ->whereKey($messageId)
            ->first();

        if (! $message instanceof ConversationMessage) {
            throw new ConversationThreadNotFoundException($thread->id);
        }

        return $message;
    }

    /**
     * #8748 — supprime le fichier physique d'un message, sur SON disque.
     *
     * L'échec de suppression n'est jamais bloquant : le contenu vient d'être
     * effacé en base, donc le fichier orphelin n'est plus atteignable par l'API.
     */
    private function purgeAttachment(ConversationMessage $message): void
    {
        if (! $message->hasAttachment() || $message->attachment_path === null) {
            return;
        }

        try {
            Storage::disk($message->attachmentDisk())->delete($message->attachment_path);
        } catch (\Throwable) {
            // Volontairement silencieux : la donnée sensible est déjà en base
            // à NULL, et une pièce jointe inaccessible ne peut plus être servie
            // (le contrôleur exige un `attachment_path` non nul).
        }
    }

    /**
     * Fetches a single thread, enforcing tenant scope + participant
     * membership for $actor.
     */
    public function findForActor(int $threadId, Employee $actor): ConversationThread
    {
        $thread = ConversationThread::query()
            ->where('company_id', $actor->company_id)
            ->with([
                'employee:id,first_name,last_name,matricule',
                'manager:id,first_name,last_name,matricule',
                'messages.author:id,first_name,last_name,matricule',
                // #8746 — chargé systématiquement : `hasParticipant()` en a
                // besoin pour les groupes, et la resource l'expose.
                'participants.employee:id,first_name,last_name,matricule',
            ])
            ->find($threadId);

        if ($thread === null || ! $thread->hasParticipant($actor)) {
            throw new ConversationThreadNotFoundException($threadId);
        }

        return $thread;
    }

    /**
     * @return array{0: Employee, 1: Employee|null}
     */
    private function resolveParticipants(Employee $author, CreateThreadDTO $dto): array
    {
        if ($author->isManager()) {
            if ($dto->recipientId === null) {
                throw ValidationException::withMessages([
                    'recipient_id' => ['Un manager doit préciser l\'employé destinataire de la discussion.'],
                ]);
            }

            /** @var Employee $employee */
            $employee = Employee::query()
                ->where('company_id', $author->company_id)
                ->findOrFail($dto->recipientId);

            // #8750 — un blocage ferme la création d'un fil direct, dans les
            // deux sens. Le message d'erreur reste neutre : il ne dit pas QUI a
            // bloqué (un blocage n'est pas une information à divulguer).
            if (! $this->moderationService->canContact($author, $employee->id)) {
                throw ValidationException::withMessages([
                    'recipient_id' => ['Cette personne n\'est pas joignable par la messagerie.'],
                ]);
            }

            return [$employee, $author];
        }

        $manager = $author->manager;

        // #8750 — même règle pour un employé qui écrit à son responsable.
        if ($manager !== null && ! $this->moderationService->canContact($author, $manager->id)) {
            throw ValidationException::withMessages([
                'recipient_id' => ['Cette personne n\'est pas joignable par la messagerie.'],
            ]);
        }

        return [$author, $manager];
    }

    // ────────────────────────────────────────────────────────────────────
    // #8746 [BOS-CHAT] — Groupes : création, participants, rôles, sortie.
    //
    // Toutes ces opérations passent par `findForActor()` (donc par le scope
    // tenant + l'appartenance au fil) AVANT toute écriture : un non-participant
    // reçoit 404, jamais 403 (un 403 révélerait l'existence du fil à un tiers).
    // ────────────────────────────────────────────────────────────────────

    /**
     * #8746 — crée un groupe dont l'auteur est le `creator`.
     *
     * Les identifiants de participants sont résolus **dans le tenant de
     * l'auteur** (`company_id`) : un identifiant d'un autre tenant est rejeté
     * en 422 (jamais silencieusement ignoré), ce qui rend l'isolation
     * multi-tenant observable par un test.
     */
    public function createGroup(Employee $author, CreateGroupThreadDTO $dto, ?UploadedFile $attachment = null): ConversationThread
    {
        $memberIds = $dto->distinctParticipantIds($author->id);

        if ($memberIds === []) {
            throw ValidationException::withMessages([
                'participant_ids' => ['Un groupe doit contenir au moins un autre participant.'],
            ]);
        }

        if (count($memberIds) + 1 > self::MAX_GROUP_PARTICIPANTS) {
            throw ValidationException::withMessages([
                'participant_ids' => [sprintf('Un groupe est limité à %d participants.', self::MAX_GROUP_PARTICIPANTS)],
            ]);
        }

        // Résolution tenant-scopée : la requête porte explicitement le
        // `company_id` de l'auteur, donc un id d'un autre tenant ne matche pas.
        $members = Employee::query()
            ->where('company_id', $author->company_id)
            ->whereIn('id', $memberIds)
            ->get();

        if ($members->count() !== count($memberIds)) {
            throw ValidationException::withMessages([
                'participant_ids' => ['Un ou plusieurs participants n\'appartiennent pas à votre organisation.'],
            ]);
        }

        if ($dto->subjectType !== null) {
            $this->assertSubjectReferenceAllowed($dto->subjectType, $dto->subjectId);
        }

        return DB::transaction(function () use ($author, $dto, $members, $attachment): ConversationThread {
            $thread = ConversationThread::query()->create([
                'company_id' => $author->company_id,
                'kind' => ConversationThread::KIND_GROUP,
                'created_by' => $author->id,
                // Colonnes historiques : renseignées pour rester lisibles par
                // les surfaces existantes (resource, tri), MAIS l'appartenance
                // d'un groupe est régie par `conversation_participants`.
                'employee_id' => $author->id,
                'manager_id' => null,
                'subject_type' => $dto->subjectType,
                'subject_id' => $dto->subjectId,
                'title' => $dto->title,
                'status' => 'open',
            ]);

            ConversationParticipant::query()->create([
                'company_id' => $thread->company_id,
                'conversation_thread_id' => $thread->id,
                'employee_id' => $author->id,
                'role' => ConversationParticipant::ROLE_CREATOR,
                'joined_at' => now(),
            ]);

            foreach ($members as $member) {
                ConversationParticipant::query()->create([
                    'company_id' => $thread->company_id,
                    'conversation_thread_id' => $thread->id,
                    'employee_id' => $member->id,
                    'role' => ConversationParticipant::ROLE_MEMBER,
                    'joined_at' => now(),
                ]);
            }

            $this->postMessage($thread, $author, $dto->body, $attachment);

            // `fresh()` retourne un modèle potentiellement null : on recharge
            // explicitement pour satisfaire le type de retour déclaré.
            /** @var ConversationThread $created */
            $created = ConversationThread::query()
                ->with(['employee', 'manager', 'participants.employee'])
                ->findOrFail($thread->id);

            return $created;
        });
    }

    /**
     * #8746 — ajoute un membre au groupe. Seul un `creator`/`admin` peut le faire.
     */
    public function addParticipant(ConversationThread $thread, Employee $actor, int $employeeId): ConversationParticipant
    {
        $actorParticipant = $this->requireParticipant($thread, $actor);

        if (! $this->participantPolicy->canAdminister($actorParticipant)) {
            throw new ConversationThreadNotFoundException($thread->id);
        }

        if ($thread->participants()->count() >= self::MAX_GROUP_PARTICIPANTS) {
            throw ValidationException::withMessages([
                'employee_id' => [sprintf('Un groupe est limité à %d participants.', self::MAX_GROUP_PARTICIPANTS)],
            ]);
        }

        /** @var Employee|null $candidate */
        $candidate = Employee::query()
            ->where('company_id', $thread->company_id)
            ->find($employeeId);

        if (! $candidate instanceof Employee) {
            throw ValidationException::withMessages([
                'employee_id' => ['Cet employé n\'appartient pas à votre organisation.'],
            ]);
        }

        // #8750 — le bloqueur ne peut pas réintroduire dans un groupe la
        // personne qu'il a bloquée (ni l'inverse). Le contrôle porte sur le
        // couple (acteur, candidat), pas sur les autres membres : un groupe
        // existant peut déjà contenir deux personnes en froid, et l'ajout d'un
        // tiers ne doit pas être bloqué pour autant.
        if (! $this->moderationService->canContact($actor, $candidate->id)) {
            throw ValidationException::withMessages([
                'employee_id' => ['Cette personne ne peut pas être ajoutée à cette conversation.'],
            ]);
        }

        $existing = $thread->participants()->where('employee_id', $candidate->id)->first();

        if ($existing instanceof ConversationParticipant) {
            // Idempotent : re-ajouter un membre déjà présent renvoie sa ligne
            // au lieu d'échouer sur la contrainte d'unicité.
            return $existing;
        }

        $participant = ConversationParticipant::query()->create([
            'company_id' => $thread->company_id,
            'conversation_thread_id' => $thread->id,
            'employee_id' => $candidate->id,
            'role' => ConversationParticipant::ROLE_MEMBER,
            'joined_at' => now(),
        ]);

        $this->communicationService->notifyEmployee($candidate, 'conversation_group_added', [
            'thread' => $thread->title,
            'conversation_thread_id' => $thread->id,
        ], ['app']);

        return $participant;
    }

    /**
     * #8746 — retire un membre. Le créateur est indéboulonnable, un admin ne
     * peut pas retirer un pair (cf. ConversationParticipantPolicy).
     */
    public function removeParticipant(ConversationThread $thread, Employee $actor, int $employeeId): void
    {
        $actorParticipant = $this->requireParticipant($thread, $actor);

        /** @var ConversationParticipant|null $target */
        $target = $thread->participants()->where('employee_id', $employeeId)->first();

        if (! $target instanceof ConversationParticipant) {
            throw new ConversationThreadNotFoundException($thread->id);
        }

        if ($target->employee_id === $actor->id) {
            // Utiliser `leave()` : self-removal suit ses propres règles.
            $this->leave($thread, $actor);

            return;
        }

        if (! $this->participantPolicy->canRemove($actorParticipant, $target)) {
            throw new ConversationThreadNotFoundException($thread->id);
        }

        $target->delete();
    }

    /**
     * #8746 — l'acteur quitte le groupe. Le créateur ne peut pas partir : sans
     * lui, le groupe n'a plus d'administrateur. La transmission du rôle
     * (`creator`) est une issue de suivi, non implémentée ici.
     */
    public function leave(ConversationThread $thread, Employee $actor): void
    {
        $participant = $this->requireParticipant($thread, $actor);

        if (! $this->participantPolicy->canLeave($participant)) {
            throw ValidationException::withMessages([
                'participant' => ['Le créateur ne peut pas quitter le groupe sans transmettre son rôle.'],
            ]);
        }

        $participant->delete();
    }

    /**
     * #8746 — participant effectif de l'acteur, ou 404 si le fil est un
     * `direct` (les groupes seuls ont des lignes `conversation_participants`)
     * ou si l'acteur n'est pas membre.
     */
    private function requireParticipant(ConversationThread $thread, Employee $actor): ConversationParticipant
    {
        $thread->loadMissing('participants');

        /** @var ConversationParticipant|null $participant */
        $participant = $thread->participantFor($actor->id);

        if (! $participant instanceof ConversationParticipant) {
            throw new ConversationThreadNotFoundException($thread->id);
        }

        return $participant;
    }

    /**
     * #8746 — un `subject_type` de contexte métier est une référence
     * DOCUMENTAIRE : on vérifie seulement qu'il est connu et accompagné d'un
     * identifiant. Aucun droit d'accès n'en découle (contrairement au chemin
     * PA2-COMM-002, qui vérifiait l'appartenance du sujet à l'employé).
     */
    private function assertSubjectReferenceAllowed(string $subjectType, ?int $subjectId): void
    {
        if (! in_array($subjectType, ConversationThread::SUBJECT_TYPES, true) || $subjectId === null) {
            throw ValidationException::withMessages([
                'subject_type' => ['Sujet de discussion invalide.'],
            ]);
        }
    }

    private function assertSubjectBelongsToEmployee(string $subjectType, ?int $subjectId, Employee $employee): void
    {
        if (! array_key_exists($subjectType, self::SUBJECT_MODELS) || $subjectId === null) {
            throw ValidationException::withMessages([
                'subject_type' => ['Sujet de discussion invalide.'],
            ]);
        }

        $modelClass = self::SUBJECT_MODELS[$subjectType];

        /** @var Model|null $subject */
        $subject = $modelClass::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->find($subjectId);

        if ($subject === null) {
            throw ValidationException::withMessages([
                'subject_id' => ['Le sujet lié n\'a pas été trouvé pour cet employé.'],
            ]);
        }
    }

    private function ensureAttachmentAllowed(UploadedFile $attachment): void
    {
        $maxBytes = self::MAX_ATTACHMENT_KB * 1024;

        if ($attachment->getSize() > $maxBytes) {
            throw ValidationException::withMessages([
                'attachment' => ['La pièce jointe dépasse la taille maximale autorisée (5 Mo).'],
            ]);
        }

        // #8748 — premier filtre sur le type réel du fichier TEMPORAIRE :
        // rejeter avant d'écrire évite de stocker un fichier interdit, même
        // quelques millisecondes. Le second contrôle (sur le fichier stocké)
        // a lieu dans `storeAttachment()` et sert de référence persistée.
        $detected = $this->detectMimeType($attachment->getRealPath() ?: $attachment->getPathname());

        if (! in_array($detected, self::ALLOWED_ATTACHMENT_MIMES, true)) {
            throw ValidationException::withMessages([
                'attachment' => ['Ce type de fichier n\'est pas autorisé dans une conversation.'],
            ]);
        }
    }

    private function notifyOtherParticipant(ConversationThread $thread, Employee $author, ConversationMessage $message): void
    {
        $authorName = trim(($author->first_name ?? '').' '.($author->last_name ?? '')) ?: $author->email;

        // #8746 — un groupe notifie TOUS les autres participants, un fil
        // `direct` l'unique interlocuteur historique. Dans les deux cas on
        // passe par le même canal (CommunicationService), donc les préférences,
        // les heures calmes et l'anti-doublon restent centralisés.
        if ($thread->isGroup()) {
            $thread->loadMissing('participants.employee');

            foreach ($thread->participants as $participant) {
                $recipient = $participant->employee;

                if ($recipient === null || $recipient->id === $author->id) {
                    continue;
                }

                $this->communicationService->notifyEmployee($recipient, 'conversation_message_received', [
                    'author' => $authorName,
                    'thread' => $thread->title,
                    'conversation_thread_id' => $thread->id,
                    'conversation_message_id' => $message->id,
                ], ['app', 'push']);
            }

            return;
        }

        $recipient = $thread->employee_id === $author->id
            ? $thread->manager
            : $thread->employee;

        if ($recipient === null) {
            return;
        }

        $this->communicationService->notifyEmployee($recipient, 'conversation_message_received', [
            'author' => $authorName,
            'thread' => $thread->title,
            'conversation_thread_id' => $thread->id,
            'conversation_message_id' => $message->id,
        ], ['app', 'push']);
    }
}
