<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Fête islamique mobile par année (issue #1812). Modèle global : table
 * `islamic_calendar` dans le schéma `public`, partagée entre tous les tenants.
 *
 * Une entrée par (holiday_key, year). Les dates sont approximatives
 * (`source = 'computed'`, `confirmed = false`) tant qu'un admin plateforme ne
 * les a pas validées depuis l'interface admin.
 *
 * @property int $id
 * @property string $holiday_key
 * @property int $year
 * @property Carbon $gregorian_date
 * @property int $duration_days
 * @property string $source
 * @property bool $confirmed
 * @property int|null $confirmed_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class IslamicCalendar extends Model
{
    protected $table = 'islamic_calendar';

    public const KEYS = ['eid_al_fitr', 'eid_al_adha', 'mawlid', 'tahmarit', 'muharram'];

    protected $fillable = [
        'holiday_key',
        'year',
        'gregorian_date',
        'duration_days',
        'source',
        'confirmed',
        'confirmed_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'gregorian_date' => 'date',
        'duration_days' => 'integer',
        'confirmed' => 'boolean',
        'confirmed_by' => 'integer',
    ];
}
