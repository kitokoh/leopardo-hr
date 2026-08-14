<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ADMIN-PAIE (issue #1813) — audit trail immuable des modifications de taux
 * légaux (barèmes fiscaux / cotisations sociales).
 *
 * Table append-only : aucun UPDATE/DELETE possible (trigger PostgreSQL
 * `tax_rate_change_log_no_mutation`). Le modèle n'expose que des INSERT ;
 * `$timestamps = false` (pas d'updated_at) et `$guarded = ['id']`.
 *
 * @property int $id
 * @property string $table_name
 * @property int $record_id
 * @property string $action
 * @property int|null $actor_id
 * @property string $actor_role
 * @property array<mixed>|null $previous_value
 * @property array<mixed> $new_value
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon $created_at
 */
class TaxRateChangeLog extends Model
{
    public const ACTION_CREATED = 'created';
    public const ACTION_SUBMITTED = 'submitted';
    public const ACTION_APPROVED = 'approved';
    public const ACTION_REJECTED = 'rejected';
    public const ACTION_SUPERSEDED = 'superseded';

    public $timestamps = false;

    protected $table = 'tax_rate_change_log';

    protected $guarded = ['id'];

    protected $casts = [
        'previous_value' => 'array',
        'new_value' => 'array',
        'created_at' => 'datetime',
    ];
}
