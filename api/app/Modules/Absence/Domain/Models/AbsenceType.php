<?php

declare(strict_types=1);

namespace App\Modules\Absence\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $id
 * @property string $name
 * @property string $code
 * @property bool   $paid
 * @property int    $max_days_per_year
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class AbsenceType extends Model
{
    protected $fillable = [
        'name',
        'code',
        'paid',
        'max_days_per_year',
        'requires_document',
        'color',
    ];

    protected $casts = [
        'paid'              => 'boolean',
        'requires_document' => 'boolean',
    ];
}
