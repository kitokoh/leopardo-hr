<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Enums;

/**
 * Sens commercial d'un DOSSIER de transaction — BC-36 TRADE, TRADE-005/#8480.
 *
 * Le sens est porte par le DOSSIER, jamais par un role utilisateur : une meme
 * entreprise peut mener des dossiers d'import ET d'export sans enfermement
 * dans un role irreversible (exigence mission §« Architecture commune »).
 *
 * `DomesticPurchase` / `DomesticSale` couvrent le grossiste B2B domestique,
 * qui reutilise exactement le meme cycle commercial (seuls different le
 * marche dessert, les regles logistiques et les formalites).
 */
enum TradeDealDirection: string
{
    case Import = 'import';
    case Export = 'export';
    case DomesticPurchase = 'domestic_purchase';
    case DomesticSale = 'domestic_sale';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $direction): string => $direction->value, self::cases());
    }

    public function isDomestic(): bool
    {
        return in_array($this, [self::DomesticPurchase, self::DomesticSale], true);
    }

    /**
     * Le dossier est-il tourne vers l'achat (on achete) ou la vente (on vend) ?
     * Sert au tri des vues acheteur/vendeur — c'est la SEULE difference entre
     * les deux espaces, qui partagent tous les objets.
     */
    public function isBuying(): bool
    {
        return in_array($this, [self::Import, self::DomesticPurchase], true);
    }
}
