<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Segment CRM (définition versionnée) — Issue #5723.
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property string|null $description
 * @property array{operator: string, conditions: list<array{field: string, op: string, value: mixed}>} $definition
 * @property int $version
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CrmSegmentMember> $members
 *
 * @mixin Builder<static>
 */
class CrmSegment extends Model
{
    use BelongsToCompany;

    protected $table = 'crm_segments';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'definition',
        'version',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'definition' => 'array',
        'version' => 'integer',
        'is_active' => 'boolean',
        'created_by' => 'integer',
    ];

    /** @return HasMany<CrmSegmentMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(CrmSegmentMember::class, 'segment_id');
    }

    /** @return HasMany<CrmSegmentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(CrmSegmentVersion::class, 'segment_id');
    }
}
