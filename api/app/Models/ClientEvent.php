<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $company_id
 * @property int|null $employee_id
 * @property string $event_name
 * @property string $surface
 * @property string|null $session_id
 * @property int|null $duration_ms
 * @property array<string, mixed>|null $properties
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company|null $company
 * @property-read Employee|null $employee
 */
class ClientEvent extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'event_name',
        'surface',
        'session_id',
        'duration_ms',
        'properties',
        'ip_address',
        'user_agent',
        'occurred_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'duration_ms' => 'integer',
        'occurred_at' => 'datetime',
    ];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}
