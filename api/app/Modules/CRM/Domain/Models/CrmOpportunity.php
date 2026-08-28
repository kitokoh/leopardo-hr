<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * #5717/#5709 — Opportunité CRM client (tenant-scoped).
 *
 * @property int $id
 * @property string $company_id
 * @property string|null $pipeline_id
 * @property string|null $lead_id
 * @property string|null $owner_id
 * @property string $name
 * @property string $stage
 * @property string|null $amount
 * @property string|null $currency
 * @property string|null $expected_close_date
 * @property string $status
 * @property string|null $notes
 *
 * @mixin Builder<static>
 */
class CrmOpportunity extends Model
{
    use BelongsToCompany;

    protected $table = 'crm_opportunities';

    protected $fillable = [
        'company_id',
        'pipeline_id',
        'lead_id',
        'owner_id',
        'name',
        'stage',
        'amount',
        'currency',
        'expected_close_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'expected_close_date' => 'date',
    ];

    /** @return BelongsTo<CrmLead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }
}
