<?php

declare(strict_types=1);

namespace App\Modules\Communication\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Communication\Domain\Models\CommunicationMessage;
use App\Modules\Communication\Domain\Models\CommunicationThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consultation des fils/messages Gmail synchronises (BC-29, R2 #7687).
 *
 * La boite est PERSONNELLE : l'index ne renvoie que les fils des boites de
 * l'employe COURANT ; un fil d'un collegue est 403 (policy), un fil d'un
 * autre tenant 404 (binding implicite tenant-scope). Le corps (dechiffre a
 * la lecture par le cast) ne sort que sur l'endpoint messages du
 * proprietaire — jamais dans les listes (minimisation).
 */
class CommunicationThreadController extends Controller
{
    /**
     * Fils des boites de l'employe courant, du plus recent au plus ancien
     * (les « 50 derniers fils » de l'acceptation tiennent sur la premiere
     * page). Filtre optionnel `?integration={uuid}`.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommunicationThread::class);

        /** @var Employee $employee */
        $employee = $request->user();

        $threads = CommunicationThread::query()
            ->whereHas('integration', function ($query) use ($employee): void {
                $query->where('employee_id', $employee->id);
            })
            ->when(
                is_string($request->query('integration')),
                fn ($query) => $query->where('integration_id', (string) $request->query('integration'))
            )
            ->orderByDesc('last_message_at')
            ->paginate(min((int) $request->query('per_page', '50'), 100));

        return new JsonResponse([
            'data' => collect($threads->items())
                ->map(fn (CommunicationThread $thread): array => $this->presentThread($thread))
                ->all(),
            'meta' => [
                'current_page' => $threads->currentPage(),
                'last_page' => $threads->lastPage(),
                'per_page' => $threads->perPage(),
                'total' => $threads->total(),
            ],
        ]);
    }

    /**
     * Messages d'UN fil (proprietaire uniquement — policy `view`), tries
     * chronologiquement. Seul endpoint qui expose le corps (text/plain
     * borne, dechiffre a la lecture).
     */
    public function messages(CommunicationThread $thread): JsonResponse
    {
        $this->authorize('view', $thread);

        $messages = $thread->messages()
            ->orderBy('sent_at')
            ->get()
            ->map(fn (CommunicationMessage $message): array => $this->presentMessage($message));

        return new JsonResponse([
            'data' => [
                'thread' => $this->presentThread($thread),
                'messages' => $messages->all(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentThread(CommunicationThread $thread): array
    {
        return [
            'id' => $thread->id,
            'integration_id' => $thread->integration_id,
            'gmail_thread_id' => $thread->gmail_thread_id,
            'subject' => $thread->subject,
            'snippet' => $thread->snippet,
            'message_count' => $thread->message_count,
            'last_message_at' => $thread->last_message_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMessage(CommunicationMessage $message): array
    {
        return [
            'id' => $message->id,
            'gmail_message_id' => $message->gmail_message_id,
            'internet_message_id' => $message->internet_message_id,
            'in_reply_to' => $message->in_reply_to,
            'from_email' => $message->from_email,
            'to_emails' => $message->to_emails ?? [],
            'cc_emails' => $message->cc_emails ?? [],
            'subject' => $message->subject,
            'snippet' => $message->snippet,
            'body' => $message->body,
            'labels' => $message->labels ?? [],
            'attachment_refs' => $message->attachment_refs ?? [],
            'sent_at' => $message->sent_at?->toIso8601String(),
            // R3 (#7688) — classification IA + liaison CRM.
            'ai_category' => $message->ai_category,
            'ai_language' => $message->ai_language,
            'ai_sentiment' => $message->ai_sentiment,
            'ai_action' => $message->ai_action,
            'ai_confidence' => $message->ai_confidence,
            'classification_status' => $message->classification_status,
            'classified_at' => $message->classified_at?->toIso8601String(),
            'crm_contact_id' => $message->crm_contact_id,
            'contact_link_status' => $message->contact_link_status,
        ];
    }
}
