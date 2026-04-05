<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'schedules';

    protected $fillable = [
        'company_id',
        'name',
        'start_time',
        'end_time',
        'late_tolerance_minutes',
        'overtime_threshold_daily',
        'is_default',
    ];

    protected $casts = [
        'late_tolerance_minutes' => 'integer',
        'overtime_threshold_daily' => 'decimal:2',
        'is_default' => 'boolean',
    ];
}

