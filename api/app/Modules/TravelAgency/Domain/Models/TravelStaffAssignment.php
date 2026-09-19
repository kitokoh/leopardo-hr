<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\TravelStaffAssignmentStatus;
use App\Modules\TravelAgency\Domain\Enums\TravelStaffRole;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelStaffAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * #7638 (TRAVEL-STAFF) — affectation d'un employé RH sur la verticale.
 *
 * `employee_id` est référencé PAR VALEUR (spec SOLUTION_TRAVEL_AGENCY.md
 * §685 : « HR reste propriétaire des employés ») : pas de relation Eloquent
 * vers `Employee` ici — le module lit les employés via Core quand il doit
 * afficher un nom (manifeste), jamais l'inverse.
 *
 * Scope : exactement un de `office_id` / `trip_id` (validé au FormRequest).
 *
 * @property int $id
 * @property string $company_id
 * @property int $employee_id
 * @property TravelStaffRole $role
 * @property int|null $office_id
 * @property int|null $trip_id
 * @property TravelStaffAssignmentStatus $status
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property int|null $revoked_by_user_id
 */
class TravelStaffAssignment extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelStaffAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'role',
        'office_id',
        'trip_id',
        'status',
        'revoked_at',
        'revoked_by_user_id',
    ];

    protected $casts = [
        'employee_id' => 'integer',
        'role' => TravelStaffRole::class,
        'status' => TravelStaffAssignmentStatus::class,
        'revoked_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<TravelOffice, $this>
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(TravelOffice::class, 'office_id');
    }

    /**
     * @return BelongsTo<TravelTrip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(TravelTrip::class, 'trip_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', TravelStaffAssignmentStatus::ACTIVE->value);
    }

    public function isActive(): bool
    {
        return $this->status === TravelStaffAssignmentStatus::ACTIVE;
    }
}
