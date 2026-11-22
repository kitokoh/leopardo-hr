<?php

declare(strict_types=1);

namespace App\Modules\Trade\Interfaces\Api\V1\Resources;

/**
 * Produit du catalogue vu par le GRAND PUBLIC — BC-36 TRADE, #9768.
 *
 * ## Périmètre volontairement réduit
 *
 * Un acheteur professionnel non authentifié ne voit que ce qui l'aide à
 * décider de contacter l'entreprise : quoi, combien, dans quelle unité, à
 * partir de quel volume, à quel prix **indicatif**, d'où ça vient.
 *
 * Sont **exclus** :
 *  - `hs_code` — donnée de conformité douanière interne, pas un argument de
 *    vente, et un code faux ou mal interprété exposerait l'entreprise ;
 *  - `status` — la liste publique ne contient déjà que des produits actifs ;
 *  - tout identifiant tenant.
 *
 * Le prix est présenté comme **indicatif** : le catalogue n'est pas une offre
 * ferme. Un prix ferme n'existe que dans une offre (`TradeQuote`), négociée.
 *
 * @param array{
 *   id: int|string,
 *   name: string,
 *   description?: string|null,
 *   category?: string|null,
 *   origin_country?: string|null,
 *   unit?: string|null,
 *   moq_quantity?: string|float|null,
 *   indicative_unit_price_minor?: int|null,
 *   currency?: string|null,
 *   specs?: array<string, mixed>|null
 * } $product
 */
final class PublicTradeProductResource
{
    /**
     * @param array<string, mixed> $product
     *
     * @return array<string, mixed>
     */
    public static function make(array $product): array
    {
        $specs = $product['specs'] ?? null;

        if (is_string($specs)) {
            /** @var mixed $decoded */
            $decoded = json_decode($specs, true);
            $specs = is_array($decoded) ? $decoded : [];
        }

        return [
            'name' => (string) ($product['name'] ?? ''),
            'description' => $product['description'] ?? null,
            'category' => $product['category'] ?? null,
            'origin_country' => $product['origin_country'] ?? null,
            'unit' => $product['unit'] ?? null,
            'moq_quantity' => $product['moq_quantity'] ?? null,
            'indicative_unit_price_minor' => $product['indicative_unit_price_minor'] ?? null,
            'currency' => $product['currency'] ?? null,
            'specs' => is_array($specs) ? $specs : [],
        ];
    }
}
