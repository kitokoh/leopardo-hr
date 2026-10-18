<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Models;

use App\Modules\Trade\Domain\Enums\TradeMessageDirection;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TradeMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Message rattache au DOSSIER — BC-36 TRADE, TRADE-005/#8480.
 *
 * Toute interaction avec la contrepartie reste attachee a la transaction :
 * pas de fil de discussion orphelin, l'historique se relit avec le dossier.
 *
 * `sent_at` n'est pose QUE si l'envoi a reellement eu lieu : un message
 * journalise sans mailer configure n'est jamais presente comme envoye.
 *
 * @property int $id
 * @property string $company_id
 * @property int $trade_deal_id
 * @property TradeMessageDirection $direction
 * @property string $body
 * @property array<int, mixed>|null $attachments
 * @property int|null $employee_id
 * @property string|null $sent_via
 * @property Carbon|null $sent_at
 */
final class TradeMessage extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TradeMessageFactory> */
    use HasFactory;

    protected $table = 'trade_messages';

    protected $fillable = [
        'trade_deal_id',
        'direction',
        'body',
        'attachments',
        'employee_id',
        'sent_via',
        'sent_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'trade_deal_id' => 'integer',
            'direction' => TradeMessageDirection::class,
            'attachments' => 'array',
            'employee_id' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TradeDeal, $this> */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(TradeDeal::class, 'trade_deal_id');
    }
}
