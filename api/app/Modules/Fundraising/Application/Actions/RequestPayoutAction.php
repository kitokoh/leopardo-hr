<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Application\Actions;

use App\Modules\Fundraising\Domain\Enums\PayoutMethod;
use App\Modules\Fundraising\Domain\Exceptions\FundraisingException;
use App\Modules\Fundraising\Domain\Models\Fundraiser;
use App\Modules\Fundraising\Domain\Models\FundraisingPayout;
use App\Modules\Fundraising\Infrastructure\Services\PayoutPlacement;

/**
 * Demande de reversement au bénéficiaire (verticale FUNDRAISING — spec
 * §3.3) : validation du statut de la cagnotte et du montant, puis
 * placement transactionnel (règle de solde sous verrou ligne) délégué à
 * `PayoutPlacement` (Infrastructure — les facades y sont autorisées).
 */
final class RequestPayoutAction
{
    public function __construct(
        private readonly PayoutPlacement $placement,
    ) {}

    /**
     * @param  array<string, mixed>  $data  payload validé (RequestPayoutRequest)
     */
    public function execute(Fundraiser $fundraiser, array $data, ?int $requestedBy = null): FundraisingPayout
    {
        if (! $fundraiser->status->allowsPayout()) {
            throw FundraisingException::invalidStatusTransition($fundraiser->status->value, 'payout');
        }

        $amount = (float) $data['amount'];

        if ($amount <= 0) {
            throw FundraisingException::payoutExceedsBalance();
        }

        return $this->placement->place($fundraiser, [
            'amount' => $amount,
            'method' => PayoutMethod::from((string) $data['method']),
            'recipient_name' => (string) $data['recipient_name'],
            'recipient_account' => (string) $data['recipient_account'],
        ], $requestedBy);
    }
}
