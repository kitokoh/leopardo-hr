<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Session de caisse du PDV tablette (TRAVEL-810, issue #6100).
 *
 * Une seule session ouverte par tenant ; la clôture compare l'ATTENDU
 * (solde initial + paiements cash confirmés) au RÉEL saisi → écart.
 */
class TravelCashSession extends Model
{
    use BelongsToCompany;

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'company_id',
        'opened_by_user_id',
        'opened_at',
        'closed_at',
        'opening_balance_minor',
        'expected_balance_minor',
        'actual_balance_minor',
        'difference_minor',
        'status',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_balance_minor' => 'integer',
        'expected_balance_minor' => 'integer',
        'actual_balance_minor' => 'integer',
        'difference_minor' => 'integer',
    ];
}
