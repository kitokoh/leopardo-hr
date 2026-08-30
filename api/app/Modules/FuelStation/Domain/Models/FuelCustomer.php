<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Compte client (professionnel) d'une station — FUEL-016 (#5810).
 *
 * CRM TENANT uniquement : les clients de la station, jamais les leads
 * commerciaux Leopardo (CRM plateforme) — distinction verrouillée.
 * Consentement marketing explicite horodaté (opt-in/opt-out), points de
 * fidélité entiers, `external_id` UNIQUE (company_id, external_id).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $station_id
 * @property string $name
 * @property string|null $contact_email
 * @property string|null $phone
 * @property bool $marketing_consent
 * @property Carbon|null $opted_in_at
 * @property Carbon|null $opted_out_at
 * @property int $loyalty_points
 * @property string $status active|inactive
 * @property string|null $external_id
 * @property int|null $created_by
 *
 * @mixin Builder<static>
 */
class FuelCustomer extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_customers';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'company_id',
        'station_id',
        'name',
        'contact_email',
        'phone',
        'marketing_consent',
        'opted_in_at',
        'opted_out_at',
        'loyalty_points',
        'status',
        'external_id',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'marketing_consent' => 'boolean',
            'loyalty_points' => 'integer',
            'opted_in_at' => 'datetime',
            'opted_out_at' => 'datetime',
        ];
    }
}
