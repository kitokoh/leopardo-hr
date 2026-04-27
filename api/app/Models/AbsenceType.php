<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AbsenceType extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'absence_types';

    public $timestamps = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'is_paid',
        'deducts_leave',
        'requires_proof',
        'max_days_once',
    ];

    protected $casts = [
        'is_paid'        => 'boolean',
        'deducts_leave'  => 'boolean',
        'requires_proof' => 'boolean',
        'max_days_once'  => 'integer',
    ];
}
