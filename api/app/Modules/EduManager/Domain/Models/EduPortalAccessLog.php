<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Journal d'audit des consultations du portail guardian — Issue #5829
 * (EDU-013). Une ligne par consultation (quel lien, quel guardian, quand).
 *
 * @property int $id
 * @property string $company_id
 * @property int $guardian_id
 * @property int $portal_link_id
 * @property Carbon $accessed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduPortalAccessLog extends Model
{
    use BelongsToCompany;

    protected $table = 'edu_portal_access_logs';

    protected $fillable = [
        'company_id',
        'guardian_id',
        'portal_link_id',
        'accessed_at',
    ];

    protected $casts = [
        'accessed_at' => 'datetime',
    ];
}
