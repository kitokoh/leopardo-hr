<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalWorkflow extends Model
{
    use BelongsToCompany;

    protected $table = 'approval_workflows';

    protected $fillable = [
        'company_id',
        'name',
        'model_type',
        'levels',
        'auto_approve_below',
        'escalation_hours',
        'active',
    ];

    protected $casts = [
        'levels' => 'array',
        'auto_approve_below' => 'float',
        'active' => 'boolean',
    ];

    public function requests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'workflow_id');
    }
}
