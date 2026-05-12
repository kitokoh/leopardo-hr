<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $employee_id
 * @property int|null $company_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $linked_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class UserEmployeeLink extends Model
{
    protected $table = 'user_employee_links';

    protected $fillable = [
        'user_id',
        'employee_id',
        'company_id',
        'status',
        'linked_at',
    ];

    protected $casts = [
        'linked_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
