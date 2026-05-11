<?php

namespace App\Http\Middleware\AI;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AIRateLimiter
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $companyId = $user->company_id;
        $key = "ai_quota:{$companyId}:".now()->format('Y-m');

        $current = (int) Cache::get($key, 0);

        /** @var array<string, int|null> $quotas */
        $quotas = config('ai.quotas', []);
        $plan = 'starter';
        $limit = $quotas[$plan] ?? 50;

        if ($current >= $limit) {
            return response()->json([
                'message' => 'AI quota exceeded for this month.',
                'quota' => $limit,
                'used' => $current,
            ], 429);
        }

        Cache::put($key, $current + 1, now()->endOfMonth());

        return $next($request);
    }
}
