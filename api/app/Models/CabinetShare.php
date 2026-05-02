<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CabinetShare extends Model
{
    use BelongsToCompany;

    protected $table = 'cabinet_shares';

    protected $fillable = [
        'company_id',
        'employee_id',
        'shareable_type',
        'shareable_id',
        'share_token',
        'shared_via',
        'shared_with_email',
        'expires_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'expires_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
