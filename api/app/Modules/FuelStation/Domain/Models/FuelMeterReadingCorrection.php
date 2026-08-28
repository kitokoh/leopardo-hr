<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Correction versionnée d'un relevé FuelStation (issue #5798).
 *
 * @property string $id
 * @property string $company_id
 * @property string $reading_id
 * @property float $old_value
 * @property float $new_value
 * @property string $reason
 * @property string|null $corrected_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelMeterReadingCorrection extends Model
{
    use BelongsToCompany;
    use HasUuids;

    protected $table = 'fuel_meter_reading_corrections';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'old_value' => 'float',
            'new_value' => 'float',
        ];
    }
}
