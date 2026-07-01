<?php

declare(strict_types=1);

namespace App\Core\Tenant\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $key
 * @property string|null $value
 * @property string|null $value_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
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
