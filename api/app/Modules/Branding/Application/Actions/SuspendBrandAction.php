<?php

declare(strict_types=1);

namespace App\Modules\Branding\Application\Actions;

use App\Modules\Branding\Domain\Models\Brand;

/**
 * Suspend une Brand (décision plateforme, BC-41, #9578).
 *
 * ## Pourquoi la purge de cache est ici aussi
 *
 * Une suspension doit couper l'accès public IMMÉDIATEMENT. Sans purge, la
 * Brand resterait servie jusqu'à 60 s après la décision — sur un contenu
 * frauduleux ou illégal, une minute de trop est une minute de trop.
 *
 * ## Ce que l'action ne fait pas
 *
 * Elle ne supprime rien : la Brand revient en brouillon avec sa trace
 * (`suspended_at`, `suspension_reason`). Une suspension est une décision
 * réversible et auditable, pas une destruction de données.
 */
final class SuspendBrandAction
{
    public function __construct(
        private readonly PublishBrandAction $publisher,
    ) {}

    public function execute(Brand $brand, string $reason): Brand
    {
        $brand->suspend($reason);
        $brand->save();

        $this->publisher->forget($brand);

        return $brand;
    }
}
