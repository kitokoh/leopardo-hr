<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Modules\FarmOs\Domain\Enums\FarmActivity;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Farm OS — Activité agricole déclarée par l'exploitation.
 *
 * Plusieurs activités par tenant. La fin d'une activité est datée
 * (`ended_at`) : elle n'entraîne AUCUNE suppression de donnée, et l'ajout
 * ultérieur d'une activité ne recrée jamais l'entreprise (exigence FR-1.7).
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $activity
 * @property bool $is_primary
 * @property Carbon $declared_at
 * @property Carbon|null $ended_at
 * @property array<mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmActivityRecord extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_activities';

    protected $fillable = [
        'company_id',
        'activity',
        'is_primary',
        'declared_at',
        'ended_at',
        'metadata',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'metadata' => 'array',
        'declared_at' => 'datetime',
        'ended_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function enum(): ?FarmActivity
    {
        return FarmActivity::tryFrom($this->activity);
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return self::query()
            ->whereNull('ended_at')
            ->pluck('activity')
            ->all();
    }
}
