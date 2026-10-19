<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceCorrectionRequest;
use App\Modules\Notification\Application\DTOs\CreateGroupThreadDTO;
use App\Modules\Notification\Application\DTOs\CreateThreadDTO;
use App\Modules\Notification\Domain\Exceptions\ConversationThreadNotFoundException;
use App\Modules\Notification\Domain\Models\ConversationMessage;
use App\Modules\Notification\Domain\Models\ConversationParticipant;
use App\Modules\Notification\Domain\Models\ConversationThread;
use App\Modules\Notification\Domain\Policies\ConversationParticipantPolicy;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use App\Modules\Planning\Domain\Models\Absence;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    /** Maximum number of members in a group thread, attachments and notifications inclusive. */
    private const MAX_GROUP_PARTICIPANTS = 50;

    public function __construct(
        private readonly CommunicationService $communicationService,
        private readonly ConversationParticipantPolicy $participantPolicy = new ConversationParticipantPolicy,
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
     */
    public function postMessage(ConversationThread $thread, Employee $author, string $body, ?UploadedFile $attachment): ConversationMessage
    {
        $attachmentPath = null;
        $attachmentOriginalName = null;
        $attachmentMimeType = null;
        $attachmentSize = null;

        if ($attachment !== null) {
            $this->ensureAttachmentAllowed($attachment);

            $attachmentPath = $attachment->store(
                sprintf('conversations/%s/%d', $thread->company_id, $thread->id),
                'local',
            );
            $attachmentOriginalName = $attachment->getClientOriginalName();
            $attachmentMimeType = $attachment->getMimeType();
            $attachmentSize = $attachment->getSize();
        }

        $message = ConversationMessage::query()->create([
            'company_id' => $thread->company_id,
            'conversation_thread_id' => $thread->id,
            'author_id' => $author->id,
            'body' => $body,
            'attachment_path' => $attachmentPath ?: null,
            'attachment_original_name' => $attachmentOriginalName,
            'attachment_mime_type' => $attachmentMimeType,
            'attachment_size' => $attachmentSize,
        ]);

        $thread->forceFill([
            'last_message_id' => $message->id,
            'last_message_at' => $message->created_at,
        ]);
        $thread->markReadFor($author);
        $thread->save();

        $this->notifyOtherParticipant($thread, $author, $message);

        return $message;
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

            return [$employee, $author];
        }

        return $author->isManager() ? [$employee, $author] : [$author, $author->manager];
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

            return $thread->fresh(['employee', 'manager', 'participants.employee']);
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
