<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property string|null $shareable_type
 * @property int|null $shareable_id
 * @property string|null $share_token
 * @property string|null $shared_via
 * @property string|null $shared_with_email
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee|null $employee
 * @property-read Model|null $shareable
 */
class CabinetShare extends Model
{
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
        'employee_id' => 'integer',
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
