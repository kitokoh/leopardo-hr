<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\PrintJobStatus;
use App\Modules\RestaurantManager\Domain\Enums\PrintTicketKind;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantPrintJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Travail d'impression thermique (REST-OS-2, Mission Leopardo Restaurant OS).
 *
 * Un travail représente un ticket à imprimer pour une commande donnée. La
 * paire (commande, type, révision) est unique par tenant : rejouer une demande
 * d'impression ne crée jamais de doublon. `status` suit PrintJobStatus et ne
 * passe à `printed` que sur accusé explicite du bridge local.
 */
class RestaurantPrintJob extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantPrintJobFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'order_id',
        'ticket_kind',
        'revision',
        'status',
        'attempts',
        'printer_ref',
        'last_error',
        'payload_hash',
        'claimed_at',
        'printed_at',
        'failed_at',
    ];

    protected $casts = [
        'ticket_kind' => PrintTicketKind::class,
        'revision' => 'integer',
        'status' => PrintJobStatus::class,
        'attempts' => 'integer',
        'claimed_at' => 'datetime',
        'printed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<RestaurantOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(RestaurantOrder::class, 'order_id');
    }

    /**
     * @return BelongsTo<RestaurantBranch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }
}
