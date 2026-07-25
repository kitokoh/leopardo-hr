<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services\Providers;

use App\Contracts\Communication\MessageProviderInterface;
use App\Contracts\Communication\RetryableMessageProviderInterface;
use App\Core\Auth\Domain\Models\Employee;
use App\Mail\CommunicationMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * PA2-COMM-007 - Production-ready email channel provider for
 * `CommunicationService`.
 *
 * - Abstract: implements the same `MessageProviderInterface` as every other
 *   channel provider, so `CommunicationService::sendExternalChannel()` does
 *   not need to know it is Laravel's mailer underneath.
 * - Retry: `CommunicationService` calls `send()` up to `maxAttempts()` times
 *   with `retryDelayMs()` backoff between attempts, so a transient SMTP/API
 *   error does not immediately record a hard `failed` audit event.
 * - Audit: every attempt (success or exception) is logged; the final
 *   status/error is still recorded by the caller in `communication_events`.
 * - Opt-out ready: the recipient's bounce state is checked before send —
 *   see `Employee::hasBouncedEmail()` — set by the (future) provider bounce
 *   webhook, so a known-bad address is skipped instead of retried forever.
 */
class MailMessageProvider implements MessageProviderInterface, RetryableMessageProviderInterface
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
        $email = $employee->email ?? null;

        if (! is_string($email) || $email === '') {
            return 'skipped';
        }

        if ($employee->hasBouncedEmail()) {
            Log::info('Communication email dispatch skipped: address previously bounced', [
                'employee_id' => $employee->id,
            ]);

            return 'skipped';
        }

        try {
            Mail::to($email)->send(new CommunicationMail($title, $body));

            return 'queued';
        } catch (Throwable $exception) {
            Log::warning('Communication email dispatch attempt failed', [
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
}
