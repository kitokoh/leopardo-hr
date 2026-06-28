<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Application\Actions\MarkNotificationsRead;
use App\Modules\Notification\Domain\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly MarkNotificationsRead $markRead,
    ) {}

    /**
     * List notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->when($request->boolean('unread_only'), fn ($q) => $q->where('read', false))
            ->latest()
            ->paginate(30);

        $unreadCount = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('read', false)
            ->count();

        return response()->json([
            'data'         => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark one or all notifications as read.
     */
    public function markRead(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
        ]);

        $count = $this->markRead->handle(
            (int) $request->user()->id,
            $request->ids ?? null
        );

        return response()->json(['marked_read' => $count]);
    }

    /**
     * Delete a single notification.
     */
    public function destroy(Request $request, AppNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === (int) $request->user()->id, 403);

        $notification->delete();

        return response()->json(null, 204);
    }
}
