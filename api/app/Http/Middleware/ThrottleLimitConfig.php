<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;

/**
 * Configuration d'une limite de throttling résolue par
 * `ResilientThrottleRequests` (DTO typé, équivalent de l'objet anonyme
 * construit par `Illuminate\Routing\Middleware\ThrottleRequests`).
 *
 * @internal
 */
final class ThrottleLimitConfig
{
    /**
     * @param  Closure(mixed): mixed|null  $afterCallback
     * @param  Closure(mixed, array<string, mixed>): mixed|null  $responseCallback
     */
    public function __construct(
        public readonly string $key,
        public readonly int $maxAttempts,
        public readonly int $decaySeconds,
        public readonly ?Closure $afterCallback = null,
        public readonly ?Closure $responseCallback = null,
    ) {}
}
