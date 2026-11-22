<?php

declare(strict_types=1);

namespace App\Modules\Trade\Infrastructure\Services;

use App\Modules\Trade\Domain\Models\TradeCompanyProfile;
use App\Modules\Trade\Domain\Models\TradeProduct;

/**
 * Kit de promotion de l'entreprise — BC-36 TRADE, #9783.
 *
 * ## Le besoin
 *
 * « Être capable de promouvoir son entreprise. » Le profil public existait
 * (`is_public`, `public_slug`, `public_name`, `public_description`,
 * `public_city`) et la fiche était servie par
 * `GET /api/v1/public/trade/companies/{slug}`. Mais **rien n'aidait le
 * responsable à produire le contenu** : il devait écrire son texte à la main,
 * sans repère, et découvrait à la publication qu'il manquait un champ.
 *
 * ## Ce que ce service ne fait PAS — trois refus délibérés
 *
 * 1. **Il ne publie rien.** Le kit PROPOSE un brouillon ; la publication reste
 *    l'acte séparé `PUT /trade/profile/publication`. Un kit qui publierait
 *    ferait apparaître une entreprise dans l'annuaire sans qu'elle l'ait
 *    demandé.
 *
 * 2. **Il n'invente aucune promesse.** Le brouillon n'utilise QUE les champs
 *    réellement renseignés (pays, catégories, produits du catalogue). Un champ
 *    vide n'est pas comblé par une phrase générique : une accroche du type
 *    « leader de votre marché » serait une affirmation que l'entreprise n'a
 *    pas faite. Aucune IA non plus : la génération est déterministe, donc
 *    reproductible et testable.
 *
 * 3. **Il ne rend jamais un lien qui ne mène nulle part.** Si `is_public` est
 *    faux, `publication.url` vaut `null` — pas une URL de fiche publique qui
 *    répondrait 404.
 */
final class TradePromotionKitBuilder
{
    /**
     * Construit le kit à partir du profil et du catalogue.
     *
     * Fonction pure du point de vue HTTP : aucun accès à la requête, aucune
     * écriture. Testable directement.
     *
     * @param  list<TradeProduct>  $products
     * @return array{
     *     publication: array{is_public: bool, slug: string|null, url: string|null},
     *     checklist: list<array{key: string, done: bool, hint: string}>,
     *     draft: array{headline: string|null, summary: string|null, highlights: list<string>},
     *     disclaimer: string
     * }
     */
    public function build(
        ?TradeCompanyProfile $profile,
        array $products = [],
    ): array {
        $isPublic = $profile?->is_public === true;
        $slug = $profile?->public_slug;
        $publicName = $this->nonEmpty($profile?->public_name);
        $description = $this->nonEmpty($profile?->public_description);
        $city = $this->nonEmpty($profile?->public_city);

        return [
            'publication' => [
                'is_public' => $isPublic,
                'slug' => $slug,
                // Le lien n'existe QUE si le profil est réellement joignable :
                // `isPubliclyVisible()` exige `is_public` + slug + nom.
                // Renvoyer `/entreprises/{slug}` sur un profil dépublié serait
                // un lien mort présenté comme une réussite.
                'url' => $profile !== null && $profile->isPubliclyVisible() ? '/entreprises/'.$slug : null,
            ],
            'checklist' => $this->checklist($profile, $products),
            'draft' => $this->draft($profile, $publicName, $description, $city, $products),
            'disclaimer' => $this->disclaimer(),
        ];
    }

    /**
     * Points de contrôle de la fiche publique.
     *
     * Chaque entrée dit si le champ est renseigné ET pourquoi il compte : un
     * `false` sans explication serait une simple liste de reproches.
     *
     * @param  list<TradeProduct>  $products
     * @return list<array{key: string, done: bool, hint: string}>
     */
    private function checklist(?TradeCompanyProfile $profile, array $products): array
    {
        $hasPublishedCatalog = false;
        foreach ($products as $product) {
            if ($product->status === 'active') {
                $hasPublishedCatalog = true;
                break;
            }
        }

        return [
            [
                'key' => 'public_name',
                'done' => $this->nonEmpty($profile?->public_name) !== null,
                'hint' => "Renseignez le nom commercial publié : c'est le titre affiché dans l'annuaire.",
            ],
            [
                'key' => 'public_slug',
                'done' => $this->nonEmpty($profile?->public_slug) !== null,
                'hint' => "Choisissez un identifiant d'URL (minuscules, chiffres et tirets) : il est unique et définitif.",
            ],
            [
                'key' => 'public_description',
                'done' => $this->nonEmpty($profile?->public_description) !== null,
                'hint' => "Écrivez une description de votre activité : c'est le seul texte que le visiteur lira en entier.",
            ],
            [
                'key' => 'public_city',
                'done' => $this->nonEmpty($profile?->public_city) !== null,
                'hint' => 'Indiquez votre ville : les acheteurs filtrent souvent par proximité.',
            ],
            [
                'key' => 'hq_country',
                'done' => $this->nonEmpty($profile?->hq_country) !== null,
                'hint' => "Renseignez le pays du siège : c'est la première information recherchée à l'import-export.",
            ],
            [
                'key' => 'directions',
                'done' => ($profile?->import_enabled === true) || ($profile?->export_enabled === true),
                'hint' => "Activez au moins une direction (import ou export) : sans elle, votre activité n'est pas qualifiée.",
            ],
            [
                'key' => 'product_categories',
                'done' => is_array($profile?->product_categories) && $profile->product_categories !== [],
                'hint' => "Déclarez vos catégories de produits : c'est ce qui vous fait apparaître dans les recherches par secteur.",
            ],
            [
                'key' => 'catalog',
                'done' => $hasPublishedCatalog,
                'hint' => 'Ajoutez au moins un produit actif au catalogue : une fiche sans produit ne génère aucune demande.',
            ],
        ];
    }

