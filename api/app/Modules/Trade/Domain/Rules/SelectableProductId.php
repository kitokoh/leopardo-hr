<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Rules;

use App\Modules\Trade\Domain\Models\TradeProduct;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Produit catalogue selectionnable dans une LIGNE de RFQ ou d'offre — #9777.
 *
 * ## Le trou que cette regle ferme
 *
 * `RfqStoreRequest` et `QuoteStoreRequest` acceptaient `lines.*.trade_product_id`
 * avec la seule regle `integer|min:1`. Rien ne verifiait que l'id designait un
 * produit du TENANT COURANT. Un id d'une autre entreprise passait donc la
 * validation et etait ecrit dans la ligne : le produit est ensuite introuvable
 * au chargement (le scope `company` le masque) et la ligne pointe dans le vide.
 *
 * ## Regles portees ici
 *
 *  1. **Tenant** : le produit doit appartenir au tenant courant. Le scope
 *     `BelongsToCompany` l'applique deja — un produit d'une autre entreprise
 *     est introuvable ici, donc refuse.
 *  2. **Statut** : un produit ARCHIVE ne peut pas entrer dans une NOUVELLE
 *     ligne. Les lignes historiques qui le referencent restent lisibles : la
 *     regle ne s'applique qu'a l'ECRITURE (`store`), jamais a la lecture.
 *  3. **Indistinguabilite** : produit inexistant, produit d'un autre tenant et
 *     produit archive produisent le MEME message. Distinguer les cas
 *     revelerait l'existence d'un produit qu'on n'a pas le droit de voir.
 */
final class SelectableProductId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            // La saisie LIBRE reste permise : une ligne sans produit catalogue
            // ne doit jamais etre bloquee (cf. docblock de TradeRfqLine).
            return;
        }

        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            $fail(__('trade.lines.product_invalid'));

            return;
        }

        // Lecture sous le scope tenant courant : un produit d'une autre
        // entreprise n'est tout simplement pas dans ce resultat.
        $product = TradeProduct::query()->whereKey((int) $value)->first();

        if (! $product instanceof TradeProduct) {
            $fail(__('trade.lines.product_invalid'));

            return;
        }

        if ($product->isArchived()) {
            $fail(__('trade.lines.product_archived'));

            return;
        }
    }
}
