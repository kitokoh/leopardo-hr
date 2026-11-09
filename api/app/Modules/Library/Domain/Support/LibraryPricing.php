<?php

declare(strict_types=1);

namespace App\Modules\Library\Domain\Support;

use App\Modules\Library\Domain\Enums\LibraryAccessModel;
use InvalidArgumentException;

/**
 * BC-38 / BC-17 (#9238) — tarification d'une ressource de la bibliothèque.
 *
 * Décisions produit (voir le commentaire de l'issue #9238) :
 *  - les prix sont **TTC** : `price_minor` est le montant payé par l'acheteur,
 *    TVA incluse. On n'ajoute jamais la TVA par-dessus ;
 *  - le taux de TVA est porté par la ressource et **figé à la vente** : ce
 *    service le ventile, il ne le décide pas ;
 *  - la vente en gros se fait par **paliers de quantité** déclarés sur la
 *    ressource, jamais par devis négocié.
 *
 * Ce service est PUR : aucun accès base, aucune horloge, aucun aléa. C'est ce
 * qui rend le calcul testable et reproductible — une facture doit pouvoir être
 * recalculée à l'identique des années plus tard.
 */
final class LibraryPricing
{
    /**
     * Prix unitaire effectif pour une quantité donnée.
     *
     * Le palier retenu est le plus élevé dont `min_qty <= $quantity`. Les
     * paliers sont supposés triés par `min_qty` croissant ; on ne s'y fie pas
     * et on prend le maximum éligible, ce qui tolère un ordre quelconque.
     *
     * @param  list<array{min_qty: int, unit_price_minor: int}>  $wholesaleTiers
     */
    public static function unitPriceMinor(int $basePriceMinor, array $wholesaleTiers, int $quantity): int
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('La quantité doit être au moins 1.');
        }

        if ($basePriceMinor < 0) {
            throw new InvalidArgumentException('Le prix de base ne peut pas être négatif.');
        }

        $bestMinQty = 0;
        $bestPrice = $basePriceMinor;

        foreach ($wholesaleTiers as $tier) {
            $minQty = (int) ($tier['min_qty'] ?? 0);
            $unitPrice = (int) ($tier['unit_price_minor'] ?? 0);

            if ($minQty < 1) {
                throw new InvalidArgumentException('Un palier doit démarrer à une quantité au moins égale à 1.');
            }

            if ($unitPrice < 0) {
                throw new InvalidArgumentException('Un prix de palier ne peut pas être négatif.');
            }

            // `>=` : à quantité minimale égale, le dernier palier déclaré gagne.
            // C'est déterministe et évite qu'un doublon de `min_qty` rende le
            // résultat dépendant de l'ordre de lecture en base.
            if ($quantity >= $minQty && $minQty >= $bestMinQty) {
                $bestMinQty = $minQty;
                $bestPrice = $unitPrice;
            }
        }

        return $bestPrice;
    }

    /**
     * Ventilation TTC → HT + TVA, en centimes.
     *
     * Le montant TTC est la référence : on ne recalcule jamais le TTC depuis
     * le HT (cela introduirait un écart d'arrondi sur le prix affiché). La TVA
     * est arrondie au centime le plus proche, et le HT est obtenu par
     * soustraction — ainsi `ht + tva === ttc` **exactement**, ce qu'une
     * facture exige.
     *
     * @return array{total_minor: int, net_minor: int, vat_minor: int, vat_rate: float}
     */
    public static function breakdown(int $totalMinor, float $vatRate): array
    {
        if ($totalMinor < 0) {
            throw new InvalidArgumentException('Le montant total ne peut pas être négatif.');
        }

        if ($vatRate < 0.0 || $vatRate > 1.0) {
            throw new InvalidArgumentException('Le taux de TVA doit être compris entre 0 et 1.');
        }

        // tva = ttc * taux / (1 + taux) — le taux s'applique au HT, pas au TTC.
        $vatMinor = (int) round($totalMinor * $vatRate / (1.0 + $vatRate));

        return [
            'total_minor' => $totalMinor,
            'net_minor' => $totalMinor - $vatMinor,
            'vat_minor' => $vatMinor,
            'vat_rate' => $vatRate,
        ];
    }

    /**
     * Devis complet d'une ligne de commande.
     *
     * @param  list<array{min_qty: int, unit_price_minor: int}>  $wholesaleTiers
     * @return array{
     *     quantity: int,
     *     unit_price_minor: int,
     *     total_minor: int,
     *     net_minor: int,
     *     vat_minor: int,
     *     vat_rate: float
     * }
     */
    public static function quote(
        LibraryAccessModel $accessModel,
        int $basePriceMinor,
        array $wholesaleTiers,
        int $quantity,
        float $vatRate,
    ): array {
        // Une ressource gratuite ou en lecture seule n'a pas de prix : refuser
        // explicitement plutôt que renvoyer 0, qui se confondrait avec un achat
        // à 0 centime et produirait une facture vide.
        if ($accessModel !== LibraryAccessModel::Purchase) {
            throw new InvalidArgumentException(
                "Le modèle d'accès « {$accessModel->value} » n'est pas payant à l'unité.",
            );
        }

        $unitPrice = self::unitPriceMinor($basePriceMinor, $wholesaleTiers, $quantity);
        $total = $unitPrice * $quantity;

        return ['quantity' => $quantity, 'unit_price_minor' => $unitPrice] + self::breakdown($total, $vatRate);
    }

    /**
     * Numéro de facture séquentiel par tenant (décision 3 de #9238).
     *
     * Format `LIB-{année}-{séquence sur 6 chiffres}`. La séquence est fournie
     * par l'appelant (allouée sous verrou en base) : ce service ne fait que
     * formater, pour que le format reste identique partout.
     */
    public static function invoiceNumber(int $year, int $sequence): string
    {
        if ($sequence < 1) {
            throw new InvalidArgumentException('La séquence de facture démarre à 1.');
        }

        if ($year < 2000 || $year > 9999) {
            throw new InvalidArgumentException('Année de facture invalide.');
        }

        return sprintf('LIB-%04d-%06d', $year, $sequence);
    }
}
