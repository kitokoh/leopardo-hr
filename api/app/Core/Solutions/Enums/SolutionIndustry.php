<?php

declare(strict_types=1);

namespace App\Core\Solutions\Enums;

/**
 * Industrie d'une solution sectorielle — BOS-013 (#8200).
 *
 * Registre FERMÉ et VERSIONNÉ (v1) : extension par nouvelle case uniquement,
 * jamais de renommage ni de retrait — la valeur sert au référentiel de
 * solutions et à l'onboarding intelligent (Léa, Block 4), elle doit rester
 * stable dans le temps comme les codes de solution du catalogue.
 *
 * Fail-closed par construction : `SolutionIndustry::from($value)` lève
 * `ValueError` sur toute valeur inconnue, `tryFrom()` retourne null.
 *
 * Une industrie n'est PAS un code de solution : deux solutions peuvent
 * partager la même industrie (ex. `restaurant` et `restaurantmanager`,
 * deux facettes de la même verticale — voir BOS-014).
 */
enum SolutionIndustry: string
{
    case EnergyFuel = 'energy_fuel';
    case Education = 'education';
    case Health = 'health';
    case Hospitality = 'hospitality';
    case Pharmacy = 'pharmacy';
    case Restaurant = 'restaurant';
    case Travel = 'travel';
    case DeliveryLogistics = 'delivery_logistics';
    case Retail = 'retail';
}
