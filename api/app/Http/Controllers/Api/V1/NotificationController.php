<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $request->validate(['unread_only' => ['nullable', 'boolean'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $query = Notification::forEmployee($actor->id);
        if ($request->boolean('unread_only')) $query->unread();

        $perPage   = $request->integer('per_page', 20);
        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $paginated->map(fn ($n) => $this->serialize($n)),
            'meta' => ['current_page' => $paginated->currentPage(), 'last_page' => $paginated->lastPage(), 'per_page' => $paginated->perPage(), 'total' => $paginated->total(), 'unread_count' => Notification::forEmployee($actor->id)->unread()->count()],
        ]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        $actor = $request->user();
        if ($notification->employee_id !== $actor->id) abort(403);

        if (!$notification->is_read) {
            $notification->update(['is_read' => true, 'read_at' => Carbon::now()]);
        }

        return response()->json(['data' => $this->serialize($notification->fresh())]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $actor = $request->user();
        Notification::forEmployee($actor->id)->unread()->update(['is_read' => true, 'read_at' => Carbon::now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        $actor = $request->user();
        if ($notification->employee_id !== $actor->id) abort(403);

        $notification->delete();

        return response()->json(['message' => 'Notification deleted successfully']);
    }

    private function serialize(Notification $n): array
    {
        return ['id' => $n->id, 'type' => $n->type, 'title' => $n->title, 'body' => $n->body, 'data' => $n->data, 'is_read' => $n->is_read, 'read_at' => $n->read_at?->toIso8601String(), 'created_at' => $n->created_at?->toIso8601String()];
    }
}
