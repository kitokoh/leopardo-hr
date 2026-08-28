<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Équipement d'un site (pompe, cuve, compteur, nozzle, console…) —
 * Issue #5797 (FUEL-003).
 *
 * @property int $id
 * @property string|null $company_id
 * @property int|null $site_id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property string $status
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelEquipment extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_equipment';

    protected $fillable = ['company_id', 'site_id', 'code', 'name', 'type', 'status', 'metadata'];

    protected $casts = [
        'site_id' => 'integer',
        'metadata' => 'encrypted:array',
    ];
}
