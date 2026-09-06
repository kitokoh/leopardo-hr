<?php

declare(strict_types=1);

namespace App\Modules\Planning\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use App\Modules\Planning\Domain\Models\Task;
use App\Modules\Planning\Domain\Models\TaskComment;

/**
 * Cas d'usage : ajout d'un commentaire à une tâche.
 *
 * Consommé par `POST /api/v1/tasks/{task}/comments`
 * (TaskController::addComment). L'accès au commentaire est vérifié par le
 * contrôleur (404 cross-tenant, 403 si ni manager, ni créateur, ni assigné).
 *
 * Effet de bord : notification des autres participants (créateur + assignés,
 * hors auteur) via CommunicationService — template `task_comment_added`,
 * résolu dans la locale du destinataire (PA2-COMM-006).
 */
class CreateTaskComment
{
    public function __construct(
        private readonly CommunicationService $communicationService,
    ) {}

    public function execute(Employee $actor, Task $task, string $content): TaskComment
    {
        $comment = TaskComment::create([
            'company_id' => $actor->company_id,
            'task_id' => $task->id,
            'author_id' => $actor->id,
            'content' => $content,
        ]);

        $this->notifyTaskParticipants($task, $actor, $comment);

        return $comment;
    }

    /**
     * Notifie les autres participants (créateur + assignés) qu'un nouveau
     * commentaire a été posté, en excluant l'auteur du commentaire.
     */
    private function notifyTaskParticipants(Task $task, Employee $author, TaskComment $comment): void
    {
        $recipientIds = collect($task->assigned_to ?? [])
            ->push($task->created_by)
            ->filter()
            ->unique()
            ->reject(fn ($id): bool => (int) $id === (int) $author->id)
            ->values();

        if ($recipientIds->isEmpty()) {
            return;
        }

        $recipients = Employee::query()
            ->where('company_id', $task->company_id)
            ->whereIn('id', $recipientIds)
            ->get();

        $authorName = trim($author->first_name.' '.$author->last_name);

        foreach ($recipients as $recipient) {
            // Title/body are resolved by CommunicationService from the
            // `task_comment_added` template using the recipient's own
            // locale (preferred_language, falling back to the company
            // language) — see PA2-COMM-006.
            $this->communicationService->notifyEmployee($recipient, 'task_comment_added', [
                'task' => $task->title,
                'author' => $authorName,
                'task_id' => $task->id,
                'task_comment_id' => $comment->id,
            ], ['app']);
        }
    }
}
