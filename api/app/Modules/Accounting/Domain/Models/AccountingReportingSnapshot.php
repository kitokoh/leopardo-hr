<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Snapshot horodaté d'un read model de reporting — issue #6243 (BC-22-D10).
 *
 * Matérialisation versionnée d'un read model pour une période donnée :
 *   - clé unique `(company_id, report, period_from, period_to)` ;
 *   - `version` incrémentée uniquement quand le contenu change (deux
 *     recomputes identiques → même version, idempotence) ;
 *   - `refreshed_at` = fraîcheur de la donnée, exposée à l'API ;
 *   - `payload` JSONB = agrégats du read model (déterministe, pas de PII).
 *
 * @property int $id
 * @property string $company_id
 * @property string $report
 * @property Carbon $period_from
 * @property Carbon $period_to
 * @property int $version
 * @property array<string, mixed> $payload
 * @property Carbon $refreshed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class AccountingReportingSnapshot extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_reporting_snapshots';

    protected $fillable = [
        'company_id',
        'report',
        'period_from',
        'period_to',
        'version',
        'payload',
        'refreshed_at',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'version' => 'integer',
        'payload' => 'array',
        'refreshed_at' => 'datetime',
    ];
}
