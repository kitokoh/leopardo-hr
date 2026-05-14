<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $name
 * @property string $model_type
 * @property array<mixed> $levels
 * @property float $auto_approve_below
 * @property int $escalation_hours
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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

    /** @return HasMany<ApprovalRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'workflow_id');
    }
}
