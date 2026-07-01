<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * NotificationStreamController — Server-Sent Events (SSE) stream.
 *
 * Migrated from App\Http\Controllers\Api\V1\NotificationStreamController.
 * Streams real-time notifications to the authenticated employee.
 * Re-connects automatically after 120 s (client handles reconnect).
 */
class NotificationStreamController extends Controller
{
    public function stream(Request $request): StreamedResponse
    {
        // Support short-lived SSE token passed as query param (avoids leaking main token in logs)
        $userId = null;
        $companyId = null;

        if ($request->query('sse_token')) {
            $cacheKey = 'sse_token:'.$request->query('sse_token');
            /** @var array{user_id: int, company_id: int}|null $tokenData */
            $tokenData = \Illuminate\Support\Facades\Cache::pull($cacheKey); // single-use
            if ($tokenData === null) {
                return new StreamedResponse(function (): void {
                    echo "event: error\ndata: {\"message\":\"invalid_sse_token\"}\n\n";
                    ob_flush();
                    flush();
                }, 401, [
                    'Content-Type' => 'text/event-stream',
                    'Cache-Control' => 'no-cache',
                ]);
            }
            $userId = $tokenData['user_id'];
            $companyId = $tokenData['company_id'];
        } else {
            /** @var Employee $user */
            $user = $request->user();
            if (!$user) {
                return new StreamedResponse(function (): void {
                    echo "event: error\ndata: {\"message\":\"unauthenticated\"}\n\n";
                    ob_flush();
                    flush();
                }, 401, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache']);
            }
            $userId = $user->id;
            $companyId = $user->company_id;
        }

        $lastCheck = now();

        return new StreamedResponse(function () use ($userId, $companyId, &$lastCheck): void {
            $maxDuration = 120;
            $start       = time();
            $interval    = 5;

            while (time() - $start < $maxDuration) {
                if (connection_aborted()) {
                    break;
                }

                /** @var (Employee&\Illuminate\Notifications\Notifiable)|null $employee */
                $employee = Employee::withoutGlobalScopes()
                    ->where('id', $userId)
                    ->where('company_id', $companyId)
                    ->first();

                if ($employee === null) {
                    echo "event: error\ndata: {\"message\":\"session_expired\"}\n\n";
                    ob_flush();
                    flush();
                    break;
                }

                $newNotifications = $employee->notifications()
                    ->where('created_at', '>', $lastCheck)
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get();

                if ($newNotifications->isNotEmpty()) {
                    $payload = json_encode([
                        'notifications' => $newNotifications->toArray(),
                        'unread_count'  => $employee->unreadNotifications()->count(),
                    ], JSON_THROW_ON_ERROR);

                    echo "event: notification\ndata: {$payload}\n\n";
                    $lastCheck = now();
                } else {
                    echo ": heartbeat\n\n";
                }

                ob_flush();
                flush();
                sleep($interval);
            }

            echo "event: timeout\ndata: {\"message\":\"reconnect\"}\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
