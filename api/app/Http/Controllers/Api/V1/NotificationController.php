<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Models\CommunicationEvent;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Api\V1\Notification\NotificationIndexRequest;

class NotificationController extends Controller
{
    public function index(NotificationIndexRequest $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $data = $request->validated();

        $query = $user->notifications();

        if (($validated['type'] ?? '') !== '') {
            $query->where('type', $validated['type']);
        }

        if (($request->has('unread') && $request->boolean('unread'))
            || ($request->has('unread_only') && $request->boolean('unread_only'))) {
            $query->where('is_read', false);
        }

        $notifications = $query
            ->orderBy('created_at', (string) ($validated['sort_dir'] ?? 'desc'))
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return NotificationResource::collection($notifications)
            ->additional([
                'meta' => [
                    'unread_count' => $user->unreadNotifications()->count(),
                ],
            ])
            ->response();
    }

    public function unread(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $unread = $user->unreadNotifications()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return NotificationResource::collection($unread)->response();
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $notification = DB::transaction(function () use ($user, $id) {
            $notification = $user->notifications()->where('id', $id)->firstOrFail();
            $notification->markAsRead();
            $this->recordCommunicationEvent($user, 'notification_read', (int) $notification->id, [
                'type' => $notification->type,
            ]);

            return $notification->fresh();
        });

        return response()->json([
            'data' => (new NotificationResource($notification))->resolve($request),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            $user->unreadNotifications()->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
            $this->recordCommunicationEvent($user, 'notifications_marked_read', null, [
                'scope' => 'all_unread',
            ]);
        });

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        DB::transaction(function () use ($user, $id): void {
            $notification = $user->notifications()->where('id', $id)->firstOrFail();
            $notificationId = (int) $notification->id;
            $notificationType = (string) $notification->type;

            $notification->delete();

            $this->recordCommunicationEvent($user, 'notification_deleted', $notificationId, [
                'type' => $notificationType,
            ]);
        });

        return response()->json(['message' => 'Notification deleted.']);
    }

    /** @param array<string, mixed> $metadata */
    private function recordCommunicationEvent(Employee $employee, string $eventName, ?int $notificationId, array $metadata = []): void
    {
        CommunicationEvent::query()->create([
            'company_id' => (string) $employee->company_id,
            'employee_id' => $employee->id,
            'notification_id' => $notificationId,
            'event_name' => $eventName,
            'channel' => 'app',
            'status' => 'recorded',
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
