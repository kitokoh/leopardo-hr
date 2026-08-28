<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Campus / site d'un établissement scolaire — Issue #5818 (EDU-002).
 *
 * Tenant-scoped (`company_id`, schéma tenant). Distinct du CRM commercial
 * plateforme : les données scolaires restent isolées (spec §6.2).
 *
 * @property int $id
 * @property string $company_id
 * @property string $code
 * @property string $name
 * @property string|null $address
 * @property string $timezone
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, static> $nothing
 *
 * @mixin Builder<static>
 */
class EduCampus extends Model
{
    use BelongsToCompany;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_ARCHIVED,
    ];

    protected $table = 'edu_campuses';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'address',
        'timezone',
        'status',
    ];

    protected $casts = [
        'timezone' => 'string',
    ];
}
