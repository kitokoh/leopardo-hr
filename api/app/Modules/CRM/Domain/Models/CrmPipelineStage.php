<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * #5709 — Stage d'un pipeline CRM client (tenant-scoped).
 *
 * L'ordre est total dans un pipeline (UNIQUE pipeline_id + position) et une
 * étape est soit gagnante (is_won) soit perdante (is_lost), jamais les deux
 * (CHECK en base). La FK composite (pipeline_id, company_id) rend
 * impossible le rattachement d'un stage au pipeline d'un autre tenant.
 *
 * @property int $id
 * @property string $company_id
 * @property int $pipeline_id
 * @property string $name
 * @property int $position
 * @property string|null $color
 * @property bool $is_won
 * @property bool $is_lost
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CrmPipelineStage extends Model
{
    use BelongsToCompany;

    protected $table = 'crm_pipeline_stages';

    protected $fillable = [
        'company_id',
        'pipeline_id',
        'name',
        'position',
        'color',
        'is_won',
        'is_lost',
        'created_by',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_won' => 'boolean',
        'is_lost' => 'boolean',
    ];

    /** @return BelongsTo<CrmPipeline, $this> */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'pipeline_id');
    }
}
