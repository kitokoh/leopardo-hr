<?php

declare(strict_types=1);

namespace App\Modules\HR\Domain\Models;

use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeJoinRequest extends Model
{
    protected $table = 'employee_join_requests';

    protected $fillable = [
        'user_id',
        'company_id',
        'message',
        'status',
        'approved_employee_id',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
