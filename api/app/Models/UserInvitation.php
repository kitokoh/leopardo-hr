<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UserInvitation extends Model
{
    use BelongsToCompany;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'schema_name',
        'employee_id',
        'email',
        'role',
        'manager_role',
        'invited_by_type',
        'invited_by_email',
        'token_hash',
        'expires_at',
        'accepted_at',
        'last_sent_at',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return DB::getDriverName() === 'pgsql'
            ? 'public.user_invitations'
            : 'user_invitations';
    }
}
