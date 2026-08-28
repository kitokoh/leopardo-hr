<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Modules\FuelStation\Domain\Enums\FuelEquipmentType;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $company_id
 * @property string $site_id
 * @property string $type
 * @property string $code
 * @property string|null $name
 * @property string $status
 * @property Carbon|null $installed_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelEquipment extends Model
{
    use BelongsToCompany;
    use HasUuids;

    protected $table = 'fuel_equipment';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'installed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public static function isValidType(string $type): bool
    {
        return FuelEquipmentType::isValid($type);
    }
}
