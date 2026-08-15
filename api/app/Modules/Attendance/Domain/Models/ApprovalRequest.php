<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $company_id
 * @property int $workflow_id
 * @property string $approvable_type
 * @property int $approvable_id
 * @property int $requester_id
 * @property int $current_level
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @mixin Builder<static>
 */
class ApprovalRequest extends Model
{
    protected $fillable = [
        'company_id',
        'workflow_id',
        'approvable_type',
        'approvable_id',
        'requester_id',
        'current_level',
        'status',
    ];

    /**
     * @param  Builder<ApprovalRequest>  $query
     * @return Builder<ApprovalRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /** @return BelongsTo<ApprovalWorkflow, $this> */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_id');
    }

    /** @return HasMany<ApprovalDecision, $this> */
    public function decisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class, 'approval_request_id');
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }
}
