<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Farm OS — Équipe opérationnelle (chef d'équipe + membres).
 *
 * Justification (ADR-FARM-0007) : `departments` / `positions` (BC-04 HR)
 * expriment l'organigramme, pas une équipe de terrain affectée à une parcelle.
 * Aucune table RH partagée n'est modifiée : la relation vit ici.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int|null $farm_site_id
 * @property string $name
 * @property int|null $lead_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmTeam extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_teams';

    protected $fillable = [
        'company_id',
        'farm_site_id',
        'name',
        'lead_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'lead_id');
    }

    /**
     * @return HasMany<FarmTeamMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(FarmTeamMember::class, 'farm_team_id');
    }
}
