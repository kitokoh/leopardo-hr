<?php

declare(strict_types=1);

namespace App\Modules\Cabinet\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * DEP-BC20 (Documents & Evidence, #5896) — piste d'audit du Cabinet.
 *
 * Journalise les opérations sensibles sur les documents du Cabinet
 * (upload, suppression, déplacement) dans `audit_logs` (immuable, RGPD).
 * La suppression est auditable : l'entrée est écrite AVANT la suppression
 * du document pour conserver les références (nom, chemin, taille).
 *
 * La journalisation ne doit JAMAIS faire échouer l'opération métier :
 * toute erreur est reportée (log) et absorbée.
 */
final class CabinetAuditLogger
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Employee $actor,
        string $action,
        ?Model $target = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        try {
            AuditLog::query()->create([
                'company_id' => $actor->company_id,
                'user_id' => $actor->id,
                'action' => $action,
                'module' => 'cabinet',
                'auditable_type' => $target?->getMorphClass(),
                'auditable_id' => $target?->getKey(),
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
                'metadata' => [
                    'category' => 'cabinet_documents',
                    ...$metadata,
                ],
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
