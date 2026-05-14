<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $employee_id
 * @property int|null $user_id
 * @property string|null $company_name
 * @property string $sector
 * @property string $country
 * @property string $city
 * @property string|null $manager_name
 * @property string|null $manager_id_card
 * @property string|null $manager_phone
 * @property string|null $notes
 * @property string $email
 * @property string|null $phone
 * @property string $description
 * @property string $status
 * @property int|null $approved_company_id
 * @property string|null $admin_notes
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 */
class CompanyRequest extends Model
{
    protected $table = 'company_requests';

    protected $fillable = [
        'employee_id',
        'user_id',
        'company_name',
        'sector',
        'country',
        'city',
        'manager_name',
        'manager_id_card',
        'manager_phone',
        'notes',
        'email',
        'phone',
        'description',
        'status',
        'approved_company_id',
        'admin_notes',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Company, $this> */
    public function approvedCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'approved_company_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
