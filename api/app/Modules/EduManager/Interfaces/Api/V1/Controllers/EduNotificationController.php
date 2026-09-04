<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use App\Modules\Notification\Domain\Models\CommunicationEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notifications EduManager — EDU-014 (issue #5830).
 *
 * Lecture seule des événements de communication `edu_*` du tenant (le hub
 * transverse Notification trace chaque envoi dans `communication_events`).
 * La direction consulte l'historique ; aucune donnée sensible n'est
 * renvoyée hors tenant (filtre `company_id` + template_key `edu_%`).
 */
class EduNotificationController extends Controller
{
    use ChecksEduSolution;

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', EduStudent::class);

        $query = CommunicationEvent::query()
            ->where('company_id', $actor->company_id)
            ->where('template_key', 'like', 'edu\_%');

        if ($request->filled('event_name')) {
            $query->where('event_name', $request->input('event_name'));
        }

        $events = $query->orderByDesc('occurred_at')->paginate((int) ($request->input('per_page') ?? 15));

        return response()->json([
            'data' => collect($events->items())->map(fn (CommunicationEvent $event): array => [
                'id' => (int) $event->getAttribute('id'),
                'event_name' => $event->event_name,
                'channel' => $event->channel,
                'status' => $event->status,
                'template_key' => $event->template_key,
                'employee_id' => $event->employee_id,
                'occurred_at' => $event->occurred_at->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $events->currentPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }
}
