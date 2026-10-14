<?php

declare(strict_types=1);

namespace App\Modules\Trade\Infrastructure\Services;

use App\Modules\Trade\Domain\Enums\TradeCostLineType;
use App\Modules\Trade\Domain\Enums\TradeCostSource;
use App\Modules\Trade\Domain\Enums\TradeIncoterm;

/**
 * Calcul du cout total rendu — BC-36 TRADE, TRADE-007/#8482.
 *
 * SERVICE PUR ET DETERMINISTE : aucune dependance base, reseau, horloge ou
 * modele Eloquent. L'entree est un tableau de lignes SCALAIRES et la sortie ne
 * depend que d'elle — le calcul est donc testable en unite, reproductible, et
 * deux personnes qui saisissent les memes postes obtiennent exactement le meme
 * total. La persistance est la responsabilite de l'action appelante.
 *
 * TROIS REGLES D'HONNETETE, structurelles :
 *
 *  1. **Aucun poste indisponible n'entre dans le total.** Une ligne
 *     `unavailable` est listee et signalee (`incomplete`), mais elle ne
 *     contribue pas au montant : le systeme ne comble JAMAIS un trou par une
 *     valeur plausible.
 *
 *  2. **Sans taux de change, aucune conversion.** Si le taux est absent, une
 *     ligne en devise etrangere reste NON convertie, est signalee
 *     (`unconverted_types`) et n'entre pas dans le total — il est impossible
 *     de produire un total silencieusement faux.
 *
 *  3. **Le perimetre de l'Incoterm est explicite.** Le fret principal et
 *     l'assurance, s'ils sont absents, sont remontes
 *     (`missing_expected_types`) : le total ne peut pas passer pour un prix
 *     final alors que des postes attendus manquent.
 *
 * Math en ENTIERS (minor units) : jamais de flottant sur de l'argent.
 */
final class TradeLandedCostCalculator
{
    /**
     * Postes du fret international dont l'absence doit etre SIGNALEE : sans
     * eux, un total reste partiel et ne se compare pas a une offre incluant
     * le transport.
     *
     * @var list<TradeCostLineType>
     */
    private const FREIGHT_TYPES = [
        TradeCostLineType::InternationalFreight,
        TradeCostLineType::Insurance,
    ];

    /**
     * Calcule le total rendu a partir de lignes scalaires.
     *
     * @param  list<array{type: string, source: string, amount_minor: int, currency: string}>  $lines
     * @return array{
     *     total_minor: int,
     *     incomplete: bool,
     *     unavailable_types: list<string>,
     *     unconverted_types: list<string>,
     *     missing_expected_types: list<string>,
     *     by_type: array<string, int>
     * }
     */
    public function compute(
        array $lines,
        ?TradeIncoterm $incoterm = null,
        ?float $fxRate = null,
        string $displayCurrency = '',
    ): array {
        $total = 0;
        $byType = [];
        $unavailable = [];
        $unconverted = [];
        $presentTypes = [];

        foreach ($lines as $line) {
            $type = TradeCostLineType::from($line['type']);
            $source = TradeCostSource::from($line['source']);

            // Regle 1 : un poste indisponible est signale et EXCLU du total.
            if (! $source->countsTowardTotal()) {
                $unavailable[] = $type->value;

                continue;
            }

            $presentTypes[] = $type->value;
            $amount = (int) $line['amount_minor'];

            // Regle 2 : conversion seulement si un taux est REELLEMENT connu.
            if ($this->needsConversion($line['currency'], $displayCurrency)) {
                if ($fxRate === null || $fxRate <= 0.0) {
                    $unconverted[] = $type->value;

                    continue;
                }

                $amount = (int) round($amount * $fxRate);
            }

            $total += $amount;
            $byType[$type->value] = ($byType[$type->value] ?? 0) + $amount;
        }

        // Regle 3 : signaler les postes attendus mais absents.
        $missing = $this->missingExpectedTypes($presentTypes, $incoterm);

        return [
            'total_minor' => $total,
            'incomplete' => $unavailable !== [] || $unconverted !== [] || $missing !== [],
            'unavailable_types' => array_values(array_unique($unavailable)),
            'unconverted_types' => array_values(array_unique($unconverted)),
            'missing_expected_types' => $missing,
            'by_type' => $byType,
        ];
    }

    /**
     * Le total est-il exploitable comme prix final ?
     * Un total incomplet reste AFFICHABLE, mais doit etre annonce comme tel.
     */
    public function isFinal(array $result): bool
    {
        return $result['incomplete'] === false;
    }

    /**
     * @param  list<string>  $presentTypes
     * @return list<string>
     */
    private function missingExpectedTypes(array $presentTypes, ?TradeIncoterm $incoterm): array
    {
        // Sans Incoterm connu, le perimetre attendu est indeterminable : on ne
        // signale rien plutot que d'inventer une expectative.
        if ($incoterm === null) {
            return [];
        }

        $missing = [];

        foreach (self::FREIGHT_TYPES as $type) {
            if (in_array($type->value, $presentTypes, true)) {
                continue;
            }

            // Fret principal : attendu des lors qu'il est a la charge de
            // l'acheteur. Assurance : attendue comme poste a part entiere
            // puisqu'elle n'est incluse dans le prix vendeur qu'en CIF/CIP.
            if ($incoterm->buyerPaysFreight() || $type === TradeCostLineType::Insurance) {
                $missing[] = $type->value;
            }
        }

        return $missing;
    }

    /**
     * Une conversion n'est requise que si la devise de la ligne differe de la
     * devise d'affichage, ET que celle-ci est connue.
     */
    private function needsConversion(string $lineCurrency, string $displayCurrency): bool
    {
        return $displayCurrency !== '' && $lineCurrency !== $displayCurrency;
    }
}
