<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T4.1 Campagne agricole (annee culturale).
 *
 * Axe de regroupement des couts et des produits. Une campagne `closed`
 * n'accepte plus de nouvelle depense : on refuse plutot que d'ecrire dans un
 * exercice clos.
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $name
 * @property int $start_year
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property string $status
 * @property string|null $notes
 * @property string|null $client_campaign_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmCampaign extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_campaigns';

    /** Statuts du registre FERME (extension par nouvelle case). */
    public const STATUSES = ['open', 'closed'];

    protected $fillable = [
        'company_id',
        'name',
        'start_year',
        'starts_on',
        'ends_on',
        'status',
        'notes',
        'client_campaign_id',
        'created_by',
    ];

    protected $casts = [
        'start_year' => 'integer',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * @return HasMany<FarmExpense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(FarmExpense::class, 'farm_campaign_id');
    }
}
