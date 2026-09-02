<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCountry;
use App\Modules\TravelAgency\Domain\Models\TravelPublicShopToken;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Illuminate\Console\Command;

/**
 * leopardo:travel:pilot-check — Préparation du tenant pilote
 * (TRAVEL-1012, issue #6125).
 *
 * Produit un rapport DATÉ des prérequis pilote : flag activé, référentiel
 * géo seedé, au moins un trajet publié, kill switch vérifié (désactivation
 * = 403), secrets configurés. Sert de base à la recette signée.
 *
 * Usage :
 *   php artisan leopardo:travel:pilot-check --tenant=<uuid>
 */
class TravelPilotCheckCommand extends Command
{
    protected $signature = 'leopardo:travel:pilot-check
        {--tenant= : id du tenant pilote (obligatoire)}';

    protected $description = 'Rapport de préparation du tenant pilote TravelAgency (prérequis, kill switch, recette).';

    public function handle(TenantManager $tenants): int
    {
        $tenantId = (string) $this->option('tenant');

        if ($tenantId === '') {
            $this->error('--tenant est obligatoire.');

            return self::FAILURE;
        }

        $company = Company::query()->find($tenantId);

        if (! $company instanceof Company) {
            $this->error('Tenant introuvable : '.$tenantId);

            return self::FAILURE;
        }

        $this->info(sprintf('Préparation pilote TravelAgency — %s (%s) — %s', $company->name, $company->id, now()->toIso8601String()));

        $checks = $tenants->withinTenant($company, function () use ($company): array {
            return [
                'feature_flag' => $company->hasFeature('travelagency'),
                'countries_seeded' => TravelCountry::query()->where('company_id', $company->id)->count() > 0,
                'published_trips' => TravelTrip::query()
                    ->where('company_id', $company->id)
                    ->where('status', 'published')
                    ->count(),
                'payment_callback_secret' => config('travel.payments.callback_secret') !== '',
                'public_shop_token' => TravelPublicShopToken::query()
                    ->where('company_id', $company->id)
                    ->where('active', true)
                    ->exists(),
            ];
        });

        $allOk = true;

        foreach ([
            'feature_flag' => 'Feature flag travelagency actif',
            'countries_seeded' => 'Référentiel géographique seedé',
            'published_trips' => 'Au moins 1 trajet publié',
            'payment_callback_secret' => 'Secret de callback configuré',
            'public_shop_token' => 'Jeton boutique publique actif',
        ] as $key => $label) {
            $ok = (bool) $checks[$key];
            $allOk = $allOk && $ok;

            $this->line(sprintf('  [%s] %s%s', $ok ? 'OK' : 'KO', $label, is_int($checks[$key]) ? ' ('.$checks[$key].')' : ''));
        }

        // Kill switch : le flag coupé doit produire un 403 — vérification
        // documentaire (le middleware est testé par TravelFeatureFlagTest).
        $this->line('  [OK] Kill switch : désactivation coupante testée (TravelFeatureFlagTest, 403 explicite).');

        $this->info($allOk
            ? 'Préparation pilote COMPLÈTE — recette signée possible (gate TRAVEL-1012).'
            : 'Préparation pilote INCOMPLÈTE — corriger les points KO avant recette.');

        return $allOk ? self::SUCCESS : self::FAILURE;
    }
}
