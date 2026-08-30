<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Devis corporate (TRAVEL-803, issue #6094).
 *
 * Prix TOUJOURS calculé serveur depuis les tarifs du trajet ; cycle
 * draft → accepted → cancelled|expired. Le devis accepté « fige » le
 * montant de la réservation de groupe.
 */
class TravelQuote extends Model
{
    use BelongsToCompany;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'company_id',
        'corporate_account_id',
        'trip_id',
        'class_id',
        'passengers_count',
        'total_amount_minor',
        'currency',
        'status',
        'expires_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'passengers_count' => 'integer',
        'total_amount_minor' => 'integer',
        'expires_at' => 'datetime',
    ];
}
