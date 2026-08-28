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
 * Campagne marketing CRM (tenant) — Issue #5724.
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property string|null $description
 * @property string $channel
 * @property string $status
 * @property int|null $segment_id
 * @property list<int>|null $audience_snapshot
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int|null $created_by
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CrmCampaignSend> $sends
 *
 * @mixin Builder<static>
 */
class CrmCampaign extends Model
{
    use BelongsToCompany;

    protected $table = 'crm_campaigns';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'channel',
        'status',
        'segment_id',
        'audience_snapshot',
        'scheduled_at',
        'started_at',
        'finished_at',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'segment_id' => 'integer',
        'audience_snapshot' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'created_by' => 'integer',
        'metadata' => 'array',
    ];

    /** @return HasMany<CrmCampaignSend, $this> */
    public function sends(): HasMany
    {
        return $this->hasMany(CrmCampaignSend::class, 'campaign_id');
    }
}
