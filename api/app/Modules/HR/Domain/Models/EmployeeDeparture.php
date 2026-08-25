<?php

declare(strict_types=1);

namespace App\Modules\HR\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Départ employé (workflow d'offboarding, issue #5324).
 *
 * Empreinte HR du départ : motif, dernier jour travaillé, préavis servi ou
 * non. Le passage à `departed` (employees.status) révoque l'accès
 * (AuthController : tout status ≠ active → 403) et supprime les tokens
 * Sanctum. Le solde de tout compte + l'attestation sont générés par le
 * module Payroll (EndOfContractController) — HR orchestre, Payroll calcule
 * (constitution §III). L'exclusion des runs de paie = gap G6 (Payroll).
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $employee_id
 * @property string $departure_type
 * @property string|null $reason
 * @property Carbon|null $last_work_day
 * @property bool $notice_served
 * @property int|null $notice_days_served
 * @property Carbon|null $departed_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee|null $employee
 * @property-read Employee|null $registrar
 *
 * @mixin Builder<static>
 */
class EmployeeDeparture extends Model
{
    use BelongsToCompany;

    public const TYPE_RESIGNATION = 'resignation';

    public const TYPE_TERMINATION = 'termination';

    public const TYPE_END_OF_CONTRACT = 'end_of_contract';

    public const TYPE_RETIREMENT = 'retirement';

    public const TYPES = [
        self::TYPE_RESIGNATION,
        self::TYPE_TERMINATION,
        self::TYPE_END_OF_CONTRACT,
        self::TYPE_RETIREMENT,
    ];

    protected $fillable = [
        'company_id',
        'employee_id',
        'departure_type',
        'reason',
        'last_work_day',
        'notice_served',
        'notice_days_served',
        'departed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'last_work_day' => 'date:Y-m-d',
            'departed_at' => 'date:Y-m-d',
            'notice_served' => 'boolean',
            'notice_days_served' => 'integer',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function registrar(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
}
