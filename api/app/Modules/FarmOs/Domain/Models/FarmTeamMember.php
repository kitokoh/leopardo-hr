<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Farm OS — Appartenance d'un employé à une équipe opérationnelle.
 *
 * `left_at` date la sortie : l'historique des affectations n'est jamais perdu.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $farm_team_id
 * @property int $employee_id
 * @property Carbon $joined_at
 * @property Carbon|null $left_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmTeamMember extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_team_members';

    protected $fillable = [
        'company_id',
        'farm_team_id',
        'employee_id',
        'joined_at',
        'left_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<FarmTeam, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(FarmTeam::class, 'farm_team_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
