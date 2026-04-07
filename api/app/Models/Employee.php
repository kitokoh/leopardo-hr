<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class Employee extends Authenticatable
{
    use BelongsToCompany;
    use HasApiTokens;
    use HasFactory;

    protected $table = 'employees';

    protected $fillable = [
        'company_id',
        'schedule_id',
        'matricule',
        'first_name',
        'last_name',
        'email',
        'password_hash',
        'contract_type',
        'contract_start',
        'contract_end',
        'salary_type',
        'salary_base',
        'hourly_rate',
        'role',
        'status',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $employee): void {
            $employee->syncUserLookup();
        });

        static::deleted(function (self $employee): void {
            $employee->deleteUserLookup();
        });
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function syncUserLookup(): void
    {
        if (! $this->canSyncUserLookup()) {
            return;
        }

        DB::table($this->userLookupTable())
            ->where('employee_id', $this->id)
            ->where('company_id', $this->company_id)
            ->where('email', '!=', $this->email)
            ->delete();

        DB::table($this->userLookupTable())->updateOrInsert(
            ['email' => $this->email],
            [
                'company_id' => $this->company_id,
                'schema_name' => $this->company?->schema_name ?? 'shared_tenants',
                'employee_id' => $this->id,
                'role' => $this->role,
            ]
        );
    }

    public function deleteUserLookup(): void
    {
        if (! $this->canSyncUserLookup()) {
            return;
        }

        DB::table($this->userLookupTable())
            ->where('employee_id', $this->id)
            ->where('company_id', $this->company_id)
            ->delete();
    }

    private function canSyncUserLookup(): bool
    {
        if (! $this->email || ! $this->company_id) {
            return false;
        }

        return Schema::hasTable('user_lookups');
    }

    private function userLookupTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.user_lookups' : 'user_lookups';
    }
}
