<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
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
                    'route' => $request->route()?->getName() ?? $request->path(),
                    ...$metadata,
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Trace une lecture de donnée sensible (bulletins, exports, journal,
     * certificat, fin de contrat — Spec S-2, #1662).
     *
     * Applique l'échantillonnage configurable `audit.sensitive_access.sampling`
     * (1.0 = 100 %, 0.0 = désactivé) pour borner le volume, et catégorise la
     * trace `sensitive_data_access` (interrogeable par `audit:sensitive-report`).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordSensitive(Request $request, Employee $actor, string $action, ?Model $target = null, array $metadata = []): void
    {
        $sampling = (float) config('audit.sensitive_access.sampling', 1.0);

        if ($sampling <= 0.0) {
            return;
        }

        if ($sampling < 1.0 && (mt_rand() / mt_getrandmax()) > $sampling) {
            return;
        }

        $this->record($request, $actor, $action, $target, [
            'category' => 'sensitive_data_access',
            ...$metadata,
        ]);
    }

    private function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        return substr($userAgent, 0, 255);
    }
}

