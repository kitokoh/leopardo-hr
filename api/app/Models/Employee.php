<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'zkteco_id',
        'first_name',
        'middle_name',
        'last_name',
        'preferred_name',
        'email',
        'personal_email',
        'phone',
        'address_line',
        'postal_code',
        'password_hash',
        'date_of_birth',
        'place_of_birth',
        'gender',
        'nationality',
        'marital_status',
        'contract_type',
        'contract_start',
        'contract_end',
        'salary_type',
        'salary_base',
        'hourly_rate',
        'role',
        'manager_role',
        'manager_id',
        'status',
        'photo_path',
        'biometric_face_enabled',
        'biometric_fingerprint_enabled',
        'biometric_face_reference_path',
        'biometric_fingerprint_reference_path',
        'biometric_consent_at',
        'invitation_accepted_at',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'extra_data',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'date_of_birth' => 'date',
        'contract_start' => 'date',
        'contract_end' => 'date',
        'biometric_face_enabled' => 'boolean',
        'biometric_fingerprint_enabled' => 'boolean',
        'biometric_consent_at' => 'datetime',
        'invitation_accepted_at' => 'datetime',
        'extra_data' => 'array',
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

    public function biometricEnrollmentRequests(): HasMany
    {
        return $this->hasMany(BiometricEnrollmentRequest::class, 'employee_id');
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

        if (DB::getDriverName() !== 'pgsql') {
            return Schema::hasTable('user_lookups');
        }

        $table = DB::selectOne("select to_regclass('public.user_lookups') as table_name");

        return $table?->table_name !== null;
    }

    private function userLookupTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.user_lookups' : 'user_lookups';
    }
}
