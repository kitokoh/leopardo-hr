<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Core\Auth\Domain\Models\Employee;
use App\Models\CommunicationEvent;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * List notifications for the authenticated employee.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $validated = $request->validate([
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
            'type'        => ['nullable', 'string', 'max:80'],
            'unread_only' => ['nullable', 'in:true,false,1,0,on,off,yes,no'],
            'sort_dir'    => ['nullable', 'in:asc,desc'],
        ]);

        $query = Notification::query()
            ->where('company_id', $user->company_id)
            ->where('employee_id', $user->id);

        if (($validated['type'] ?? '') !== '') {
            $query->where('type', $validated['type']);
        }
        if ($request->has('unread_only') && $request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        $notifications = $query
            ->orderBy('created_at', (string) ($validated['sort_dir'] ?? 'desc'))
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 20));

        $unreadCount = Notification::query()
            ->where('company_id', $user->company_id)
            ->where('employee_id', $user->id)
            ->where('is_read', false)
            ->count();

        return NotificationResource::collection($notifications)
            ->additional(['meta' => ['unread_count' => $unreadCount]])
            ->response();
    }

    /**
     * GET /notifications/unread — Returns only unread notifications.
     */
    public function unread(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $notifications = Notification::query()
            ->where('company_id', $user->company_id)
            ->where('employee_id', $user->id)
            ->where('is_read', false)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return NotificationResource::collection($notifications)
            ->response();
    }

    /**
     * Mark a single notification as read (PATCH|PUT /notifications/{id}/read).
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $notification = DB::transaction(function () use ($user, $id) {
            /** @var Notification $notification */
            $notification = Notification::query()
                ->where('company_id', $user->company_id)
                ->where('employee_id', $user->id)
                ->where('id', $id)
                ->firstOrFail();

            $notification->markAsRead();

            // Track the read event for communication analytics
            CommunicationEvent::create([
                'company_id'      => $user->company_id,
                'employee_id'     => $user->id,
                'notification_id' => $notification->id,
                'event_name'      => 'notification_read',
                'channel'         => 'app',
                'status'          => 'delivered',
                'occurred_at'     => now(),
            ]);

            return $notification->fresh();
        });

        return response()->json([
            'data' => (new NotificationResource($notification))->resolve($request),
        ]);
    }

    /**
     * Mark all unread notifications as read (PUT /notifications/read-all).
     */
    public function markAllRead(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            $unread = Notification::query()
                ->where('company_id', $user->company_id)
                ->where('employee_id', $user->id)
                ->where('is_read', false)
                ->get();

            if ($unread->isEmpty()) {
                return;
            }

            Notification::query()
                ->where('company_id', $user->company_id)
                ->where('employee_id', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true, 'read_at' => now()]);

            $now = now();
            foreach ($unread as $notification) {
                CommunicationEvent::create([
                    'company_id'      => $user->company_id,
                    'employee_id'     => $user->id,
                    'notification_id' => $notification->id,
                    'event_name'      => 'notification_read',
                    'channel'         => 'app',
                    'status'          => 'delivered',
                    'occurred_at'     => $now,
                ]);
            }
        });

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    /**
     * Delete a notification (DELETE /notifications/{id}).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        DB::transaction(function () use ($user, $id): void {
            $notification = Notification::query()
                ->where('company_id', $user->company_id)
                ->where('employee_id', $user->id)
                ->where('id', $id)
                ->firstOrFail();

            $notification->delete();
        });

        return response()->json(['message' => 'Notification deleted.']);
    }
}
