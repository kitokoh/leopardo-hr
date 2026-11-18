<?php

declare(strict_types=1);

namespace App\Modules\Branding\Application\Actions;

use App\Modules\Branding\Domain\Enums\BrandVertical;
use App\Modules\Branding\Domain\Models\Brand;
use App\Modules\Branding\Domain\Palette\BrandPalette;
use App\Modules\Branding\Domain\Services\BrandResolver;

/**
 * Met à jour une Brand existante (BC-41, #9578).
 *
 * ## Ce que l'action refuse de faire
 *
 * - **Changer le slug d'une Brand publiée.** Le slug est une URL publique :
 *   le changer casse tous les liens entrants et le référencement. Un tenant
 *   qui veut un nouveau slug crée une nouvelle Brand et redirige — c'est une
 *   décision consciente, pas un effet de bord d'un formulaire.
 * - **Accepter `on_primary` du client.** La palette est reconstruite par le
 *   domaine, donc le contraste est recalculé. Une valeur fournie est ignorée.
 * - **Publier implicitement.** Mettre à jour une Brand ne la publie pas : la
 *   publication est une transition explicite (`PublishBrandAction`).
 */
final class UpdateBrandAction
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(Brand $brand, array $input): Brand
    {
        if (array_key_exists('name', $input) && is_string($input['name']) && trim($input['name']) !== '') {
            $brand->name = $input['name'];
        }

        if (array_key_exists('vertical', $input) && is_string($input['vertical'])) {
            $vertical = BrandVertical::tryFrom($input['vertical']);

            // Une verticale inconnue est refusée, pas silencieusement remplacée
            // par `generic` : le tenant croirait avoir changé de verticale.
            if ($vertical === null) {
                throw new \InvalidArgumentException(sprintf(
                    'BRAND_VERTICAL_UNKNOWN: « %s » n\'est pas une verticale du registre.',
                    $input['vertical'],
                ));
            }

            $brand->vertical = $vertical;
        }

        if (array_key_exists('palette', $input) && is_array($input['palette'])) {
            $current = $brand->palette();
            $palette = $input['palette'];

            $brand->palette = BrandPalette::of(
                primary: is_string($palette['primary'] ?? null) ? $palette['primary'] : $current->primary,
                accent: is_string($palette['accent'] ?? null) ? $palette['accent'] : $current->accent,
                surface: is_string($palette['surface'] ?? null) ? $palette['surface'] : $current->surface,
            )->toArray();
        }

        if (array_key_exists('identity', $input) && is_array($input['identity'])) {
            $brand->identity = array_merge(
                is_array($brand->identity) ? $brand->identity : [],
                $input['identity'],
            );
        }

        if (array_key_exists('surface', $input) && is_array($input['surface'])) {
            $brand->surface = array_merge(
                is_array($brand->surface) ? $brand->surface : [],
                $input['surface'],
            );
        }

        if (array_key_exists('host', $input)) {
            $brand->host = is_string($input['host']) && $input['host'] !== ''
                ? BrandResolver::normalizeHost($input['host'])
                : null;
        }

        $brand->save();

        return $brand;
    }
}
