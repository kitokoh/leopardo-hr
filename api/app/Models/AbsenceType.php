<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $name
 * @property string $code
 * @property bool $is_paid
 * @property bool $deducts_leave
 * @property bool $requires_proof
 * @property int $max_days_once
 * @property \Illuminate\Support\Carbon|null $created_at
 */
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
        'is_paid' => 'boolean',
        'deducts_leave' => 'boolean',
        'requires_proof' => 'boolean',
        'max_days_once' => 'integer',
    ];
}
