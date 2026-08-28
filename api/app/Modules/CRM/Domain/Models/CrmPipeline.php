<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
<<<<<<< HEAD
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Pipeline commercial d'un tenant (CRM client V0, issue #5709).
 *
 * Un pipeline appartient à UNE entreprise (`company_id`) : le trait
 * BelongsToCompany scope automatiquement toutes les requêtes au tenant
 * courant (fail-closed sur la surface API tenant, #3727).
 *
 * Le CRM commercial Leopardo (Platform/Marketing) est distinct : ces
 * modèles ne le remplacent pas et ne s'en servent pas.
=======

/**
 * #5717/#5709 — Pipeline CRM client (tenant-scoped).
>>>>>>> origin/main
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
<<<<<<< HEAD
 * @property string|null $description
 * @property bool $is_default
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
=======
 * @property bool $is_default
 * @property array<mixed> $stages
>>>>>>> origin/main
 *
 * @mixin Builder<static>
 */
class CrmPipeline extends Model
{
    use BelongsToCompany;

    protected $table = 'crm_pipelines';

    protected $fillable = [
        'company_id',
        'name',
<<<<<<< HEAD
        'description',
        'is_default',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /** @return HasMany<CrmPipelineStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(CrmPipelineStage::class, 'pipeline_id');
    }

    /** @return HasMany<CrmOpportunity, $this> */
    public function opportunities(): HasMany
    {
        return $this->hasMany(CrmOpportunity::class, 'pipeline_id');
    }
=======
        'is_default',
        'stages',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'stages' => 'array',
    ];
>>>>>>> origin/main
}
