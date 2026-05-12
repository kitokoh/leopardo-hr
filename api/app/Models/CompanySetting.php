<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $key
 * @property string|null $value
 * @property string|null $value_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class CompanySetting extends Model
{
    protected $table = 'company_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = null;

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'key',
        'value',
        'value_type',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
    ];
}
