<?php

declare(strict_types=1);

namespace App\Modules\HR\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingProgress extends Model
{
    use BelongsToCompany;

    protected $table = 'onboarding_progresses';

    protected $fillable = [
        'company_id',
        'employee_id',
        'current_step',
        'is_completed',
        'completed_steps',
        'metadata',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_steps' => 'array',
        'metadata' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
