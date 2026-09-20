<?php

declare(strict_types=1);

namespace App\Modules\HospitalityManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Réservation (guichet ou en ligne) — HOSP-004 (#7946, BC-32).
 *
 * Machine à états (spec §5, transitions gardées — 409
 * INVALID_RESERVATION_TRANSITION) :
 *   pending    → confirmed | cancelled (expiration automatique si online)
 *   confirmed  → checked_in | cancelled | no_show
 *   checked_in → checked_out
 *   checked_out / cancelled / no_show → états terminaux.
 *
 * @property int $id
 * @property string $company_id
 * @property string $reference
 * @property int $property_id
 * @property int $room_type_id
 * @property int|null $unit_id
 * @property string $guest_name
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property Carbon $check_in
 * @property Carbon $check_out
 * @property int $adults
 * @property int $children
 * @property string $status
 * @property int|null $total_amount_minor
 * @property string|null $currency
 * @property string $source
 * @property Carbon|null $expires_at
 * @property string|null $idempotency_key
 * @property string|null $tracking_code_hash
 * @property string|null $notes
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HospitalityReservation extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CHECKED_IN = 'checked_in';

    public const STATUS_CHECKED_OUT = 'checked_out';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_NO_SHOW = 'no_show';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_CHECKED_IN,
        self::STATUS_CHECKED_OUT,
        self::STATUS_CANCELLED,
        self::STATUS_NO_SHOW,
    ];

    public const SOURCE_DESK = 'desk';

    public const SOURCE_ONLINE = 'online';

    /** @var list<string> */
    public const SOURCES = [
        self::SOURCE_DESK,
        self::SOURCE_ONLINE,
    ];

    /**
     * Machine à états : statut → transitions autorisées.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_CHECKED_IN, self::STATUS_CANCELLED, self::STATUS_NO_SHOW],
        self::STATUS_CHECKED_IN => [self::STATUS_CHECKED_OUT],
        self::STATUS_CHECKED_OUT => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_NO_SHOW => [],
    ];

    /** Statuts qui immobilisent l'inventaire (comptage anti-overbooking). */
    public const INVENTORY_HOLDING_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_CHECKED_IN,
    ];

    protected $table = 'hospitality_reservations';

    protected $fillable = [
        'company_id',
        'reference',
        'property_id',
        'room_type_id',
        'unit_id',
        'guest_name',
        'contact_email',
        'contact_phone',
        'check_in',
        'check_out',
        'adults',
        'children',
        'status',
        'total_amount_minor',
        'currency',
        'source',
        'expires_at',
        'idempotency_key',
        'tracking_code_hash',
        'notes',
        'version',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'expires_at' => 'datetime',
        'adults' => 'integer',
        'children' => 'integer',
        'total_amount_minor' => 'integer',
        'version' => 'integer',
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

    /**
     * @return BelongsTo<HospitalityUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(HospitalityUnit::class, 'unit_id');
    }

    /**
     * La transition vers `$target` est-elle autorisée depuis le statut courant ?
     */
    public function canTransitionTo(string $target): bool
    {
        return in_array($target, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * Une réservation online pending dont expires_at est dépassé n'immobilise
     * plus l'inventaire (elle sera annulée par la commande d'expiration).
     */
    public function holdsInventory(): bool
    {
        if (! in_array($this->status, self::INVENTORY_HOLDING_STATUSES, true)) {
            return false;
        }

        if ($this->status === self::STATUS_PENDING
            && $this->expires_at !== null
            && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
