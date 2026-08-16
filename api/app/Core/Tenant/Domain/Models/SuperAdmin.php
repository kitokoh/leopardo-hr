<?php

declare(strict_types=1);

namespace App\Core\Tenant\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $status
 * @property string|null $password_hash
 * @property string|null $two_fa_secret
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 *
 * @mixin Builder<static>
 */
class SuperAdmin extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'super_admins';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'email',
        'last_login_at',
    ];

    protected $hidden = [
        'password_hash',
        'two_fa_secret',
    ];

    /**
     * Les colonnes created_at / last_login_at sont gérées manuellement
     * ($timestamps = false) mais doivent être castées en Carbon : la
     * sérialisation admin (PlatformUserController::serialize) appelle
     * ->toIso8601String() — sans cast, PostgreSQL renvoie une string et
     * l'endpoint /platform/users (et /admin/users) crashe en 500
     * (constat QA live 2026-08-15).
     */
    protected $casts = [
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }
}
