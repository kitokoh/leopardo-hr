<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Version de définition d'un segment CRM — Issue #5723 (historique
 * reproductible : chaque version figée permet de rejouer le snapshot).
 *
 * @property int $id
 * @property int $segment_id
 * @property int $version
 * @property array{operator: string, conditions: list<array{field: string, op: string, value: mixed}>} $definition
 * @property int|null $changed_by
 * @property Carbon $changed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CrmSegmentVersion extends Model
{
    protected $table = 'crm_segment_versions';

    public $timestamps = true;

    protected $fillable = [
        'segment_id',
        'version',
        'definition',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'segment_id' => 'integer',
        'version' => 'integer',
        'definition' => 'array',
        'changed_by' => 'integer',
        'changed_at' => 'datetime',
    ];
}
