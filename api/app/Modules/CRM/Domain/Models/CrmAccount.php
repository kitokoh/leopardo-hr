<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * #5714/#5708 — Compte CRM client (tenant-scoped).
 *
 * Organisation cliente du tenant. `company_id` non nullable ; archivage soft
 * via `archived_at` (jamais de DELETE destructif côté API).
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property string|null $industry
 * @property string|null $website
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $city
 * @property string|null $country
 * @property string|null $notes
 * @property string $status
 * @property int|null $owner_id
 * @property string|null $archived_at
 *
 * @mixin Builder<static>
 */
class CrmAccount extends Model
{
    use BelongsToCompany;

    protected $table = 'crm_accounts';

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'phone',
        'notes',
        'status',
        'owner_id',
        'archived_at',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'owner_id');
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(CrmContact::class, 'account_id')->where('is_primary', true);
    }
}
