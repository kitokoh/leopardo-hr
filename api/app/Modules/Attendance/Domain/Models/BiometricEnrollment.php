<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Enums\BiometricEnrollmentStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Enrôlement biométrique versionné d'un employé (BIO-002 #6763, BIO-003 #6764).
 *
 * Le gabarit (`template`) est chiffré au repos (cast `encrypted`) et n'est
 * jamais exposé par les réponses API. `template_key_version` suit la version
 * de clé de chiffrement (rotation). Un seul enrôlement ACTIF par employé et
 * méthode (index unique partiel) : l'activation d'un remplacement révoque
 * l'ancien gabarit.
 *
 * @property int $id
 * @property string $company_id
 * @property int $employee_id
 * @property string $method
 * @property \App\Modules\Attendance\Domain\Enums\BiometricEnrollmentStatus $status
 * @property int $version
 * @property int $template_key_version
 * @property string $template
 * @property string|null $provider
 * @property string|null $correlation_id
 * @property string $enrolled_via
 * @property int|null $created_by_employee_id
 * @property int|null $activated_by_employee_id
 * @property int|null $revoked_by_employee_id
 * @property Carbon|null $enrolled_at
 * @property Carbon|null $revoked_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Employee|null $employee
 *
 * @mixin Builder<static>
 */
class BiometricEnrollment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'employee_id',
        'method',
        'status',
        'version',
        'template_key_version',
        'template',
        'provider',
        'correlation_id',
        'enrolled_via',
        'created_by_employee_id',
        'activated_by_employee_id',
        'revoked_by_employee_id',
        'enrolled_at',
        'revoked_at',
    ];

    protected $casts = [
        'status' => BiometricEnrollmentStatus::class,
        'version' => 'integer',
        'template_key_version' => 'integer',
        // Chiffrement applicatif au repos (clé APP_KEY).
        'template' => 'encrypted',
        'enrolled_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    // Le gabarit ne doit jamais apparaître en clair dans une sérialisation
    // métier ordinaire (réponses API, logs, exports).
    protected $hidden = ['template'];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Enrôlements actifs d'un employé pour une méthode.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUsableFor(Builder $query, int $employeeId, string $method): Builder
    {
        return $query
            ->where('employee_id', $employeeId)
            ->where('method', $method)
            ->where('status', BiometricEnrollmentStatus::Active);
    }
}
