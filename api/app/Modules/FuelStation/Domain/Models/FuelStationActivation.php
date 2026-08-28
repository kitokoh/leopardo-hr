<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Activation FuelStation par tenant (issue #5795).
 *
 * @property string $company_id
 * @property string $manifest_version
 * @property string $status
 * @property Carbon|null $activated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelStationActivation extends Model
{
    protected $table = 'fuel_station_activations';

    public $incrementing = false;

    protected $primaryKey = 'company_id';

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
        ];
    }
}
