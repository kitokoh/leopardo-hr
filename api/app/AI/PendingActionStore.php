<?php

declare(strict_types=1);

namespace App\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PendingActionStore
{
    private const CACHE_PREFIX = 'ai_pending_action:';

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function store(string $companyId, int $userId, string $toolName, array $arguments): string
    {
        $id = (string) Str::uuid();

        Cache::put($this->cacheKey($id), [
            'company_id' => $companyId,
            'user_id' => $userId,
            'tool' => $toolName,
            'arguments' => $arguments,
        ], now()->addMinutes($this->ttlMinutes()));

        return $id;
    }

    /**
     * @return array{company_id: string, user_id: int, tool: string, arguments: array<string, mixed>}|null
     */
    public function pull(string $id, string $companyId, int $userId): ?array
    {
        /** @var array{company_id: string, user_id: int, tool: string, arguments: array<string, mixed>}|null $payload */
        $payload = Cache::get($this->cacheKey($id));

        if ($payload === null) {
            return null;
        }

        if ($payload['company_id'] !== $companyId || $payload['user_id'] !== $userId) {
            return null;
        }

        Cache::forget($this->cacheKey($id));

        return $payload;
    }

    public function forget(string $id): void
    {
        Cache::forget($this->cacheKey($id));
    }

    private function cacheKey(string $id): string
    {
        return self::CACHE_PREFIX.$id;
    }

    private function ttlMinutes(): int
    {
        $configured = config('ai.pending_action_ttl_minutes', 15);

        return max(1, is_numeric($configured) ? (int) $configured : 15);
    }
}
