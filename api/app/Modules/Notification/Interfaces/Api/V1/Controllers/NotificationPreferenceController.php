<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationPreferenceResource;
use App\Modules\Notification\Domain\Models\CommunicationEvent;
use App\Modules\Notification\Infrastructure\Services\NotificationPreferenceProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function __construct(private readonly NotificationPreferenceProvisioner $preferences) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        return (new NotificationPreferenceResource($this->preferences->ensureForEmployee($employee)))
            ->response()
            ->setStatusCode(200);
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
            'whatsapp_consent_given' => ['sometimes', 'boolean'],
            'locale' => ['sometimes', 'nullable', 'in:fr,ar,en,tr'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['boolean'],
            'quiet_hours' => ['sometimes', 'array'],
            'quiet_hours.enabled' => ['sometimes', 'boolean'],
            'quiet_hours.start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'quiet_hours.end' => ['sometimes', 'nullable', 'date_format:H:i'],
        ]);

        $preferences = $this->preferences->ensureForEmployee($employee);

        // PA2-COMM-008 - WhatsApp consent is a distinct, explicit,
        // server-timestamped opt-in: the employee can flip it on/off from
        // the client, but the exact instant is always recorded here, never
        // trusted from client input, and withdrawing consent clears the
        // timestamp so a later `hasWhatsappConsent()` check is unambiguous.
        if (array_key_exists('whatsapp_consent_given', $validated)) {
            $consentGiven = (bool) $validated['whatsapp_consent_given'];
            $validated['whatsapp_consent_at'] = $consentGiven ? now() : null;
        }

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
                'whatsapp_consent_given' => $preferences->whatsapp_consent_given,
            ],
            'occurred_at' => now(),
        ]);

        return (new NotificationPreferenceResource($preferences->fresh()))->response();
    }
}
