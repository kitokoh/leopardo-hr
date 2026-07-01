<?php

declare(strict_types=1);

namespace App\Core\Tenant\Domain\Models;

use App\Core\Auth\Domain\Models\User;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $password_hash
 * @property string|null $two_fa_secret
 * @property Carbon|null $last_login_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class SuperAdmin extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'super_admins';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'two_fa_secret',
        'last_login_at',
    ];

    protected $hidden = [
        'password_hash',
        'two_fa_secret',
    ];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }
}
