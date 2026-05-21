<?php

namespace App\Services\Communication;

use App\Contracts\Communication\MessageProviderInterface;
use App\Models\CommunicationEvent;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Services\Communication\Providers\AuditMessageProvider;
use App\Services\PushNotificationService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CommunicationService
{
    public function __construct(
        private readonly PushNotificationService $pushNotifications,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @param  list<string>|null  $channels
     * @return array<string, mixed>
     */
    public function notifyEmployee(Employee $employee, string $templateKey, array $context = [], ?array $channels = null): array
    {
        $template = $this->template($templateKey);
        $preference = $this->preferencesFor($employee);
        $requestedChannels = $this->normalizeChannels($channels ?? config('communication.default_channels', ['app', 'push']));
        $category = (string) ($context['category'] ?? $template['category'] ?? 'system');
        $title = (string) ($context['title'] ?? $template['title']);
        $body = (string) ($context['body'] ?? $template['body']);
        $metadata = $this->sanitizeMetadata($context);

        $notification = null;
        $results = [];

        foreach ($requestedChannels as $channel) {
            if (! $this->allows($preference, $channel, $category)) {
                $results[$channel] = 'skipped';
                $this->recordEvent($employee, $notification, $templateKey, $channel, 'skipped', $metadata, 'Preference disabled.');

                continue;
            }

            if ($channel === 'app') {
                $notification = Notification::query()->create([
                    'company_id' => (string) $employee->company_id,
                    'employee_id' => $employee->id,
                    'type' => $category,
                    'title' => $title,
                    'body' => $body,
                    'data' => $metadata,
                    'is_read' => false,
                    'created_at' => now(),
                ]);
                $results[$channel] = 'sent';
                $this->recordEvent($employee, $notification, $templateKey, $channel, 'sent', $metadata);

                continue;
            }

            $results[$channel] = $this->sendExternalChannel($employee, $notification, $templateKey, $channel, $title, $body, $metadata);
        }

        return [
            'notification_id' => $notification?->id,
            'results' => $results,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function template(string $templateKey): array
    {
        $templates = config('communication.templates', []);
        $template = is_array($templates) ? Arr::get($templates, $templateKey) : null;

        if (! is_array($template)) {
            $fallback = Arr::get((array) $templates, 'generic', []);

            return is_array($fallback) ? $fallback : [
                'category' => 'system',
                'title' => 'Notification Leopardo RH',
                'body' => 'Une nouvelle information est disponible dans votre espace.',
            ];
        }

        return $template;
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
                'locale' => 'fr',
                'timezone' => $employee->company?->timezone,
                'categories' => [
                    'hr' => true,
                    'payroll' => true,
                    'security' => true,
                    'system' => true,
                    'marketing' => false,
                ],
            ]
        );
    }

    /**
     * @param  list<string>  $channels
     * @return list<string>
     */
    private function normalizeChannels(array $channels): array
    {
        $allowed = ['app', 'email', 'push', 'sms', 'whatsapp'];
        $normalized = [];

        foreach ($channels as $channel) {
            if (is_string($channel) && in_array($channel, $allowed, true) && ! in_array($channel, $normalized, true)) {
                $normalized[] = $channel;
            }
        }

        return $normalized === [] ? ['app'] : $normalized;
    }

    private function allows(NotificationPreference $preference, string $channel, string $category): bool
    {
        $flag = $channel.'_enabled';
        $channelAllowed = (bool) ($preference->{$flag} ?? false);

        if (! $channelAllowed) {
            return false;
        }

        $categories = $preference->categories;

        return ! is_array($categories) || (bool) ($categories[$category] ?? true);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $context): array
    {
        $allowedKeys = config('communication.public_metadata_keys', []);
        $allowedKeys = is_array($allowedKeys) ? $allowedKeys : [];
        $metadata = [];

        foreach ($allowedKeys as $key) {
            if (is_string($key) && array_key_exists($key, $context)) {
                $metadata[$key] = $context[$key];
            }
        }

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function sendExternalChannel(
        Employee $employee,
        ?Notification $notification,
        string $templateKey,
        string $channel,
        string $title,
        string $body,
        array $metadata
    ): string {
        try {
            $status = match ($channel) {
                'push' => $this->sendPush($employee, $title, $body, $metadata),
                'email' => $this->sendEmail($employee, $title, $body),
                'sms', 'whatsapp' => $this->providerFor($channel)->send($employee, $title, $body, $metadata),
                default => 'skipped',
            };

            $this->recordEvent($employee, $notification, $templateKey, $channel, $status, $metadata);

            return $status;
        } catch (\Throwable $exception) {
            Log::warning('Communication channel dispatch failed', [
                'employee_id' => $employee->id,
                'channel' => $channel,
                'error' => $exception->getMessage(),
            ]);
            $this->recordEvent($employee, $notification, $templateKey, $channel, 'failed', $metadata, $exception->getMessage());

            return 'failed';
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function sendPush(Employee $employee, string $title, string $body, array $metadata): string
    {
        $sent = $this->pushNotifications->sendToEmployee($employee, $title, $body, $metadata);

        return $sent > 0 ? 'sent' : 'skipped';
    }

    private function sendEmail(Employee $employee, string $title, string $body): string
    {
        $email = $employee->email ?? null;

        if (! is_string($email) || $email === '') {
            return 'skipped';
        }

        Mail::raw($body, static function ($message) use ($email, $title): void {
            $message->to($email)->subject($title);
        });

        return 'queued';
    }

    private function providerFor(string $channel): MessageProviderInterface
    {
        $configured = config('communication.providers.'.$channel, 'audit');

        if ($configured !== 'audit') {
            Log::warning('Communication provider not implemented yet, falling back to audit-only provider', [
                'channel' => $channel,
                'provider' => $configured,
            ]);
        }

        return new AuditMessageProvider;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordEvent(
        Employee $employee,
        ?Notification $notification,
        string $templateKey,
        string $channel,
        string $status,
        array $metadata,
        ?string $errorMessage = null
    ): void {
        CommunicationEvent::query()->create([
            'company_id' => (string) $employee->company_id,
            'employee_id' => $employee->id,
            'notification_id' => $notification?->id,
            'event_name' => 'communication_dispatched',
            'channel' => $channel,
            'status' => $status,
            'provider' => config('communication.providers.'.$channel),
            'template_key' => $templateKey,
            'metadata' => $metadata,
            'error_message' => $errorMessage,
            'occurred_at' => now(),
        ]);
    }
}
