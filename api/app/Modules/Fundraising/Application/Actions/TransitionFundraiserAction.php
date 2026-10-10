<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Application\Actions;

use App\Modules\Fundraising\Domain\Enums\FundraiserStatus;
use App\Modules\Fundraising\Domain\Exceptions\FundraisingException;
use App\Modules\Fundraising\Domain\Models\Fundraiser;
use App\Modules\Fundraising\Domain\Models\FundraiserPublicLink;

/**
 * Transitions de cycle de vie d'une cagnotte (verticale FUNDRAISING —
 * spec §3.1) et maintien de l'annuaire public (slug → tenant) :
 *
 * - publish : `draft|paused → active` — écrit/met à jour l'entrée
 *   d'annuaire (le lien devient consultable ET contributif) ;
 * - pause : `active → paused` — retire l'entrée (404 public
 *   anti-énumération, spec §6) ;
 * - close : `active|paused|completed → closed` — la collecte s'arrête, la
 *   page publique reste lisible (statut miroir `closed`) et les
 *   reversements restent possibles.
 */
final class TransitionFundraiserAction
{
    /**
     * Point d'entrée convention #6570 : délègue au verbe demandé.
     */
    public function execute(Fundraiser $fundraiser, string $verb): Fundraiser
    {
        return match ($verb) {
            'publish' => $this->publish($fundraiser),
            'pause' => $this->pause($fundraiser),
            'close' => $this->close($fundraiser),
            default => throw FundraisingException::invalidStatusTransition($fundraiser->status->value, $verb),
        };
    }

    public function publish(Fundraiser $fundraiser): Fundraiser
    {
        if (! in_array($fundraiser->status, [FundraiserStatus::DRAFT, FundraiserStatus::PAUSED], true)) {
            throw FundraisingException::invalidStatusTransition($fundraiser->status->value, FundraiserStatus::ACTIVE->value);
        }

        $fundraiser->status = FundraiserStatus::ACTIVE;
        $fundraiser->published_at ??= now();
        $fundraiser->save();

        $this->upsertPublicLink($fundraiser);

        return $fundraiser;
    }

    public function pause(Fundraiser $fundraiser): Fundraiser
    {
        if ($fundraiser->status !== FundraiserStatus::ACTIVE) {
            throw FundraisingException::invalidStatusTransition($fundraiser->status->value, FundraiserStatus::PAUSED->value);
        }

        $fundraiser->status = FundraiserStatus::PAUSED;
        $fundraiser->save();

        $this->removePublicLink($fundraiser);

        return $fundraiser;
    }

    public function close(Fundraiser $fundraiser): Fundraiser
    {
        if (! in_array($fundraiser->status, [FundraiserStatus::ACTIVE, FundraiserStatus::PAUSED, FundraiserStatus::COMPLETED], true)) {
            throw FundraisingException::invalidStatusTransition($fundraiser->status->value, FundraiserStatus::CLOSED->value);
        }

        $fundraiser->status = FundraiserStatus::CLOSED;
        $fundraiser->save();

        // `closed` reste lisible publiquement mais ne collecte plus :
        // si l'entrée n'existe pas (ex. paused→closed), on la recrée en
        // statut miroir pour garder la transparence de la collecte.
        $this->upsertPublicLink($fundraiser);

        return $fundraiser;
    }

    private function upsertPublicLink(Fundraiser $fundraiser): void
    {
        FundraiserPublicLink::query()->updateOrCreate(
            ['slug' => $fundraiser->slug],
            ['company_id' => $fundraiser->company_id, 'status' => $fundraiser->status->value],
        );
    }

    private function removePublicLink(Fundraiser $fundraiser): void
    {
        FundraiserPublicLink::query()->where('slug', $fundraiser->slug)->delete();
    }
}
