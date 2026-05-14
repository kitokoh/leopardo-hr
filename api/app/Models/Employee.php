<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $schedule_id
 * @property string|null $matricule
 * @property string|null $zkteco_id
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $preferred_name
 * @property string $email
 * @property string|null $personal_email
 * @property string|null $phone
 * @property string|null $address_line
 * @property string|null $postal_code
 * @property string|null $password_hash
 * @property Carbon $date_of_birth
 * @property string|null $place_of_birth
 * @property string $gender
 * @property string|null $nationality
 * @property string|null $marital_status
 * @property string $contract_type
 * @property Carbon $contract_start
 * @property Carbon|null $contract_end
 * @property string $salary_type
 * @property float|null $salary_base
 * @property float|null $hourly_rate
 * @property string $role
 * @property string|null $manager_role
 * @property int|null $manager_id
 * @property string $status
 * @property string|null $photo_path
 * @property bool $biometric_face_enabled
 * @property bool $biometric_fingerprint_enabled
 * @property string|null $biometric_face_reference_path
 * @property string|null $biometric_fingerprint_reference_path
 * @property Carbon|null $biometric_consent_at
 * @property Carbon|null $invitation_accepted_at
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property string|null $emergency_contact_relation
 * @property array<mixed> $extra_data
 * @property string $preferred_language
 * @property string $iban
 * @property string $bank_account
 * @property string $national_id
 * @property int $failed_login_attempts
 * @property Carbon|null $locked_until
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company|null $company
 * @property-read Schedule|null $schedule
 * @property-read Carbon|null $email_verified_at
 * @property-read Carbon|null $last_login_at
 */
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
        'preferred_language',
        'iban',
        'bank_account',
        'national_id',
        'failed_login_attempts',
        'locked_until',
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
        'iban' => 'encrypted',
        'bank_account' => 'encrypted',
        'national_id' => 'encrypted',
        'failed_login_attempts' => 'integer',
        'locked_until' => 'datetime',
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

    public function isEmployee(): bool
    {
        return $this->role === 'employee';
    }

    public function hasManagerRole(string ...$roles): bool
    {
        if (! $this->isManager()) {
            return false;
        }

        if ($roles === []) {
            return true;
        }

        return in_array($this->manager_role, $roles, true);
    }

    public function isPrincipal(): bool
    {
        return $this->hasManagerRole('principal');
    }

    public function isHr(): bool
    {
        return $this->hasManagerRole('rh');
    }

    /**
     * Route d'accueil suggeree selon le role/sous-role de l'employe.
     */
    public function homeRoute(): string
    {
        if (! $this->isManager()) {
            return 'me.dashboard';
        }

        return 'dashboard';
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    /** @return HasMany<BiometricEnrollmentRequest, $this> */
    public function biometricEnrollmentRequests(): HasMany
    {
        return $this->hasMany(BiometricEnrollmentRequest::class, 'employee_id');
    }

    /** @return HasMany<PrivacyRequest, $this> */
    public function privacyRequests(): HasMany
    {
        return $this->hasMany(PrivacyRequest::class, 'employee_id');
    }

    /**
     * @return HasMany<CabinetFolder, $this>
     */
    public function cabinetFolders(): HasMany
    {
        return $this->hasMany(CabinetFolder::class, 'employee_id');
    }

    /**
     * @return HasMany<CabinetDocument, $this>
     */
    public function cabinetDocuments(): HasMany
    {
        return $this->hasMany(CabinetDocument::class, 'employee_id');
    }

    /** @return HasMany<Notification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'employee_id');
    }

    /** @return HasMany<Notification, $this> */
    public function unreadNotifications(): HasMany
    {
        return $this->notifications()->unread();
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

        // Defense in depth: Never overwrite a lookup record belonging to a different employee.
        // This prevents cross-tenant hijacking if validation is somehow bypassed.
        $existing = DB::table($this->userLookupTable())
            ->where('email', $this->email)
            ->first();

        if ($existing && (int) $existing->employee_id !== (int) $this->id) {
            return;
        }

        DB::table($this->userLookupTable())->updateOrInsert(
            ['email' => $this->email],
            [
                'company_id' => $this->company_id,
                'schema_name' => $this->company?->schema_name ?? 'shared_tenants',
                'employee_id' => $this->id,
                'role' => $this->userLookupRole(),
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

    private function userLookupRole(): string
    {
        $role = $this->getAttribute('role');

        return is_string($role) && $role !== ''
            ? $role
            : 'employee';
    }
}
