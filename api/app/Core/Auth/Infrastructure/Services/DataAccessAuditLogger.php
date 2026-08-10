<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Throwable;

/**
 * Journalise les accès aux données RH (S-2, #1662).
 *
 * Deux niveaux :
 *  - `record()`            : accès génériques (anonymisation RGPD, listes,
 *                            profils employés) — catégorie `hr_data_access` ;
 *  - `recordSensitive()`   : accès en LECTURE aux données SENSIBLES (bulletins,
 *                            exports, journal, certificat, end-of-contract) —
 *                            catégorie `sensitive_data_access`, volume borné par
 *                            échantillonnage configurable
 *                            (`config('security.sensitive_access_logging')`).
 */
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
     * Trace un accès en lecture à une ressource sensible (qui/quoi/quand),
     * sans exploser le volume : échantillonnage + liste blanche configurables.
     *
     * - `enabled`        : active/désactive la journalisation (défaut true) ;
     * - `sampling_rate`  : pourcentage d'événements tracés (défaut 100) ;
     * - `resources`      : liste blanche des ressources sensibles tracées.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordSensitive(Request $request, Employee $actor, string $resource, ?Model $target = null, array $metadata = []): void
    {
        if (! $this->shouldLogSensitive($resource)) {
            return;
        }

        $this->record($request, $actor, 'sensitive_data_access', $target, [
            'category' => 'sensitive_data_access',
            'resource' => $resource,
            ...$metadata,
        ]);
    }

    private function shouldLogSensitive(string $resource): bool
    {
        $config = config('security.sensitive_access_logging', []);

        if (! (bool) ($config['enabled'] ?? true)) {
            return false;
        }

        $resources = (array) ($config['resources'] ?? []);
        if ($resources !== [] && ! in_array($resource, $resources, true)) {
            return false;
        }

        $rate = max(0, min(100, (int) ($config['sampling_rate'] ?? 100)));
        if ($rate >= 100) {
            return true;
        }
        if ($rate <= 0) {
            return false;
        }

        return random_int(1, 100) <= $rate;
    }

    private function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        return substr($userAgent, 0, 255);
    }
}
