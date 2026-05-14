<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Throwable;

class DataAccessAuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(Request $request, Employee $actor, string $action, ?Model $target = null, array $metadata = []): void
    {
        try {
            AuditLog::query()->create([
                'company_id' => $actor->company_id,
                'user_id' => $actor->id,
                'action' => $action,
                'auditable_type' => $target?->getMorphClass(),
                'auditable_id' => $target?->getKey(),
                'old_values' => null,
                'new_values' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $this->truncateUserAgent($request->userAgent()),
                'metadata' => [
                    'category' => 'hr_data_access',
                    'route' => optional($request->route())->getName() ?? $request->path(),
                    ...$metadata,
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        return substr($userAgent, 0, 255);
    }
}
