<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Appartenance d'un contact à un segment CRM — Issue #5723.
 *
 * @property int $id
 * @property int $segment_id
 * @property string $company_id
 * @property int $contact_id
 * @property string $source
 * @property Carbon|null $built_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CrmSegmentMember extends Model
{
    use BelongsToCompany;

    protected $table = 'crm_segment_members';

    protected $fillable = [
        'segment_id',
        'company_id',
        'contact_id',
        'source',
        'built_at',
    ];

    protected $casts = [
        'segment_id' => 'integer',
        'contact_id' => 'integer',
        'built_at' => 'datetime',
    ];
}
