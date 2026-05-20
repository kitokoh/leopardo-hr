<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotificationStreamController extends Controller
{
    public function stream(Request $request): StreamedResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $userId = $user->id;
        $companyId = $user->company_id;
        $lastCheck = now();

        return new StreamedResponse(function () use ($userId, $companyId, &$lastCheck): void {
            $maxDuration = 120;
            $start = time();
            $interval = 5;

            while (time() - $start < $maxDuration) {
                if (connection_aborted()) {
                    break;
                }

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
                        'unread_count' => $employee->unreadNotifications()->count(),
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
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
