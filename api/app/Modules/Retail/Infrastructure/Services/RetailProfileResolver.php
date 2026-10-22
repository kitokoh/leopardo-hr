<?php

declare(strict_types=1);

namespace App\Modules\Retail\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Application\Services\RetailMarketplaceService;
use App\Shared\Contracts\Discovery\PublicProfileResolver;

/**
 * C-1 (#8773) — Résolution d'une boutique publique vers son tenant.
 *
 * Même source de vérité que {@see RetailDiscoverableProvider}
 * (`RetailMarketplaceService::eligibleCompanyIds()`). Le slug public Retail est
 * `companies.slug` — celui déjà utilisé par `GET /api/v1/public/market/sellers/{slug}`.
 *
 * Fail-closed : `null` dès que la boutique n'est plus opt-in, que la feature
 * `retail` a été retirée ou que la société est `suspended`/`expired`.
 */
final class RetailProfileResolver implements PublicProfileResolver
{
    public function __construct(private readonly RetailMarketplaceService $marketplace) {}

    public function type(): string
    {
        return 'retail_shop';
    }

    public function resolve(string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }

        /** @var list<string> $eligible */
        $eligible = $this->marketplace->eligibleCompanyIds();

        if ($eligible === []) {
            return null;
        }

        $company = $this->marketplace->eligibleCompanyForSlug($eligible, $slug);

        if (! $company instanceof Company) {
            return null;
        }

        // Re-vérification explicite : `eligibleCompanyForSlug` filtre déjà,
        // mais l'isolation est une garantie de sécurité, pas une déduction.
        if (! in_array((string) $company->id, $eligible, true)) {
            return null;
        }

        $settings = $this->marketplace->settingsByCompanyId([(string) $company->id]);

        $shopName = null;

        foreach ($settings as $shopSettings) {
            $shopName = $shopSettings->shop_name;

            break;
        }

        return [
            'company_id' => (string) $company->id,
            'display_name' => $shopName ?? $company->name,
        ];
    }
}
