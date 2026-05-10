<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalDecision extends Model
{
    public $timestamps = false;

    protected $table = 'approval_decisions';

    protected $fillable = [
        'approval_request_id',
        'level',
        'approver_id',
        'decision',
        'comment',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }
}
