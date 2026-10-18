<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Trade\Domain\Enums\TradeInvitationChannel;
use App\Modules\Trade\Domain\Enums\TradeInvitationStatus;
use App\Modules\Trade\Domain\Models\TradeCounterparty;
use App\Modules\Trade\Domain\Models\TradeRfq;
use App\Modules\Trade\Domain\Models\TradeRfqInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TradeRfqInvitation>
 */
class TradeRfqInvitationFactory extends Factory
{
    protected $model = TradeRfqInvitation::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'trade_rfq_id' => TradeRfq::factory(),
            'trade_counterparty_id' => TradeCounterparty::factory(),
            'channel' => TradeInvitationChannel::Email->value,
            'status' => TradeInvitationStatus::Pending->value,
            'message' => null,
            // Le token n'est stocke que HACHE ; la valeur brute n'existe que
            // dans l'e-mail transmis (ici : jamais posee).
            'response_token_hash' => null,
            'response_token_expires_at' => null,
            'response_token_used_at' => null,
            'sent_at' => null,
            'viewed_at' => null,
            'responded_at' => null,
        ];
    }

    /**
     * Invitation portant un lien de reponse public utilisable.
     */
    public function withResponseLink(string $plainToken = 'test-token'): static
    {
        return $this->state(fn (): array => [
            'response_token_hash' => hash('sha256', $plainToken),
            'response_token_expires_at' => now()->addDays(7),
            'status' => TradeInvitationStatus::Sent->value,
            'sent_at' => now(),
        ]);
    }
}
