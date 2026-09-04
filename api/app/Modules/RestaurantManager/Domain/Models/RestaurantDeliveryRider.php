<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantDeliveryRiderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Livreur rattaché à une branche (RESTO-211, issue #6176).
 *
 * `employee_id` référence le dossier RH (par valeur, sans FK) ; `is_active`
 * permet de désactiver un livreur sans le supprimer.
 */
class RestaurantDeliveryRider extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantDeliveryRiderFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'employee_id',
        'name',
        'phone',
        'vehicle_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<RestaurantBranch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }
}
