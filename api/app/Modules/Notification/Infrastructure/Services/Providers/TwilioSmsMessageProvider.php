<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services\Providers;

use App\Contracts\Communication\MessageProviderInterface;
use App\Contracts\Communication\RetryableMessageProviderInterface;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * PA2-JOB-003 - Production SMS channel provider for `CommunicationService`,
 * backed by the Twilio Messages REST API.
 *
 * - Same contract as every other channel provider: `CommunicationService`
 *   only knows `MessageProviderInterface`, not that Twilio is underneath.
 * - Retry: opts into `RetryableMessageProviderInterface` so a transient
 *   network/5xx failure is retried by the caller with backoff, mirroring
 *   `MailMessageProvider` (PA2-COMM-007).
 * - Skips silently (status `skipped`) when the employee has no usable
 *   phone number or when Twilio credentials are not configured, so a
 *   misconfigured environment never blocks the rest of the notification
 *   fan-out.
 */
class TwilioSmsMessageProvider implements MessageProviderInterface, RetryableMessageProviderInterface
{
    public function __construct(
        private readonly int $maxAttempts = 3,
        private readonly int $baseRetryDelayMs = 500,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function send(Employee $employee, string $title, string $body, array $metadata = []): string
    {
        $to = $this->resolvePhoneNumber($employee);

        if ($to === null) {
            Log::info('Communication SMS dispatch skipped: no usable phone number', [
                'employee_id' => $employee->id,
            ]);

            return 'skipped';
        }

        $credentials = $this->credentials();

        if ($credentials === null) {
            Log::warning('Communication SMS dispatch skipped: Twilio credentials not configured');

            return 'skipped';
        }

        [$accountSid, $authToken, $from] = $credentials;

        try {
            $response = Http::asForm()
                ->withBasicAuth($accountSid, $authToken)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'From' => $from,
                    'To' => $to,
                    'Body' => $this->composeMessage($title, $body),
                ]);

            if ($response->successful()) {
                return 'queued';
            }

            Log::warning('Twilio SMS API error', [
                'employee_id' => $employee->id,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw new RuntimeException('Twilio SMS API returned HTTP '.$response->status());
        } catch (Throwable $exception) {
            Log::warning('Communication SMS dispatch attempt failed', [
                'employee_id' => $employee->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function maxAttempts(): int
    {
        return max(1, $this->maxAttempts);
    }

    public function retryDelayMs(int $attempt): int
    {
        // Exponential backoff: baseDelay * 2^(attempt-1).
        return $this->baseRetryDelayMs * (2 ** max(0, $attempt - 1));
    }

    /**
     * @return array{0: string, 1: string, 2: string}|null
     */
    private function credentials(): ?array
    {
        $accountSid = config('services.twilio.account_sid');
        $authToken = config('services.twilio.auth_token');
        $from = config('services.twilio.from');

        if (! is_string($accountSid) || $accountSid === ''
            || ! is_string($authToken) || $authToken === ''
            || ! is_string($from) || $from === '') {
            return null;
        }

        return [$accountSid, $authToken, $from];
    }

    private function resolvePhoneNumber(Employee $employee): ?string
    {
        $phone = $employee->phone ?? $employee->personal_phone ?? null;

        if (! is_string($phone) || trim($phone) === '') {
            return null;
        }

        return trim($phone);
    }

    private function composeMessage(string $title, string $body): string
    {
        if ($title === '') {
            return $body;
        }

        return $title.': '.$body;
    }
}
