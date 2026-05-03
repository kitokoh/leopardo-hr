<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyRequest extends Model
{
    protected $table = 'company_requests';

    protected $fillable = [
        'employee_id',
        'company_name',
        'sector',
        'country',
        'city',
        'manager_name',
        'manager_id_card',
        'manager_phone',
        'notes',
        'status',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
