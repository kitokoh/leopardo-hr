<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * #5717/#5709 — Pipeline CRM client (tenant-scoped).
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property bool $is_default
 * @property array<mixed> $stages
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
        'is_default',
        'stages',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'stages' => 'array',
    ];
}
