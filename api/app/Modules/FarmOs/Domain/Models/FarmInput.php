<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Modules\FarmOs\Domain\Enums\FarmInputCategory;
use App\Modules\FarmOs\Domain\Enums\FarmInputUnit;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T2.1 Intrant agricole (catalogue).
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $name
 * @property string|null $reference
 * @property string $category
 * @property string $unit
 * @property string|null $packaging
 * @property string|null $alert_threshold
 * @property bool $is_regulated
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmInput extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_inputs';

    protected $fillable = [
        'company_id',
        'name',
        'reference',
        'category',
        'unit',
        'packaging',
        'alert_threshold',
        'is_regulated',
        'notes',
    ];

    protected $casts = [
        'alert_threshold' => 'decimal:3',
        'is_regulated' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function categoryEnum(): ?FarmInputCategory
    {
        return FarmInputCategory::tryFrom($this->category);
    }

    public function unitEnum(): ?FarmInputUnit
    {
        return FarmInputUnit::tryFrom($this->unit);
    }

    /**
     * @return HasMany<FarmInputLot, $this>
     */
    public function lots(): HasMany
    {
        return $this->hasMany(FarmInputLot::class, 'farm_input_id');
    }

    /**
     * @return HasMany<FarmInputStockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(FarmInputStockMovement::class, 'farm_input_id');
    }
}
