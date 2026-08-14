<?php

declare(strict_types=1);

namespace App\Core\Auth\Domain\Models;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\Site;
use App\Modules\Attendance\Domain\Models\BiometricEnrollmentRequest;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Modules\Cabinet\Domain\Models\CabinetFolder;
use App\Modules\HR\Domain\Models\Department;
use App\Modules\HR\Domain\Models\OnboardingProgress;
use App\Modules\HR\Domain\Models\Position;
use App\Modules\HR\Domain\Models\PrivacyRequest;
use App\Modules\Notification\Domain\Models\Notification;
use App\Modules\Notification\Domain\Models\NotificationPreference;
use App\Modules\Planning\Domain\Models\Schedule;
use App\Traits\BelongsToCompany;
use Carbon\CarbonInterface;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Contracts\HasApiTokens as HasApiTokensContract;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string|null $company_id
 * @property int|null $department_id
 * @property int|null $position_id
 * @property int|null $schedule_id
 * @property int|null $site_id
 * @property int|null $salary_structure_id
 * @property string|null $matricule
 * @property string|null $zkteco_id
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $preferred_name
 * @property string $email
 * @property string|null $personal_email
 * @property string|null $recovery_email
 * @property string|null $personal_phone
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
 * @property Carbon|null $contract_start
 * @property Carbon|null $contract_end
 * @property string $salary_type
 * @property float|null $salary_base
 * @property float|null $hourly_rate
 * @property string $payment_method
 * @property float $leave_balance
 * @property string $role
 * @property string|null $manager_role
 * @property int|null $manager_id
 * @property string $status
 * @property string|null $photo_path
 * @property Carbon|null $last_login_at
 * @property array<mixed>|null $metadata
 * @property bool $biometric_face_enabled
 * @property bool $biometric_fingerprint_enabled
 * @property string|null $biometric_face_reference_path
 * @property string|null $biometric_fingerprint_reference_path
 * @property Carbon|null $biometric_consent_at
 * @property CarbonInterface|null $email_verified_at
 * @property CarbonInterface|null $invitation_accepted_at
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
 * @property Carbon|null $email_bounced_at
 * @property string|null $email_bounce_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company|null $company
 * @property-read Department|null $department
 * @property-read Position|null $position
 * @property-read Schedule|null $schedule
 * @property-read Site|null $site
 *
 * @mixin Builder
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @method static static create(array<string, mixed> $attributes = [])
 * @method static static|null find(mixed $id, array<int, string> $columns = ['*'])
 * @method static static findOrFail(mixed $id, array<int, string> $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Builder<static> where(string|\Closure|array<mixed> $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder<static> select(array<int, string>|string $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Builder<static> withoutGlobalScopes(array<int, string>|null $scopes = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static> with(array<int|string, mixed>|string $relations, \Closure|string $callback = null)
 */
class Employee extends Authenticatable implements HasApiTokensContract
{
    use BelongsToCompany;
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $table = 'employees';

    /**
     * Resolve the factory for this model explicitly since it lives
     * outside the default App\Models namespace.
     */
    protected static function newFactory(): EmployeeFactory
    {
        return EmployeeFactory::new();
    }

    protected $fillable = [
        'company_id',
        'schedule_id',
        'department_id',
        'position_id',
        'site_id',
        'salary_structure_id',
        'matricule',
        'cnps_matricule',
        'zkteco_id',
        'first_name',
        'middle_name',
        'last_name',
        'preferred_name',
        'email',
        'personal_email',
        'recovery_email',
        'personal_phone',
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
        'email_bounced_at',
        'email_bounce_reason',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'email_bounced_at' => 'datetime',
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

    /**
     * PA2-COMM-007 - True once a mail provider bounce webhook has recorded
     * a hard bounce for this employee's `email`. `MailMessageProvider`
     * checks this before every send so a known-bad address stops being
     * retried on every future communication.
     */
    public function hasBouncedEmail(): bool
    {
        return $this->email_bounced_at !== null;
    }

    public function isPrincipal(): bool
    {
        return $this->hasManagerRole('principal');
    }

    public function isHr(): bool
    {
        return $this->hasManagerRole('rh');
    }

    public function isMarketing(): bool
    {
        return $this->hasManagerRole('marketing');
    }

    public function isComptable(): bool
    {
        return $this->hasManagerRole('comptable');
    }

    public function isDept(): bool
    {
        return $this->hasManagerRole('dept');
    }

    public function isSuperviseur(): bool
    {
        return $this->hasManagerRole('superviseur');
    }

    /**
     * Whether this actor's manager scope is limited to a single department
     * (currently only `manager_role = 'dept'`). Company-wide roles
     * (principal, rh, comptable, marketing) and self-service employees
     * are not department-scoped.
     */
    public function isDepartmentScoped(): bool
    {
        return $this->isDept();
    }

    /**
     * Whether this actor's manager scope is limited to their own directly
     * assigned team (`manager_role = 'superviseur'`). Per RBAC_SYSTEM.md,
     * a superviseur only ever sees "son equipe assignee", never the whole
     * company (PA2-SEC-003).
     */
    public function isSupervisorScoped(): bool
    {
        return $this->isSuperviseur();
    }

    /**
     * Whether $target sits within this department-scoped actor's own
     * department. Returns false when the actor has no department
     * assigned (fail closed) so a misconfigured dept manager sees
     * nothing rather than everything.
     */
    public function managesDepartmentOf(self $target): bool
    {
        if ($this->department_id === null) {
            return false;
        }

        return $this->department_id === $target->department_id;
    }

    /**
     * Whether $target is directly assigned to this supervisor via
     * `manager_id` (the existing hierarchy FK; see PA2-SEC-003). A
     * superviseur's "equipe assignee" is defined as employees whose
     * manager_id points back to them. Self is always included so a
     * superviseur can act on their own records.
     */
    public function managesEmployeeDirectly(self $target): bool
    {
        if ($this->id === $target->id) {
            return true;
        }

        return $target->manager_id !== null && $target->manager_id === $this->id;
    }

    /**
     * Whether this actor's visibility is limited to an explicit subset of
     * employees (department for `dept`, direct reports for `superviseur`)
     * rather than the whole company. Company-wide roles (principal, rh,
     * comptable, marketing) and self-service employees are not team-scoped.
     */
    public function isTeamScoped(): bool
    {
        return $this->isDept() || $this->isSuperviseur();
    }

    /**
     * Dispatches to the correct "manages" check for whichever team-scoped
     * role the actor holds. Callers should guard with isTeamScoped() (or
     * accept that non-team-scoped actors always return true here).
     */
    public function managesTeamMemberOf(self $target): bool
    {
        if ($this->isDept()) {
            return $this->managesDepartmentOf($target);
        }

        if ($this->isSuperviseur()) {
            return $this->managesEmployeeDirectly($target);
        }

        return true;
    }

    /**
     * Constrains an Employee query builder to the employees this actor is
     * allowed to see when they hold a team-scoped manager_role. No-op for
     * company-wide roles. Fails closed: a dept manager without a
     * department, or a superviseur (who by definition has no direct
     * reports until manager_id is set on someone), sees nobody rather
     * than everybody.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleToManager(Builder $query, self $actor): Builder
    {
        if ($actor->isDept()) {
            return $query->where('department_id', $actor->department_id ?? -1);
        }

        if ($actor->isSuperviseur()) {
            return $query->where(function ($scope) use ($actor): void {
                $scope->where('manager_id', $actor->id)->orWhere('id', $actor->id);
            });
        }

        return $query;
    }

    /**
     * Resolves who should be alerted about this employee (e.g. an
     * out-of-geofence punch): their direct `manager_id` when one is
     * assigned and still active, otherwise every active company-wide
     * manager (`manager_role` principal/rh) so an alert is never silently
     * dropped just because no direct hierarchy has been configured yet.
     * Never includes the employee themself.
     *
     * @return Collection<int, static>
     */
    public function resolveAlertRecipients(): Collection
    {
        if ($this->manager_id !== null) {
            $directManager = static::query()
                ->where('company_id', $this->company_id)
                ->where('status', 'active')
                ->find($this->manager_id);

            if ($directManager !== null) {
                return collect([$directManager]);
            }
        }

        return static::query()
            ->where('company_id', $this->company_id)
            ->where('role', 'manager')
            ->whereIn('manager_role', ['principal', 'rh'])
            ->where('status', 'active')
            ->where('id', '!=', $this->id)
            ->get();
    }

    /**
     * Route d'accueil suggeree selon le role/sous-role de l'employe.
     */
    public function homeRoute(): string
    {
        if (! $this->isManager()) {
            return 'me.dashboard';
        }

        return match ($this->manager_role) {
            'principal' => 'dashboard',
            'rh' => 'dashboard',
            'comptable' => 'dashboard',
            'marketing' => 'dashboard',
            'dept' => 'dashboard',
            default => 'dashboard',
        };
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /** @return BelongsTo<Position, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    /**
     * The direct manager this employee reports to via `manager_id`
     * (self-referencing hierarchy FK; see PA2-SEC-003).
     *
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function onboardingProgress(): HasOne
    {
        return $this->hasOne(OnboardingProgress::class);
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

    /** @return HasMany<CabinetFolder, $this> */
    public function cabinetFolders(): HasMany
    {
        return $this->hasMany(CabinetFolder::class, 'employee_id');
    }

    /** @return HasMany<CabinetDocument, $this> */
    public function cabinetDocuments(): HasMany
    {
        return $this->hasMany(CabinetDocument::class, 'employee_id');
    }

    /** @return HasMany<Notification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'employee_id');
    }

    /** @return HasMany<NotificationPreference, $this> */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class, 'employee_id');
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
