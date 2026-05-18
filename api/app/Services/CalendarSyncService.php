<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CalendarConnection;
use App\Models\CalendarEvent;
use App\Models\Employee;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CalendarSyncService
{
    public function connect(Employee $employee, string $provider, string $accessToken, ?string $refreshToken = null, ?string $calendarId = null, ?\DateTimeInterface $expiresAt = null): CalendarConnection
    {
        return CalendarConnection::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'provider' => $provider,
            ],
            [
                'access_token' => encrypt($accessToken),
                'refresh_token' => $refreshToken ? encrypt($refreshToken) : null,
                'calendar_id' => $calendarId,
                'token_expires_at' => $expiresAt,
                'is_active' => true,
            ]
        );
    }

    public function disconnect(Employee $employee, string $provider): bool
    {
        return CalendarConnection::query()
            ->where('employee_id', $employee->id)
            ->where('provider', $provider)
            ->update(['is_active' => false, 'access_token' => null, 'refresh_token' => null]) > 0;
    }

    public function syncLeaves(Employee $employee): int
    {
        $connections = CalendarConnection::query()
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->where('sync_leaves', true)
            ->get();

        $synced = 0;

        foreach ($connections as $connection) {
            try {
                $absences = \App\Models\Absence::query()
                    ->where('employee_id', $employee->id)
                    ->where('status', 'approved')
                    ->where('start_date', '>=', now()->subMonth())
                    ->get();

                foreach ($absences as $absence) {
                    $event = CalendarEvent::query()->updateOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'source_type' => 'absence',
                            'source_id' => $absence->id,
                            'provider' => $connection->provider,
                        ],
                        [
                            'title' => 'Congé : '.($absence->type ?? 'absence'),
                            'description' => $absence->reason ?? '',
                            'starts_at' => $absence->start_date,
                            'ends_at' => $absence->end_date ?? $absence->start_date,
                            'all_day' => true,
                            'sync_status' => 'pending',
                        ]
                    );

                    $pushed = $this->pushEventToProvider($connection, $event);
                    if ($pushed) {
                        $synced++;
                    }
                }

                $connection->update(['last_synced_at' => now()]);
            } catch (\Throwable $e) {
                Log::error('Calendar sync leaves failed', [
                    'employee_id' => $employee->id,
                    'provider' => $connection->provider,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $synced;
    }

    public function syncTraining(Employee $employee): int
    {
        $connections = CalendarConnection::query()
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->where('sync_training', true)
            ->get();

        $synced = 0;

        foreach ($connections as $connection) {
            try {
                $enrollments = \Illuminate\Support\Facades\DB::table('training_enrollments')
                    ->join('training_sessions', 'training_enrollments.training_session_id', '=', 'training_sessions.id')
                    ->where('training_enrollments.employee_id', $employee->id)
                    ->where('training_sessions.start_date', '>=', now()->subMonth())
                    ->select('training_sessions.*', 'training_enrollments.id as enrollment_id')
                    ->get();

                foreach ($enrollments as $session) {
                    $event = CalendarEvent::query()->updateOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'source_type' => 'training_session',
                            'source_id' => $session->id,
                            'provider' => $connection->provider,
                        ],
                        [
                            'title' => 'Formation : '.($session->title ?? 'Session'),
                            'starts_at' => $session->start_date,
                            'ends_at' => $session->end_date ?? $session->start_date,
                            'all_day' => false,
                            'sync_status' => 'pending',
                        ]
                    );

                    $pushed = $this->pushEventToProvider($connection, $event);
                    if ($pushed) {
                        $synced++;
                    }
                }

                $connection->update(['last_synced_at' => now()]);
            } catch (\Throwable $e) {
                Log::error('Calendar sync training failed', [
                    'employee_id' => $employee->id,
                    'provider' => $connection->provider,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $synced;
    }

    public function getEvents(Employee $employee, string $from, string $to): \Illuminate\Support\Collection
    {
        return CalendarEvent::query()
            ->where('employee_id', $employee->id)
            ->where('starts_at', '>=', $from)
            ->where('starts_at', '<=', $to)
            ->orderBy('starts_at')
            ->get();
    }

    private function pushEventToProvider(CalendarConnection $connection, CalendarEvent $event): bool
    {
        try {
            if ($connection->provider === 'google') {
                return $this->pushToGoogle($connection, $event);
            }

            if ($connection->provider === 'outlook') {
                return $this->pushToOutlook($connection, $event);
            }

            $event->update(['sync_status' => 'synced']);

            return true;
        } catch (\Throwable $e) {
            $event->update(['sync_status' => 'failed']);
            Log::warning('Calendar event push failed', [
                'event_id' => $event->id,
                'provider' => $connection->provider,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function pushToGoogle(CalendarConnection $connection, CalendarEvent $event): bool
    {
        $calendarId = $connection->calendar_id ?? 'primary';
        $accessToken = $connection->access_token ? decrypt($connection->access_token) : null;

        if (empty($accessToken)) {
            return false;
        }

        $payload = [
            'summary' => $event->title,
            'description' => $event->description ?? '',
            'start' => $event->all_day
                ? ['date' => $event->starts_at->format('Y-m-d')]
                : ['dateTime' => $event->starts_at->toIso8601String()],
            'end' => $event->all_day
                ? ['date' => $event->ends_at->format('Y-m-d')]
                : ['dateTime' => $event->ends_at->toIso8601String()],
        ];

        $url = 'https://www.googleapis.com/calendar/v3/calendars/'.urlencode($calendarId).'/events';

        if ($event->external_event_id) {
            $response = Http::withToken($accessToken)->put($url.'/'.$event->external_event_id, $payload);
        } else {
            $response = Http::withToken($accessToken)->post($url, $payload);
        }

        if ($response->successful()) {
            $event->update([
                'external_event_id' => $response->json('id'),
                'sync_status' => 'synced',
            ]);

            return true;
        }

        return false;
    }

    private function pushToOutlook(CalendarConnection $connection, CalendarEvent $event): bool
    {
        $accessToken = $connection->access_token ? decrypt($connection->access_token) : null;

        if (empty($accessToken)) {
            return false;
        }

        $payload = [
            'subject' => $event->title,
            'body' => ['contentType' => 'Text', 'content' => $event->description ?? ''],
            'start' => [
                'dateTime' => $event->starts_at->format('Y-m-d\TH:i:s'),
                'timeZone' => 'UTC',
            ],
            'end' => [
                'dateTime' => $event->ends_at->format('Y-m-d\TH:i:s'),
                'timeZone' => 'UTC',
            ],
            'isAllDay' => $event->all_day,
        ];

        $url = 'https://graph.microsoft.com/v1.0/me/events';

        if ($event->external_event_id) {
            $response = Http::withToken($accessToken)->patch($url.'/'.$event->external_event_id, $payload);
        } else {
            $response = Http::withToken($accessToken)->post($url, $payload);
        }

        if ($response->successful()) {
            $event->update([
                'external_event_id' => $response->json('id'),
                'sync_status' => 'synced',
            ]);

            return true;
        }

        return false;
    }
}
