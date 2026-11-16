<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Modules\Communication\Domain\Models\CommunicationEvent;
use App\Modules\Notification\Domain\Models\Notification;
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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'type' => ['nullable', 'string', 'max:80'],
            // #2331 : contrat v4.16.185 — `unread=true` (mobile) est l'alias
            // canonique ; `unread_only` reste accepté pour compatibilité.
            'unread' => ['nullable', 'in:true,false,1,0,on,off,yes,no'],
            'unread_only' => ['nullable', 'in:true,false,1,0,on,off,yes,no'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ]);

        $query = Notification::query()
            ->where('company_id', $user->company_id)
            ->where('employee_id', $user->id);

        if (($validated['type'] ?? '') !== '') {
            $query->where('type', $validated['type']);
        }
        $unreadFilter = $request->has('unread')
            ? $request->boolean('unread')
            : $request->boolean('unread_only');
        if ($unreadFilter) {
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
                'company_id' => $user->company_id,
                'employee_id' => $user->id,
                'notification_id' => $notification->id,
                'event_name' => 'notification_read',
                'channel' => 'app',
                'status' => 'delivered',
                'occurred_at' => now(),
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
                    'company_id' => $user->company_id,
                    'employee_id' => $user->id,
                    'notification_id' => $notification->id,
                    'event_name' => 'notification_read',
                    'channel' => 'app',
                    'status' => 'delivered',
                    'occurred_at' => $now,
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

    /**
     * feat(notification): API purge des notifications anciennes (#9540).
     *
     * Supprime les notifications LUES de plus de X jours pour nettoyer
     * la base et améliorer les performances. Les notifications non lues
     * et critiques sont toujours conservées.
     *
     * RBAC : authentifié (chaque employé purge ses propres notifications).
     */
    public function purgeOld(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:7', 'max:365'],
            'confirm' => ['required', 'boolean', 'accepted'],
        ]);

        abort_unless($validated['confirm'], 422, 'PURGE_NOT_CONFIRMED');

        $days = $validated['days'] ?? 90;
        $cutoffDate = now()->subDays($days);

        $deleted = DB::transaction(function () use ($user, $cutoffDate) {
            return Notification::query()
                ->where('company_id', $user->company_id)
                ->where('employee_id', $user->id)
                ->where('is_read', true)
                ->where('created_at', '<', $cutoffDate)
                // Ne jamais supprimer les notifications critiques
                ->whereNotIn('type', ['security_alert', 'system_maintenance'])
                ->delete();
        });

        $oldestKept = Notification::query()
            ->where('company_id', $user->company_id)
            ->where('employee_id', $user->id)
            ->orderBy('created_at')
            ->value('created_at');

        return response()->json([
            'data' => [
                'deleted_count' => $deleted,
                'oldest_kept' => $oldestKept ? $oldestKept->toIso8601String() : null,
            ],
        ]);
    }
}
