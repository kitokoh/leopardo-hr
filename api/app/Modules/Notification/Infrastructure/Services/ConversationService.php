<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceCorrectionRequest;
use App\Modules\Notification\Application\DTOs\CreateThreadDTO;
use App\Modules\Notification\Domain\Exceptions\ConversationThreadNotFoundException;
use App\Modules\Notification\Domain\Models\ConversationMessage;
use App\Modules\Notification\Domain\Models\ConversationThread;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use App\Modules\Planning\Domain\Models\Absence;
use Illuminate\Http\UploadedFile;
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

    public function __construct(
        private readonly CommunicationService $communicationService,
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
            $attachmentMimeType = $attachment->getClientMimeType();
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
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, ConversationThread>
     */
    public function threadsFor(Employee $actor, int $perPage = 20)
    {
        $query = ConversationThread::query()
            ->where('company_id', $actor->company_id)
            ->with(['employee:id,first_name,last_name,matricule', 'manager:id,first_name,last_name,matricule']);

        if ($actor->isManager()) {
            $query->where('manager_id', $actor->id);
        } else {
            $query->where('employee_id', $actor->id);
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
            ->with(['employee:id,first_name,last_name,matricule', 'manager:id,first_name,last_name,matricule', 'messages.author:id,first_name,last_name,matricule'])
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

        return [$author, $author->manager];
    }

    private function assertSubjectBelongsToEmployee(string $subjectType, ?int $subjectId, Employee $employee): void
    {
        if (! array_key_exists($subjectType, self::SUBJECT_MODELS) || $subjectId === null) {
            throw ValidationException::withMessages([
                'subject_type' => ['Sujet de discussion invalide.'],
            ]);
        }

        $modelClass = self::SUBJECT_MODELS[$subjectType];

        /** @var \Illuminate\Database\Eloquent\Model|null $subject */
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
        $recipient = $thread->employee_id === $author->id
            ? $thread->manager
            : $thread->employee;

        if ($recipient === null) {
            return;
        }

        $authorName = trim(($author->first_name ?? '').' '.($author->last_name ?? '')) ?: $author->email;

        $this->communicationService->notifyEmployee($recipient, 'conversation_message_received', [
            'author' => $authorName,
            'thread' => $thread->title,
            'conversation_thread_id' => $thread->id,
            'conversation_message_id' => $message->id,
        ], ['app', 'push']);
    }
}
