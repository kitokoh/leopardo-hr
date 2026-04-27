<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use BelongsToCompany;

    protected $table = 'notifications';
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $fillable = ['company_id', 'employee_id', 'type', 'title', 'body', 'data', 'is_read', 'read_at'];
    protected $casts = ['data' => 'array', 'is_read' => 'boolean', 'read_at' => 'datetime', 'created_at' => 'datetime'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function scopeUnread(Builder $q): Builder
    {
        return $q->where('is_read', false);
    }

    public function scopeForEmployee(Builder $q, int $employeeId): Builder
    {
        return $q->where('employee_id', $employeeId);
    }
}
