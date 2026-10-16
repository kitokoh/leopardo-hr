<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * #8544 (TRAVEL-OS) — trace d'une mise à disposition du PDF d'un billet.
 *
 * `reprint_number = 0` = première émission ; `1`, `2`, … = réimpressions.
 *
 * C'est un JOURNAL, pas une machine à états : consulter ou réimprimer un billet
 * ne change jamais son statut (`issued` reste `issued`).
 *
 * @property string $channel
 * @property string $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property int|null $employee_id
 * @property int $id
 * @property bool $is_reprint
 * @property int $reprint_number
 * @property int $ticket_id
 */
class TravelTicketPrintEvent extends Model
{
    use BelongsToCompany;

    /** @var list<string> */
    protected $fillable = [
        'ticket_id',
        'employee_id',
        'channel',
        'reprint_number',
        'is_reprint',
    ];

    protected $casts = [
        'reprint_number' => 'integer',
        'is_reprint' => 'boolean',
    ];

    /**
     * @return BelongsTo<TravelTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TravelTicket::class, 'ticket_id');
    }
}
