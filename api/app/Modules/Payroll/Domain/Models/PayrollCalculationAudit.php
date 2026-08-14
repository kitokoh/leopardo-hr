<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Issue #1874 — enregistrement d'audit immuable d'un calcul de paie.
 *
 * Une ligne par corrélation (run de paie ou simulation). Contexte de
 * règles (pays, version, identifiant), paramètres d'entrée agrégés NON
 * sensibles, résultats agrégés, statut de résolution et horodatage —
 * de quoi expliquer/reproduire/auditer un résultat de paie sans jamais
 * exposer de données individuelles ni de secrets (docs/payroll/AUDIT.md).
 *
 * @property int $id
 * @property string $correlation_id
 * @property string|null $company_id
 * @property string $actor_type
 * @property int|null $actor_id
 * @property string $country_code
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property string|null $rules_version
 * @property string|null $rules_identifier
 * @property array<string, mixed>|null $input_snapshot
 * @property array<string, mixed>|null $result_snapshot
 * @property string $status
 * @property string|null $error_message
 * @property Carbon $created_at
 *
 * @mixin Builder<static>
 */
class PayrollCalculationAudit extends Model
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_VALIDATION_ERROR = 'validation_error';

    public const STATUS_RULE_MISSING = 'rule_missing';

    public const STATUS_PROVIDER_ERROR = 'provider_error';

    public const STATUS_FALLBACK_FORBIDDEN = 'fallback_forbidden';

    public const ACTOR_USER = 'user';

    public const ACTOR_JOB = 'job';

    use BelongsToCompany;

    public $timestamps = false;

    protected $table = 'payroll_calculation_audits';

    protected $fillable = [
        'correlation_id',
        'company_id',
        'actor_type',
        'actor_id',
        'country_code',
        'period_start',
        'period_end',
        'rules_version',
        'rules_identifier',
        'input_snapshot',
        'result_snapshot',
        'status',
        'error_message',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'input_snapshot' => 'array',
        'result_snapshot' => 'array',
        'created_at' => 'datetime',
    ];
}
