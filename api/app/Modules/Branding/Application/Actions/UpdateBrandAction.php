<?php

declare(strict_types=1);

namespace App\Modules\Branding\Application\Actions;

use App\Modules\Branding\Domain\Contracts\BrandCacheInterface;
use App\Modules\Branding\Domain\Enums\BrandVertical;
use App\Modules\Branding\Domain\Models\Brand;
use App\Modules\Branding\Domain\Palette\BrandPalette;

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
 * - **Effacer un domaine sur une saisie invalide.** Voir `Brand::assertValidHost()`.
 *
 * ## Pourquoi elle purge le cache de résolution publique
 *
 * `BrandResolver` mémorise une résolution réussie pendant 60 s, indexée par slug
 * ET par host. Une mise à jour change ce que la surface publique sert (identité,
 * palette, locale par défaut) et peut changer le host. Sans purge, le visiteur
 * continuait de recevoir l'ancien DTO jusqu'à une minute après l'enregistrement ;
 * et si le host changeait, l'ANCIEN domaine restait servi alors que le tenant
 * venait de le déplacer — exactement ce qu'une action de modération doit couper
 * immédiatement. Les transitions (`publish`, `suspend`) purgeaient déjà ; la
 * mise à jour était le trou.
 */
final class UpdateBrandAction
{
    public function __construct(
        private readonly BrandCacheInterface $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(Brand $brand, array $input): Brand
    {
        // Host précédent conservé : après un changement, la clé de cache de
        // l'ancien domaine doit être purgée elle aussi, sinon l'ancienne adresse
        // continue de résoudre vers la Brand.
        $previousHost = $brand->host;

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
            // `null` ou chaîne vide = retirer le domaine (intention explicite).
            // Toute autre valeur est validée : une faute de frappe ne doit pas
            // effacer le domaine existant en silence.
            $brand->host = is_string($input['host']) && trim($input['host']) !== ''
                ? Brand::assertValidHost($input['host'])
                : null;
        }

        $brand->save();

        $this->cache->forget($brand->slug, $previousHost);
        $this->cache->forget($brand->slug, $brand->host);

        return $brand;
    }
}
