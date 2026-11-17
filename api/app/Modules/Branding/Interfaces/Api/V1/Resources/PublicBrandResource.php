<?php

declare(strict_types=1);

namespace App\Modules\Branding\Interfaces\Api\V1\Resources;

use App\Modules\Branding\Domain\Models\Brand;
use App\Modules\Branding\Domain\PublicSurface\PublicBrandPresenter;
use App\Modules\Branding\Domain\Surface\SurfaceProfileRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Représentation PUBLIQUE d'une Brand (BC-41, #9578).
 *
 * ## Liste blanche stricte
 *
 * Cette ressource est servie à des visiteurs ANONYMES. Elle ne doit exposer
 * aucun état interne du tenant : ni `company_id`, ni `is_default`, ni
 * `suspension_reason`, ni `status`. Le fait qu'une Brand soit servie prouve
 * déjà qu'elle est publiée ; renvoyer le statut n'ajouterait qu'une surface
 * d'énumération.
 *
 * ## Le profil de verticale est inclus
 *
 * C'est ce qui rend la surface « spécialisée » : le front reçoit les capacités,
 * les blocs attendus et le thème de la verticale, et rend le profil au lieu de
 * coder une page par verticale.
 *
 * @mixin Brand
 */
final class PublicBrandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Brand $brand */
        $brand = $this->resource;

        $locale = $request->query('lang');
        $locale = is_string($locale) ? strtolower(substr(trim($locale), 0, 2)) : '';

        $published = $brand->publishedLocales();
        $resolved = in_array($locale, $published, true) ? $locale : $brand->defaultLocale();

        $presenter = new PublicBrandPresenter(app(SurfaceProfileRegistry::class));

        return $presenter->present($brand, $resolved);
    }
}