    /**
     * Brouillon de présentation — déterministe, adossé aux données réelles.
     *
     * ## Le nom vient de `public_name`, jamais de la raison sociale
     *
     * `public_name` est documenté comme « nom commercial publiable (≠ raison
     * sociale) ». Construire une accroche PUBLIQUE à partir de la raison sociale
     * pousserait l'utilisateur à publier un nom qu'il n'a pas choisi — et la
     * distinction entre les deux champs existe précisément pour l'éviter. Sans
     * `public_name`, le brouillon reste vide et la checklist dit quoi remplir :
     * le kit se régénère ensuite avec le bon nom.
     *
     * @param  list<TradeProduct>  $products
     * @return array{headline: string|null, summary: string|null, highlights: list<string>}
     */
    private function draft(
        ?TradeCompanyProfile $profile,
        ?string $publicName,
        ?string $description,
        ?string $city,
        array $products,
    ): array {
        // Nom commercial UNIQUEMENT : la raison sociale n'est pas un nom
        // publiable (cf. doc du champ `public_name`).
        $name = $publicName;

        $directions = [];
        if ($profile?->import_enabled === true) {
            $directions[] = 'import';
        }
        if ($profile?->export_enabled === true) {
            $directions[] = 'export';
        }

        $categories = is_array($profile?->product_categories) ? $profile->product_categories : [];
        $categories = array_values(array_filter($categories, static fn ($value): bool => is_string($value) && $value !== ''));

        // Accroche : construite UNIQUEMENT si le nom existe. Sinon `null` — une
        // accroche sans nom serait une phrase creuse.
        $headline = null;
        if ($name !== null) {
            $parts = [$name];
            if ($directions !== []) {
                $parts[] = '— '.implode(' et ', $directions);
            }
            if ($categories !== []) {
                $parts[] = implode(', ', array_slice($categories, 0, 3));
            }
            $headline = implode(' ', $parts);
        }

        // Résumé : la description de l'entreprise si elle en a écrit une, sinon
        // un assemblage factuel des champs réellement renseignés. Jamais de
        // superlatif, jamais de promesse de délai ou de capacité.
        $summary = $description;
        if ($summary === null && $name !== null) {
            $facts = [];
            if ($city !== null) {
                $facts[] = 'Établie à '.$city;
            }
            if ($profile?->hq_country !== null && $profile->hq_country !== '') {
                $facts[] = 'pays du siège : '.$profile->hq_country;
            }
            if ($directions !== []) {
                $facts[] = 'activité : '.implode(' et ', $directions);
            }
            if ($facts !== []) {
                $summary = $name.' — '.implode(' ; ', $facts).'.';
            }
        }

        $highlights = [];

        if ($categories !== []) {
            $highlights[] = 'Catégories de produits : '.implode(', ', $categories).'.';
        }

        if ($profile?->hq_country !== null && $profile->hq_country !== '') {
            $highlights[] = 'Pays du siège : '.$profile->hq_country.'.';
        }

        if ($profile?->default_currency !== null && $profile->default_currency !== '') {
            $highlights[] = 'Devise par défaut : '.$profile->default_currency.'.';
        }

        if ($profile?->default_incoterm !== null && $profile->default_incoterm !== '') {
            $highlights[] = 'Incoterm habituel : '.$profile->default_incoterm.'.';
        }

        // Catalogue : on cite des produits RÉELS, jamais une gamme inventée.
        $namedProducts = [];
        foreach ($products as $product) {
            if ($product->status === 'active' && $this->nonEmpty($product->name) !== null) {
                $namedProducts[] = $product->name;
            }
        }

        if ($namedProducts !== []) {
            $highlights[] = count($namedProducts).' produit(s) actif(s) au catalogue, dont : '
                .implode(', ', array_slice($namedProducts, 0, 3)).'.';
        }

        return [
            'headline' => $headline,
            'summary' => $summary,
            'highlights' => $highlights,
        ];
    }

    private function disclaimer(): string
    {
        return "Ce kit propose un brouillon construit à partir des informations déjà enregistrées dans votre profil : "
            ."il ne publie rien et n'ajoute aucune affirmation que vous n'ayez saisie. "
            .'La publication reste une action distincte, et vous en restez responsable.';
    }

    /** Retourne la chaîne élaguée, ou `null` si elle est vide. */
    private function nonEmpty(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
