<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property string $id
 * @property string|null $company_id
 * @property string $schema_name
 * @property string|null $employee_id
 * @property string $email
 * @property string $role
 * @property string|null $manager_role
 * @property string|null $invited_by_type
 * @property string|null $invited_by_email
 * @property string|null $token_hash
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $accepted_at
 * @property \Illuminate\Support\Carbon|null $last_sent_at
 * @property array<mixed> $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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
