<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $employee_id
 * @property string $type
 * @property string $title
 * @property string|null $body
 * @property array<mixed> $data
 * @property bool $is_read
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class Notification extends Model
{
    use BelongsToCompany;

    protected $table = 'notifications';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = ['company_id', 'employee_id', 'type', 'title', 'body', 'data', 'is_read', 'read_at'];

    protected $casts = ['data' => 'array', 'is_read' => 'boolean', 'read_at' => 'datetime', 'created_at' => 'datetime'];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $q
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeUnread(Builder $q): Builder
    {
        return $q->where('is_read', false);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<static> $q
     * @param int $employeeId
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForEmployee(Builder $q, int $employeeId): Builder
    {
        return $q->where('employee_id', $employeeId);
    }

    public function markAsRead(): void
    {
        $this->forceFill([
            'is_read' => true,
            'read_at' => $this->read_at ?? now(),
        ])->save();
    }
}
