<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $partner_link_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $referrer_url
 * @property Carbon $clicked_at
 */
class PartnerClick extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'partner_link_id',
        'ip_address',
        'user_agent',
        'referrer_url',
        'clicked_at',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    /** @return BelongsTo<PartnerLink, $this> */
    public function partnerLink(): BelongsTo
    {
        return $this->belongsTo(PartnerLink::class);
    }
}
