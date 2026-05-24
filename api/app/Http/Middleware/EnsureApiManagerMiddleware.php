<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiManagerMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     * @param  string  ...$roles  Allowed manager_role values (empty = any manager)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $employee = $request->user() ?? Auth::user();

        if (! $employee || ! method_exists($employee, 'isManager') || ! $employee->isManager()) {
            return response()->json([
                'error' => 'MANAGER_REQUIRED',
                'message' => 'This endpoint requires manager access.',
                'localized_message' => __('errors.MANAGER_REQUIRED', [], 'fr'),
            ], 403);
        }

        if ($roles !== [] && method_exists($employee, 'hasManagerRole') && ! $employee->hasManagerRole(...$roles)) {
            return response()->json([
                'error' => 'INSUFFICIENT_ROLE',
                'message' => 'Your manager role does not have access to this resource.',
                'localized_message' => __('errors.INSUFFICIENT_ROLE', [], 'fr'),
            ], 403);
        }

        return $next($request);
    }
}
