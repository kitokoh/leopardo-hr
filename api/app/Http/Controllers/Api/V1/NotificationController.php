<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', 'string', 'max:80'],
            'unread' => ['nullable', 'boolean'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ]);

        $query = $user->notifications();

        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if ($request->has('unread') && $request->boolean('unread')) {
            $query->where('is_read', false);
        }

        $notifications = $query
            ->orderBy('created_at', (string) ($validated['sort_dir'] ?? 'desc'))
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    public function unread(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $unread = $user->unreadNotifications()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $unread]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['data' => $notification->fresh()]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $user->unreadNotifications()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
