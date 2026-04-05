<?php

namespace App\Http\Middleware\Web;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureManagerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $employee = $request->user();

        if (! $employee || ! method_exists($employee, 'isManager') || ! $employee->isManager()) {
            abort(403);
        }

        return $next($request);
    }
}
