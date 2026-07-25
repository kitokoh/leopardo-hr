<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services\Providers;

use App\Contracts\Communication\MessageProviderInterface;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PA2-COMM-008 - Real WhatsApp send path via the Meta WhatsApp Business
 * Cloud API. Only ever instantiated by
 * `CommunicationService::providerFor()` once both the phone number id and
 * the access token secrets are configured; every other case (missing
 * secret, missing recipient consent/phone number, provider-side failure)
 * is handled by the caller before/around this class so that a
 * misconfigured or unreachable provider never blocks the audit trail.
 */
class WhatsappCloudApiMessageProvider implements MessageProviderInterface
{
    public function __construct(
        private readonly string $phoneNumberId,
        private readonly string $accessToken,
        private readonly string $apiBaseUrl = 'https://graph.facebook.com/v19.0',
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function send(Employee $employee, string $title, string $body, array $metadata = []): string
    {
        $recipient = $this->resolveRecipientNumber($employee);

        if ($recipient === null) {
            Log::warning('WhatsApp Cloud API dispatch skipped: employee has no usable phone number', [
                'employee_id' => $employee->id,
            ]);

            return 'skipped';
        }

        $text = $title !== '' ? $title.PHP_EOL.PHP_EOL.$body : $body;

        try {
            $response = Http::withToken($this->accessToken)
                ->post("{$this->apiBaseUrl}/{$this->phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $recipient,
                    'type' => 'text',
                    'text' => ['body' => $text],
                ]);

            if ($response->successful()) {
                return 'queued';
            }

            Log::warning('WhatsApp Cloud API rejected the message', [
                'employee_id' => $employee->id,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return 'failed';
        } catch (Throwable $exception) {
            Log::warning('WhatsApp Cloud API dispatch failed', [
                'employee_id' => $employee->id,
                'error' => $exception->getMessage(),
            ]);

            return 'failed';
        }
    }

    /**
     * Meta's Cloud API expects E.164 (no leading `+`, no separators). The
     * employee's personal phone is preferred (their own device) over the
     * work phone, matching the recipient the employee actually consented
     * for when they opted into WhatsApp notifications.
     */
    private function resolveRecipientNumber(Employee $employee): ?string
    {
        $raw = $employee->personal_phone ?: $employee->phone;

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9]/', '', $raw);

        return $normalized !== '' ? $normalized : null;
    }
}
