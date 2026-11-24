<?php

declare(strict_types=1);

namespace App\Core\Auth\Domain\Models;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use App\Core\Tenant\Domain\Models\Site;
use App\Modules\Attendance\Domain\Models\BiometricEnrollmentRequest;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Modules\Cabinet\Domain\Models\CabinetFolder;
use App\Modules\HR\Domain\Models\Department;
use App\Modules\HR\Domain\Models\EmployeeDocument;
use App\Modules\HR\Domain\Models\OnboardingProgress;
use App\Modules\HR\Domain\Models\Position;
use App\Modules\HR\Domain\Models\PrivacyRequest;
use App\Modules\Notification\Domain\Models\Notification;
use App\Modules\Notification\Domain\Models\NotificationPreference;
use App\Modules\Planning\Domain\Models\Schedule;
use App\Shared\Traits\BelongsToCompany;
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
 * Modèle canonique Employé (toutes surfaces : API, web, mobile, jobs).
 *
 * Note sécurité (#6560, audit F2) : les champs salariaux (`salary_base`,
 * `salary_type`, `salary_structure_id`) sont stockés EN CLAIR — décision
 * assumée pour permettre les agrégations paie (totaux, moyennes, exports
 * CNAS/CNPS/CNSS-MA) côté base de données. Les données RGPD sensibles
 * (iban, bank_account, national_id) sont chiffrées via les casts
 * `encrypted`. Si le chiffrement des salaires devient requis (TDE ou
 * chiffrement applicatif + agrégations en mémoire), ouvrir une issue dédiée
 * avec impact paie/performance — ne pas chiffrer sans étude (les scopes
 * `where('salary_base', ...)` et les agrégations SQL caseraient).
 *
 * @property int $id
 * @property string|null $company_id
 * @property int|null $department_id
 * @property int|null $position_id
 * @property int|null $schedule_id
 * @property int|null $site_id
 * @property int|null $salary_structure_id
 * @property string|null $matricule
 * @property string|null $cnps_matricule
 * @property string|null $cnss_ci_matricule
 * @property string|null $ipres_matricule
 * @property string|null $ipres_category
 * @property float|null $family_parts
 * @property string|null $zkteco_id
 * @property string|null $badge_number
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string|null $preferred_name
 * @property string $email
 * @property string|null $google_id
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
 * @property string|null $google_id_bounce_reason
 * @property string|null $two_fa_secret
 * @property Carbon|null $two_fa_enabled_at
 * @property list<string>|null $two_fa_recovery_codes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company|null $company
 * @property-read Department|null $department
 * @property-read Position|null $position
 * @property-read Schedule|null $schedule
 * @property-read Site|null $site
 * @property-read Collection<int, EmployeeDocument>|null $employeeDocuments
 *
 * @mixin Builder<static>
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
    use \App\Core\Auth\Domain\Models\Concerns\EmployeePermissions;
    use BelongsToCompany;
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\EmployeeFactory> */
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
        'schedule_id',
        'department_id',
        'position_id',
        'site_id',
        'salary_structure_id',
        'matricule',
        'cnps_matricule',
        'cnss_ci_matricule',
        'ipres_matricule',
        'ipres_category',
        'family_parts',
        'zkteco_id',
        'badge_number',
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
        'date_of_birth',
        'place_of_birth',
        'gender',
        'nationality',
        'marital_status',
        'contract_type',
        'contract_start',
        'contract_end',
        'pas_rate',
        'pas_identifier',
        'salary_type',
        'hourly_rate',
        'manager_id',
        'photo_path',
        'biometric_face_enabled',
        'biometric_fingerprint_enabled',
        'biometric_consent_at',
        'invitation_accepted_at',
        'google_id',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'extra_data',
        'preferred_language',
        'iban',
        'bank_account',
        'national_id',
        'email_bounce_reason',
    ];

    protected $hidden = [
        // Issue #5588 : deux_fa_secret et les codes de récupération sont des
        // secrets — toute sérialisation (Resource, toArray, log) les fuirait.
        'password_hash',
        'two_fa_secret',
        'two_fa_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'email_bounced_at' => 'datetime',
        'date_of_birth' => 'date',
        'contract_start' => 'date',
        'contract_end' => 'date',
        // #7936 : taux PAS en pourcentage, declare en DSN (S21.G00.50.006).
        'pas_rate' => 'float',
        'biometric_face_enabled' => 'boolean',
        'biometric_fingerprint_enabled' => 'boolean',
        'biometric_consent_at' => 'datetime',
        'two_fa_secret' => 'string',
        'two_fa_enabled_at' => 'datetime',
        // #5588 : cast JSON requis pour le tableau de codes hachés (colonne
        // json) — sans cast, INSERT/UPDATE crashent et la lecture
        // (consumeRecoveryCode) reçoit une string au lieu d'un tableau.
        'two_fa_recovery_codes' => 'array',
        'invitation_accepted_at' => 'datetime',
        'extra_data' => 'array',
        'iban' => 'encrypted',
        'bank_account' => 'encrypted',
        'national_id' => 'encrypted',
        'failed_login_attempts' => 'integer',
        'locked_until' => 'datetime',
        'family_parts' => 'float',
    ];

    protected static function booted(): void
    {
        /**
         * #9897 — un employé créé sous un tenant actif lui appartient.
         *
         * `employees` porte désormais une politique RLS (étape 4). Une ligne
         * à `company_id` NULL serait INVISIBLE (`NULL = 'x'` vaut NULL, pas
         * TRUE) : une panne d'affichage bruyante, jamais une fuite. On
         * renseigne donc le tenant à la création quand il est connu.
         *
         * On ne l'impose QUE si `company_id` est absent : un appelant qui le
         * fournit explicitement (import, backfill, test multi-tenant) garde
         * la main. Et on ne l'invente pas : sans tenant courant, on laisse
         * `null` — les fixtures de test qui renseignent `company_id` dans un
         * second `save()` continuent de fonctionner.
         */
        static::creating(function (self $employee): void {
            if ($employee->company_id !== null) {
                return;
            }

            $company = app()->bound('current_company') ? app('current_company') : null;

            if ($company instanceof Company) {
                $employee->company_id = $company->id;
            }
        });

        static::saved(function (self $employee): void {
            $employee->syncUserLookup();
        });

        static::deleted(function (self $employee): void {
            $employee->deleteUserLookup();
        });
    }

    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
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

    /**
     * Issue #7598 (R1 de l'épique #7597) — accès ressource-scopés posés par le
     * responsable du tenant (« Moussa → restaurant Almadies, niveau manage »).
     *
     * @return HasMany<EmployeeResourceAssignment, $this>
     */
    public function resourceAssignments(): HasMany
    {
        return $this->hasMany(EmployeeResourceAssignment::class, 'employee_id');
    }

    /**
     * Issue #7761 — grants de MODULES composables posés par le responsable du
     * tenant (« Moussa → marketing + comptabilité + tickets »). Complément du
     * RBAC historique par `manager_role` : le middleware `api.manager` accepte
     * « rôle autorisé OU grant explicite ».
     *
     * @return HasMany<\App\Core\Tenant\Domain\Models\EmployeeModuleGrant, $this>
     */
    public function moduleGrants(): HasMany
    {
        // FQCN volontaire (pas de nouvel import) : la garde layer-purity
        // (#6568) allowliste les facades DB/Schema de ce fichier PAR NUMÉRO DE
        // LIGNE — ajouter un `use` en tête décalerait ces lignes et la ferait
        // échouer dans les deux sens (allowlist immuable, pattern #5584).
        return $this->hasMany(\App\Core\Tenant\Domain\Models\EmployeeModuleGrant::class, 'employee_id');
    }

    /** Niveau assigné à ce collaborateur pour cette ressource, ou null. */

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

    /** @return HasOne<OnboardingProgress, $this> */
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

    /** @return HasMany<EmployeeDocument, $this> */
    public function employeeDocuments(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class, 'employee_id');
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

        /** @var object{employee_id: int|string}|null $existing */
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

        /** @var object{table_name: string|null}|null $table */
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
