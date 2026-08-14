<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Audit trail immuable des modifications de taux légaux (issue #1813).
 *
 * Table append-only : aucun UPDATE/DELETE possible (trigger PostgreSQL).
 * Ce modèle ne doit jamais être modifié après création — un appel à
 * `save()`/`update()`/`delete()` lève une QueryException.
 *
 * @property int $id
 * @property string $table_name
 * @property int $record_id
 * @property string $action
 * @property int $actor_id
 * @property string $actor_role
 * @property array<string, mixed>|null $previous_value
 * @property array<string, mixed> $new_value
 * @property string|null $reason
 * @property Carbon $created_at
 *
 * @mixin Builder<static>
 */
class TaxRateChangeLog extends Model
{
    public const TABLE_TAX_SLABS = 'tax_slabs';

    public const TABLE_SOCIAL_CONTRIBUTIONS = 'social_contributions';

    public const ACTION_CREATED = 'created';

    public const ACTION_SUBMITTED = 'submitted';

    public const ACTION_APPROVED = 'approved';

    public const ACTION_REJECTED = 'rejected';

    public const ACTION_SUPERSEDED = 'superseded';

    protected $table = 'tax_rate_change_log';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'previous_value' => 'array',
        'new_value' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForRecord(Builder $query, string $tableName, int $recordId): Builder
    {
        return $query->where('table_name', $tableName)->where('record_id', $recordId);
    }
}
