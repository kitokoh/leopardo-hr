<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services;

use App\Contracts\Communication\MessageProviderInterface;
use App\Contracts\Communication\RetryableMessageProviderInterface;
use App\Core\Auth\Domain\Models\Employee;
use App\Jobs\SendPushNotificationJob;
use App\Modules\Notification\Domain\Models\CommunicationEvent;
use App\Modules\Notification\Domain\Models\Notification;
use App\Modules\Notification\Domain\Models\NotificationPreference;
use App\Modules\Notification\Infrastructure\Services\Providers\AuditMessageProvider;
use App\Modules\Notification\Infrastructure\Services\Providers\WhatsappCloudApiMessageProvider;
use App\Support\I18nCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class CommunicationService
{
    public function __construct(
        private readonly PushNotificationService $pushNotifications,
        private readonly NotificationPreferenceProvisioner $preferences,
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
        $locale = $this->localeFor($employee, $context);
        $title = (string) ($context['title'] ?? $this->translate($template, 'title_key', $locale, $context));
        $body = (string) ($context['body'] ?? $this->translate($template, 'body_key', $locale, $context));
        $metadata = $this->sanitizeMetadata($context);

        $notification = null;
        $results = [];

        foreach ($requestedChannels as $channel) {
            if ($this->allows($preference, $channel, $category) === false) {
                $results[$channel] = 'skipped';
                $reason = $channel === 'whatsapp' && $preference->{$channel.'_enabled'} && ! $preference->hasWhatsappConsent()
                    ? 'WhatsApp consent missing.'
                    : 'Preference disabled.';
                $this->recordEvent($employee, $notification, $templateKey, $channel, 'skipped', $metadata, $reason);

                continue;
            }

            if ($this->shouldSkipForQuietHours($preference, $channel, $category)) {
                $results[$channel] = (string) config('communication.quiet_hours.defer_status', 'skipped');
                $this->recordEvent($employee, $notification, $templateKey, $channel, $results[$channel], $metadata, 'Quiet hours active.');

                continue;
            }

            if ($this->quotaExceeded($employee, $channel)) {
                $results[$channel] = 'skipped';
                $this->recordEvent($employee, $notification, $templateKey, $channel, 'skipped', $metadata, 'Monthly channel quota exceeded.');

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

        if (is_array($template) === false) {
            $fallback = Arr::get((array) $templates, 'generic', []);

            return is_array($fallback) ? $fallback : [
                'category' => 'system',
                'title_key' => 'notifications.generic_title',
                'body_key' => 'notifications.generic_body',
            ];
        }

        return $template;
    }

    /**
     * Resolves the recipient's locale for this notification: an explicit
     * `locale` in the caller context wins, then the employee's own
     * `preferred_language`, then the tenant company's default language.
     *
     * @param  array<string, mixed>  $context
     */
    private function localeFor(Employee $employee, array $context): string
    {
        $requested = $context['locale'] ?? $employee->preferred_language ?? $employee->company?->language;

        return I18nCatalog::normalizeLocale(is_string($requested) ? $requested : null);
    }

    /**
     * Resolves a template's title/body from its translation key, forwarding
     * only the caller context keys declared in `vars` as `trans()`
     * replacement parameters (e.g. `:task`, `:author`).
     *
     * @param  array<string, mixed>  $template
     * @param  array<string, mixed>  $context
     */
    private function translate(array $template, string $keyField, string $locale, array $context): string
    {
        $key = $template[$keyField] ?? null;

        if (is_string($key) === false || $key === '') {
            return '';
        }

        $vars = $template['vars'] ?? [];
        $vars = is_array($vars) ? $vars : [];
        $replace = [];

        foreach ($vars as $var) {
            if (is_string($var) && array_key_exists($var, $context)) {
                $replace[$var] = (string) $context[$var];
            }
        }

        return trans($key, $replace, $locale);
    }

    private function preferencesFor(Employee $employee): NotificationPreference
    {
        return $this->preferences->ensureForEmployee($employee);
    }

    /**
     * @param  array<int, mixed>  $channels
     * @return list<string>
     */
    private function normalizeChannels(array $channels): array
    {
        $allowed = ['app', 'email', 'push', 'sms', 'whatsapp'];
        $normalized = [];

        foreach ($channels as $channel) {
            if (is_string($channel) && in_array($channel, $allowed, true) && in_array($channel, $normalized, true) === false) {
                $normalized[] = $channel;
            }
        }

        return $normalized === [] ? ['app'] : $normalized;
    }

    private function allows(NotificationPreference $preference, string $channel, string $category): bool
    {
        $flag = $channel.'_enabled';
        $channelAllowed = (bool) ($preference->{$flag} ?? false);

        if ($channelAllowed === false) {
            return false;
        }

        // PA2-COMM-008 - WhatsApp Business messaging requires an explicit,
        // timestamped opt-in distinct from the channel toggle itself (Meta
        // Cloud API policy). A recipient who enabled the channel but never
        // completed the separate consent step is never messaged.
        if ($channel === 'whatsapp' && $preference->hasWhatsappConsent() === false) {
            return false;
        }

        $categories = $preference->categories;

        return is_array($categories) === false || (bool) ($categories[$category] ?? true);
    }

    private function shouldSkipForQuietHours(NotificationPreference $preference, string $channel, string $category): bool
    {
        if ($channel === 'app') {
            return false;
        }

        $bypassCategories = config('communication.quiet_hours.bypass_categories', ['security']);
        $bypassCategories = is_array($bypassCategories) ? $bypassCategories : [];

        if (in_array($category, $bypassCategories, true)) {
            return false;
        }

        $quietHours = $preference->quiet_hours;

        if (is_array($quietHours) === false || (bool) ($quietHours['enabled'] ?? false) === false) {
            return false;
        }

        $start = $quietHours['start'] ?? null;
        $end = $quietHours['end'] ?? null;

        if (is_string($start) === false || is_string($end) === false || $start === '' || $end === '') {
            return false;
        }

        $timezone = is_string($preference->timezone) && $preference->timezone !== ''
            ? $preference->timezone
            : (string) config('app.timezone', 'UTC');

        $now = Carbon::now($timezone);
        $current = $now->format('H:i');

        if ($start === $end) {
            return true;
        }

        if ($start < $end) {
            return $current >= $start && $current < $end;
        }

        return $current >= $start || $current < $end;
    }

    private function quotaExceeded(Employee $employee, string $channel): bool
    {
        $limit = (int) config('communication.monthly_channel_quotas.'.$channel, 0);

        if ($limit <= 0) {
            return false;
        }

        $periodStart = now()->startOfMonth();

        $used = CommunicationEvent::query()
            ->where('company_id', (string) $employee->company_id)
            ->where('channel', $channel)
            ->whereIn('status', ['sent', 'queued'])
            ->where('occurred_at', '>=', $periodStart)
            ->count();

        return $used >= $limit;
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
                'email', 'sms', 'whatsapp' => $this->dispatchWithRetry($this->providerFor($channel), $employee, $title, $body, $metadata),
                default => 'skipped',
            };

            $this->recordEvent($employee, $notification, $templateKey, $channel, $status, $metadata);

            return $status;
        } catch (Throwable $exception) {
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
        SendPushNotificationJob::dispatch($employee->id, $title, $body, $metadata);

        return 'queued';
    }

    /**
     * PA2-COMM-007 - Bounded caller-side retry for providers that opt in via
     * `RetryableMessageProviderInterface` (currently `MailMessageProvider`).
     * Providers that do not implement it (audit fallback, WhatsApp Cloud
     * API) are called exactly once, unchanged from prior behaviour.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function dispatchWithRetry(
        MessageProviderInterface $provider,
        Employee $employee,
        string $title,
        string $body,
        array $metadata
    ): string {
        if (! $provider instanceof RetryableMessageProviderInterface) {
            return $provider->send($employee, $title, $body, $metadata);
        }

        $maxAttempts = $provider->maxAttempts();
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $provider->send($employee, $title, $body, $metadata);
            } catch (Throwable $exception) {
                $lastException = $exception;

                if ($attempt < $maxAttempts) {
                    $delayMs = $provider->retryDelayMs($attempt);

                    if ($delayMs > 0) {
                        usleep($delayMs * 1000);
                    }
                }
            }
        }

        throw $lastException;
    }

    private function providerFor(string $channel): MessageProviderInterface
    {
        $configured = config('communication.providers.'.$channel, 'audit');

        if ($channel === 'whatsapp' && $configured !== 'audit') {
            $provider = $this->whatsappCloudApiProvider();

            if ($provider !== null) {
                return $provider;
            }

            Log::warning('WhatsApp provider configured but secrets are missing, falling back to audit-only provider', [
                'channel' => $channel,
                'provider' => $configured,
            ]);

            return new AuditMessageProvider;
        }

        if ($configured !== 'audit') {
            Log::warning('Communication provider not implemented yet, falling back to audit-only provider', [
                'channel' => $channel,
                'provider' => $configured,
            ]);
        }

        return new AuditMessageProvider;
    }

    /**
     * PA2-COMM-008 - Only ever returns a real provider when both Meta Cloud
     * API secrets are configured; otherwise every WhatsApp dispatch stays
     * on the audit-only fallback so a missing secret never surfaces as a
     * hard failure to the caller.
     */
    private function whatsappCloudApiProvider(): ?WhatsappCloudApiMessageProvider
    {
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $accessToken = config('services.whatsapp.access_token');

        if (! is_string($phoneNumberId) || $phoneNumberId === '' || ! is_string($accessToken) || $accessToken === '') {
            return null;
        }

        $baseUrl = config('services.whatsapp.api_base_url', 'https://graph.facebook.com/v19.0');

        return new WhatsappCloudApiMessageProvider(
            $phoneNumberId,
            $accessToken,
            is_string($baseUrl) && $baseUrl !== '' ? $baseUrl : 'https://graph.facebook.com/v19.0',
        );
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
