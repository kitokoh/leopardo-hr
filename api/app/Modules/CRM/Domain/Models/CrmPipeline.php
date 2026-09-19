<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * #5709 / #7452 — Pipeline commercial CRM client (tenant-scoped).
 *
 * Le modèle manquait alors que la table `crm_pipelines` existe (migration
 * 2026_08_28_000100_5709) et que les tests de scoping tenant
 * (`CrmModelsTenantScopingTest`) comme le read model dashboard s'y réfèrent.
 * `stages` (JSON embarqué de la génération V0, nullable depuis #7452) est
 * conservé pour compat lecture ; la génération vivante modélise les étapes
 * dans `crm_pipeline_stages` (relation `stageRecords()`).
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property bool $is_default
 * @property array<int, mixed>|null $stages
 * @property string|null $description
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CrmPipeline extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $table = 'crm_pipelines';

    protected $fillable = [
        'company_id',
        'name',
        'is_default',
        'stages',
        'description',
        'created_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'stages' => 'array',
    ];

    /** @return HasMany<CrmPipelineStage, $this> */
    public function stageRecords(): HasMany
    {
        return $this->hasMany(CrmPipelineStage::class, 'pipeline_id')->orderBy('position');
    }
}
