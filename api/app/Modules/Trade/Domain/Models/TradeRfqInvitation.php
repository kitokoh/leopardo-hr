<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Models;

use App\Modules\Trade\Domain\Enums\TradeInvitationChannel;
use App\Modules\Trade\Domain\Enums\TradeInvitationStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TradeRfqInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Invitation d'une RFQ a un fournisseur — BC-36 TRADE, TRADE-003/#8478.
 *
 * Chaque invitation porte son PROPRE cycle (`pending` → `sent` → `viewed` →
 * `responded`/`declined`/`expired`) : une RFQ diffusee a cinq fournisseurs a
 * cinq etats independants, ce qui permet de relancer precisement ceux qui
 * n'ont pas encore repondu.
 *
 * `response_token_hash` : le lien de reponse public est stocke HACHE
 * (SHA-256) et jamais en clair — la valeur brute n'existe que dans l'e-mail
 * transmis au fournisseur. Usage borne (`response_token_used_at`) + expiration
 * (`response_token_expires_at`), et l'acces est strictement limite aux
 * informations diffusees avec l'invitation.
 *
 * @property int $id
 * @property string $company_id
 * @property int $trade_rfq_id
 * @property int $trade_counterparty_id
 * @property TradeInvitationChannel $channel
 * @property TradeInvitationStatus $status
 * @property string|null $message
 * @property string|null $response_token_hash
 * @property Carbon|null $response_token_expires_at
 * @property Carbon|null $response_token_used_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $viewed_at
 * @property Carbon|null $responded_at
 */
final class TradeRfqInvitation extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TradeRfqInvitationFactory> */
    use HasFactory;

    protected $table = 'trade_rfq_invitations';

    protected $fillable = [
        'trade_rfq_id',
        'trade_counterparty_id',
        'channel',
        'status',
        'message',
        'response_token_hash',
        'response_token_expires_at',
        'response_token_used_at',
        'sent_at',
        'viewed_at',
        'responded_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'trade_rfq_id' => 'integer',
            'trade_counterparty_id' => 'integer',
            'channel' => TradeInvitationChannel::class,
            'status' => TradeInvitationStatus::class,
            'response_token_expires_at' => 'datetime',
            'response_token_used_at' => 'datetime',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TradeRfq, $this> */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(TradeRfq::class, 'trade_rfq_id');
    }

    /** @return BelongsTo<TradeCounterparty, $this> */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(TradeCounterparty::class, 'trade_counterparty_id');
    }

    /**
     * Le lien de reponse est-il encore utilisable ?
     * Un token absent, expire ou deja consomme est refuse — fail-closed.
     */
    public function isResponseLinkUsable(): bool
    {
        if ($this->response_token_hash === null || $this->response_token_used_at !== null) {
            return false;
        }

        return $this->response_token_expires_at !== null
            && $this->response_token_expires_at->isFuture();
    }
}
