<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Filtre ATS sauvegardé par un recruteur.
 *
 * `criteria` est un sac de critères validés par `AtsFilterCriteria` — jamais
 * une requête SQL sérialisée : un filtre sauvegardé ne doit pas pouvoir
 * devenir un vecteur d'injection au moment où on le rejoue.
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $job_posting_id
 * @property string $name
 * @property array<string, mixed> $criteria
 * @property int|null $created_by
 * @property bool $is_shared
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class AtsSavedFilter extends Model
{
    use BelongsToCompany;

    protected $table = 'ats_saved_filters';

    protected $fillable = [
        'company_id',
        'job_posting_id',
        'name',
        'criteria',
        'created_by',
        'is_shared',
    ];

    protected $casts = [
        'criteria' => 'array',
        'is_shared' => 'boolean',
    ];
}
