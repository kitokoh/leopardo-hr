<?php

namespace App\Listeners;

use App\Events\CompanyCreated;
use App\Modules\Billing\Domain\Models\Partner;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;

class LinkPartnerToNewCompany
{
    public function handle(CompanyCreated $event): void
    {
        // #6958 : listener auxiliaire — un échec ici (table/schéma absent sur
        // un env partiel, ex. DEV) ne doit JAMAIS faire échouer la création du
        // tenant (le provisioning tourne dans une transaction) : on loggue et
        // on laisse la création d'entreprise aboutir.
        try {
            $this->link($event);
        } catch (\Throwable $exception) {
            Log::warning('CompanyCreated → LinkPartnerToNewCompany failed (non bloquant)', [
                'company_id' => $event->company->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function link(CompanyCreated $event): void
    {
        // Manual referral code takes precedence (already assigned in Controller)
        if ($event->company->referrer_partner_id) {
            return;
        }

        $referrerId = Cookie::get('leopardo_referrer_id');

        if (! $referrerId) {
            return;
        }

        $partner = Partner::find($referrerId);

        if (! $partner || $partner->status !== 'active') {
            return;
        }

        // Anti-auto-referral
        if ($event->company->email === $partner->user->email) {
            Log::warning("Auto-referral attempt blocked for partner {$partner->id} on company {$event->company->id}");

            return;
        }

        $event->company->referrer_partner_id = $partner->id;
        $event->company->save();

        \App\Modules\Billing\Domain\Models\PartnerReferral::updateOrCreate(
            ['company_id' => $event->company->id],
            [
                'partner_id' => $partner->id,
                'referred_at' => now(),
            ]
        );

        Log::info("Company {$event->company->id} linked to partner {$partner->id}");
    }
}
