<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

<<<<<<< HEAD
use App\Core\Auth\Domain\Models\Employee;
=======
>>>>>>> origin/main
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
<<<<<<< HEAD
use Illuminate\Support\Carbon;

/**
 * Opportunité commerciale d'un tenant (CRM client V0, issue #5709).
 *
 * Rattachement pipeline + stage du MÊME tenant garanti par les FK
 * composites en base. Le cycle de vie (won/lost) est porté par le stage ;
 * `won_at`/`lost_at` horodatent la transition pour l'analytique.
 *
 * @property int $id
 * @property string $company_id
 * @property int $pipeline_id
 * @property int $stage_id
 * @property string $name
 * @property int|null $account_id
 * @property int|null $converted_from_lead_id
 * @property string|null $amount
 * @property string|null $currency
 * @property string|null $expected_close_date
 * @property int|null $owner_id
 * @property string|null $source
 * @property string|null $description
 * @property Carbon|null $won_at
 * @property Carbon|null $lost_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
=======

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
>>>>>>> origin/main
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
<<<<<<< HEAD
        'stage_id',
        'name',
        'account_id',
        'converted_from_lead_id',
        'amount',
        'currency',
        'expected_close_date',
        'owner_id',
        'source',
        'description',
        'won_at',
        'lost_at',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expected_close_date' => 'date',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<CrmPipeline, $this> */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'pipeline_id');
    }

    /** @return BelongsTo<CrmPipelineStage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmPipelineStage::class, 'stage_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'owner_id');
    }

    public function isWon(): bool
    {
        return $this->stage->is_won ?? false;
    }

    public function isLost(): bool
    {
        return $this->stage->is_lost ?? false;
=======
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
>>>>>>> origin/main
    }
}
