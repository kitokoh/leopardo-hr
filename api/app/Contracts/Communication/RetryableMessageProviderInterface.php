<?php

declare(strict_types=1);

namespace App\Contracts\Communication;

/**
 * PA2-COMM-007 - Optional capability for `MessageProviderInterface`
 * implementations that support caller-driven retry (bounded attempt count
 * and backoff), so `CommunicationService` can retry a transient failure
 * without duplicating provider-specific retry policy.
 */
interface RetryableMessageProviderInterface
{
    /**
     * Maximum number of attempts (including the first one) the caller
     * should make before recording a final `failed` status.
     */
    public function maxAttempts(): int;

    /**
     * Milliseconds to wait before the next attempt, given the number of
     * attempts already made (1-indexed).
     */
    public function retryDelayMs(int $attempt): int;
}
