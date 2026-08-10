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
        if (! $this->shouldSample($actor, $action)) {
            return;
        }

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
     * Échantillonnage configurable (S-2, #1662) — voir config/audit.php.
     *
     * Déterministe par (acteur, action) : un même acteur sur une même action
     * est toujours tracé ou jamais, ce qui rend le comportement testable et
     * évite les trous aléatoires dans la traçabilité d'un acteur donné.
     */
    private function shouldSample(Employee $actor, string $action): bool
    {
        $rate = (float) config('audit.data_access.sample_rate', 1.0);

        if ($rate >= 1.0) {
            return true;
        }

        if ($rate <= 0.0) {
            return false;
        }

        $bucket = (((int) crc32($action.'|'.$actor->id)) % 10000) / 10000;

        return $bucket < $rate;
    }

    private function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        return substr($userAgent, 0, 255);
    }
}

