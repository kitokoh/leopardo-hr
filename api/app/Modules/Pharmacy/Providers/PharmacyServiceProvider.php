<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Providers;

use App\Core\Solutions\SolutionCatalogue;
use App\Modules\Pharmacy\Domain\Models\PharmacyBatch;
use App\Modules\Pharmacy\Domain\Models\PharmacyProduct;
use App\Modules\Pharmacy\Domain\Models\PharmacyPurchaseOrder;
use App\Modules\Pharmacy\Domain\Models\PharmacySale;
use App\Modules\Pharmacy\Domain\Models\PharmacySupplier;
use App\Modules\Pharmacy\Domain\Policies\PharmacyProductPolicy;
use App\Modules\Pharmacy\Domain\Policies\PharmacyPurchaseOrderPolicy;
use App\Modules\Pharmacy\Domain\Policies\PharmacySalePolicy;
use App\Modules\Pharmacy\Domain\Policies\PharmacyStockPolicy;
use App\Modules\Pharmacy\Domain\Policies\PharmacySupplierPolicy;
use App\Modules\Pharmacy\Domain\Solution\PharmacyManifest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Module Pharmacy (PharmaManager) — PHARMA-001 (#7798).
 *
 * Enregistre le manifest de la solution `pharmacy` dans le catalogue
 * (allowlist fail-closed, garde d'isolation #5584 : le catalogue ne
 * référence jamais `App\Modules\*` directement — c'est le module qui
 * s'enregistre) et les policies RBAC du module (composition root
 * décentralisée, PA2-ARCH-003).
 */
class PharmacyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->resolving(SolutionCatalogue::class, function (SolutionCatalogue $catalogue): void {
            $catalogue->register('pharmacy', static fn (): PharmacyManifest => new PharmacyManifest);
        });
    }

    public function boot(): void
    {
        Gate::policy(PharmacyProduct::class, PharmacyProductPolicy::class);
        Gate::policy(PharmacyBatch::class, PharmacyStockPolicy::class);
        Gate::policy(PharmacySupplier::class, PharmacySupplierPolicy::class);
        Gate::policy(PharmacyPurchaseOrder::class, PharmacyPurchaseOrderPolicy::class);
        Gate::policy(PharmacySale::class, PharmacySalePolicy::class);
    }
}
