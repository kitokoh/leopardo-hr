<?php

declare(strict_types=1);

namespace App\Modules\Absence\Domain\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Colonnes reelles de la table `absence_types`
 * (migration tenant 2026_04_01_000103_create_attendance_absences_advances.php,
 * schema de test shared_tenants.absence_types).
 *
 * @property int         $id
 * @property int|null    $company_id
 * @property string      $name
 * @property string      $code
 * @property bool        $is_paid
 * @property bool        $deducts_leave
 * @property bool        $requires_proof
 * @property int|null    $max_days_once
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class AbsenceType extends Model
{
    use BelongsToCompany;
    use HasFactory;

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
    ];
}
