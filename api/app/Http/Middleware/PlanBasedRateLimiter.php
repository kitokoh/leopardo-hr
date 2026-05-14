<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class PlanBasedRateLimiter
{
    /**
     * Rate limits per plan tier (requests per minute).
     *
     * @var array<string, int>
     */
    private const PLAN_LIMITS = [
        'free' => 60,
        'starter' => 120,
        'professional' => 300,
        'enterprise' => 1000,
    ];

    private const DEFAULT_LIMIT = 60;

    private const CACHE_TTL_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $employee = $request->user();
        if (! $employee instanceof Employee || ! $employee->company_id) {
            /** @var Response */
            return $next($request);
        }

        $companyId = (string) $employee->company_id;
        $limit = $this->resolveLimit($companyId);
        $key = 'plan_rate:'.$companyId;
        $current = is_numeric(Cache::get($key)) ? (int) Cache::get($key) : 0;

        if ($current >= $limit) {
            return response()->json([
                'error' => 'RATE_LIMIT_EXCEEDED',
                'message' => __('Too many requests. Your plan allows :limit requests per minute.', ['limit' => $limit]),
                'retry_after' => 60,
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => (string) $limit,
                'X-RateLimit-Remaining' => '0',
                'Retry-After' => '60',
            ]);
        }

        Cache::put($key, $current + 1, now()->addMinute());

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $limit - $current - 1));
        $response->headers->set('X-RateLimit-Plan', $this->resolvePlanName($companyId));

        return $response;
    }

    private function resolveLimit(string $companyId): int
    {
        $planName = $this->resolvePlanName($companyId);

        return self::PLAN_LIMITS[$planName] ?? self::DEFAULT_LIMIT;
    }

    private function resolvePlanName(string $companyId): string
    {
        /** @var string */
        return Cache::remember(
            'company_plan:'.$companyId,
            self::CACHE_TTL_SECONDS,
            function () use ($companyId): string {
                $subscription = Subscription::query()
                    ->where('company_id', $companyId)
                    ->where('status', 'active')
                    ->first(['plan']);

                if (! $subscription) {
                    return 'free';
                }

                return strtolower((string) $subscription->plan);
            }
        );
    }
}
