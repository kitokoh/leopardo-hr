<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $approval_request_id
 * @property int $level
 * @property int $approver_id
 * @property string $decision
 * @property string|null $comment
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property \Illuminate\Support\Carbon $created_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class ApprovalDecision extends Model
{
    protected $fillable = [
        'approval_request_id',
        'level',
        'approver_id',
        'decision',
        'comment',
        'decided_at',
    ];

    public const UPDATED_AT = null;

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<ApprovalRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }
}

