<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Jour férié par pays (issue #1811). Modèle global : table `public_holidays`
 * dans le schéma `public`, partagée entre tous les tenants.
 *
 * - `company_id = null`  → férié national (lu par tous les tenants du pays)
 * - `company_id != null` → férié spécifique à une entreprise (pont, fermeture)
 * - `is_recurring = true` → se répète chaque année (ex. 1er mai), `month_day`
 *   contient le motif 'MM-DD'
 * - `holiday_type` → 'fixed' | 'islamic' | 'christian' | 'custom'
 *
 * @property int $id
 * @property int|null $company_id
 * @property string $country_code
 * @property string $name
 * @property Carbon $date
 * @property int $year
 * @property bool $is_recurring
 * @property string|null $month_day
 * @property string $holiday_type
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class PublicHoliday extends Model
{
    protected $table = 'public_holidays';

    protected $fillable = [
        'company_id',
        'country_code',
        'name',
        'date',
        'year',
        'is_recurring',
        'month_day',
        'holiday_type',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'year' => 'integer',
        'is_recurring' => 'boolean',
    ];
}
