<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Unité physique (chambre / appartement) d'un établissement — HOSP-002
 * (#7944, BC-32).
 *
 * `room_type_id` nullable : une unité sans type est un appartement locatif
 * hors typologie (gestion locative — HOSP-005). Code unique par
 * (tenant, établissement) ; statut opérationnel borné.
 *
 * @property int $id
 * @property string $company_id
 * @property int $property_id
 * @property int|null $room_type_id
 * @property string $code
 * @property string|null $floor
 * @property string $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HospitalityUnit extends Model
{
    use BelongsToCompany;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_OCCUPIED = 'occupied';

    public const STATUS_MAINTENANCE = 'maintenance';

    public const STATUS_OUT_OF_SERVICE = 'out_of_service';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_OCCUPIED,
        self::STATUS_MAINTENANCE,
        self::STATUS_OUT_OF_SERVICE,
    ];

    protected $table = 'hospitality_units';

    protected $fillable = [
        'company_id',
        'property_id',
        'room_type_id',
        'code',
        'floor',
        'status',
        'notes',
    ];

    protected $casts = [
        'room_type_id' => 'integer',
        'status' => 'string',
    ];

    /**
     * @return BelongsTo<HospitalityProperty, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(HospitalityProperty::class, 'property_id');
    }

    /**
     * @return BelongsTo<HospitalityRoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(HospitalityRoomType::class, 'room_type_id');
    }
}
