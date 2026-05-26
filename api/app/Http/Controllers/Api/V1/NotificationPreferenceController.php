<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationPreferenceResource;
use App\Models\CommunicationEvent;
use App\Models\Employee;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        return (new NotificationPreferenceResource($this->preferencesFor($employee)))->response();
    }

    public function update(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $validated = $request->validate([
            'app_enabled' => ['sometimes', 'boolean'],
            'email_enabled' => ['sometimes', 'boolean'],
            'push_enabled' => ['sometimes', 'boolean'],
            'sms_enabled' => ['sometimes', 'boolean'],
            'whatsapp_enabled' => ['sometimes', 'boolean'],
            'locale' => ['sometimes', 'nullable', 'in:fr,ar,en,tr'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['boolean'],
            'quiet_hours' => ['sometimes', 'array'],
            'quiet_hours.enabled' => ['sometimes', 'boolean'],
            'quiet_hours.start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'quiet_hours.end' => ['sometimes', 'nullable', 'date_format:H:i'],
        ]);

        $preferences = $this->preferencesFor($employee);
        $preferences->fill($validated);
        $preferences->save();

        CommunicationEvent::query()->create([
            'company_id' => (string) $employee->company_id,
            'employee_id' => $employee->id,
            'event_name' => 'notification_preferences_updated',
            'channel' => 'app',
            'status' => 'recorded',
            'metadata' => [
                'channels' => [
                    'app' => $preferences->app_enabled,
                    'email' => $preferences->email_enabled,
                    'push' => $preferences->push_enabled,
                    'sms' => $preferences->sms_enabled,
                    'whatsapp' => $preferences->whatsapp_enabled,
                ],
            ],
            'occurred_at' => now(),
        ]);

        return (new NotificationPreferenceResource($preferences->fresh()))->response();
    }

    private function preferencesFor(Employee $employee): NotificationPreference
    {
        return NotificationPreference::query()->firstOrCreate(
            ['employee_id' => $employee->id],
            [
                'company_id' => (string) $employee->company_id,
                'app_enabled' => true,
                'email_enabled' => true,
                'push_enabled' => true,
                'sms_enabled' => false,
                'whatsapp_enabled' => false,
                'locale' => $employee->preferred_language ?: null,
                'timezone' => optional($employee->company)->timezone,
                'categories' => [
                    'hr' => true,
                    'payroll' => true,
                    'security' => true,
                    'system' => true,
                    'marketing' => false,
                ],
                'quiet_hours' => [
                    'enabled' => false,
                    'start' => null,
                    'end' => null,
                ],
            ],
        );
    }
}
