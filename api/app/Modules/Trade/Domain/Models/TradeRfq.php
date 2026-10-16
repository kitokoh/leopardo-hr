<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Models;

use App\Modules\Trade\Domain\Enums\TradeRfqStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TradeRfqFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Demande de prix (RFQ) — BC-36 TRADE, TRADE-003/#8478.
 *
 * Le NOYAU PARTAGE : l'acheteur cree la RFQ, la diffuse a plusieurs
 * fournisseurs (invitations) et recolte des offres comparables. Le vendeur,
 * dans son espace, la voit comme une opportunite entrante.
 *
 * @property int $id
 * @property string $company_id
 * @property string $reference
 * @property int|null $trade_deal_id
 * @property string $title
 * @property TradeRfqStatus $status
 * @property string|null $destination_country
 * @property string|null $destination_port
 * @property string|null $incoterm
 * @property int|null $budget_minor
 * @property string|null $budget_currency
 * @property Carbon|null $deadline_at
 * @property string|null $notes
 * @property int|null $created_by_employee_id
 * @property Carbon|null $published_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $awarded_at
 * @property int|null $awarded_quote_id
 */
final class TradeRfq extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TradeRfqFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'trade_rfqs';

    protected $fillable = [
        'reference',
        'trade_deal_id',
        'title',
        'status',
        'destination_country',
        'destination_port',
        'incoterm',
        'budget_minor',
        'budget_currency',
        'deadline_at',
        'notes',
        'created_by_employee_id',
        'published_at',
        'closed_at',
        'awarded_at',
        'awarded_quote_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => TradeRfqStatus::class,
            'trade_deal_id' => 'integer',
            'budget_minor' => 'integer',
            'created_by_employee_id' => 'integer',
            'awarded_quote_id' => 'integer',
            'deadline_at' => 'datetime',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
            'awarded_at' => 'datetime',
        ];
    }

    /** @return HasMany<TradeRfqLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(TradeRfqLine::class, 'trade_rfq_id')->orderBy('position');
    }

    /** @return HasMany<TradeRfqInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(TradeRfqInvitation::class, 'trade_rfq_id');
    }

    /** @return HasMany<TradeQuote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(TradeQuote::class, 'trade_rfq_id');
    }
}
